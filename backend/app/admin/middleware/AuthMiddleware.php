<?php

namespace app\admin\middleware;

use app\admin\service\JwtService;
use app\admin\service\PermissionService;
use app\common\attribute\RequiresPermission;
use app\common\exception\ForbiddenException;
use app\common\exception\UnauthorizedException;
use app\model\SysUser;
use ReflectionAttribute;
use ReflectionMethod;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 后台认证 + 权限中间件
 *
 * 处理流程：
 *  1. 白名单路径放行（登录、验证码、公开接口）
 *  2. 解析 Token，失败抛 UnauthorizedException
 *  3. 解析并缓存用户信息，状态异常抛 UnauthorizedException
 *  3.1 Token 版本校验（token_version），不一致即失效
 *  4. 注入 admin_user_id / admin_user 到 Request 上
 *  5. 超级管理员跳过权限检查
 *  6. 普通用户按控制器方法上的 #[RequiresPermission] 注解鉴权，失败抛 ForbiddenException
 *
 * 缓存策略：
 *  - 用户信息：auth_user_{id}（5 min）
 *  - 接口权限注解：按 "控制器::方法" 反射结果做进程内 static 缓存（不可变数据，协程安全）
 *
 * 协程安全：本中间件**不持有任何请求级可变实例字段**。所有请求态（用户信息、
 *      是否超管）通过局部变量与方法参数传递，因此单例在协程并发下也不会串号。
 *      切勿在此类中新增「随请求变化」的实例属性。
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** 不需要认证的路径前缀 */
    private const EXCEPT = [
        '/admin/login',
        '/admin/captcha',
        '/admin/public/',
    ];

    /** 用户信息缓存 TTL（秒） */
    private const USER_CACHE_TTL = 300;

    public function process(Request $request, callable $handler): Response
    {
        // 1. 白名单
        if ($this->isExcept($request->path())) {
            return $handler($request);
        }

        // 2. Token 解析
        $payload = $this->parseToken($request);
        if ($payload === null) {
            throw new UnauthorizedException('请先登录');
        }

        // 3. 用户状态校验
        $user = $this->resolveUser((int) ($payload['user_id'] ?? 0));
        if ($user === null) {
            throw new UnauthorizedException('账号状态异常或不存在');
        }

        // 3.1 Token 版本校验：改密/重置/禁用/登出会自增 token_version 使旧 Token 失效
        if ((int) ($payload['tv'] ?? 0) !== (int) ($user['token_version'] ?? 0)) {
            throw new UnauthorizedException('登录状态已失效，请重新登录');
        }

        // 4. 注入到 Request
        $request->admin_user_id = $user['id'];
        $request->admin_user    = $user;

        // 5. 超级管理员跳过权限检查
        if (PermissionService::getInstance()->isSuperAdmin((int) $user['id'])) {
            return $handler($request);
        }

        // 6. 权限校验
        if (!$this->checkPermission($request, (int) $user['id'])) {
            throw new ForbiddenException('无权限访问');
        }

        return $handler($request);
    }

    /**
     * 解析 Token 并校验。
     *
     * @return array<string,mixed>|null
     */
    private function parseToken(Request $request): ?array
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return null;
        }
        $payload = JwtService::getInstance()->decode($token);
        return ($payload && !empty($payload['user_id'])) ? $payload : null;
    }

    /**
     * 从 Authorization 头或 ?token= 查询参数中提取 Token。
     */
    private function extractToken(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            return $token !== '' ? $token : null;
        }
        $token = (string) $request->get('token', '');
        return $token !== '' ? $token : null;
    }

    /**
     * 获取用户信息（带缓存）。
     *
     * @return array<string,mixed>|null
     */
    private function resolveUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $cacheKey = "auth_user_{$userId}";
        $cached   = cache($cacheKey);

        if (is_array($cached)) {
            if (((int) ($cached['status'] ?? 0)) !== SysUser::STATUS_NORMAL) {
                return null;
            }
            return $cached;
        }

        $user = SysUser::find($userId);
        if (!$user || $user->status !== SysUser::STATUS_NORMAL) {
            return null;
        }

        $data = [
            'id'            => $user->id,
            'username'      => $user->username,
            'nickname'      => $user->nickname,
            'avatar'        => $user->avatar,
            'dept_id'       => $user->dept_id,
            'status'        => $user->status,
            'token_version' => (int) ($user->token_version ?? 0),
        ];

        cache([$cacheKey => $data], self::USER_CACHE_TTL);
        return $data;
    }

    /**
     * 检查接口权限：根据当前路由的控制器方法上的 #[RequiresPermission] 注解判断。
     *
     * 方法若未标注注解（含闭包/无控制器路由），表示无需鉴权，放行。
     */
    private function checkPermission(Request $request, int $userId): bool
    {
        $controller = (string) ($request->controller ?? '');
        $action     = (string) ($request->action ?? '');

        $permissions = $this->getRequiredPermissions($controller, $action);
        if ($permissions === null || $permissions === []) {
            return true;
        }

        return PermissionService::getInstance()->hasAnyPermission($userId, $permissions);
    }

    /**
     * 读取控制器方法上的 #[RequiresPermission] 权限标识（带进程内 static 缓存）。
     *
     * static 缓存的是「控制器::方法 → 权限标识」这一不可变映射，与请求无关，
     * 因此在协程并发下安全（多协程读同一份只读数据）。
     *
     * @return string[]|null null 表示无注解（放行）
     */
    private function getRequiredPermissions(string $controller, string $action): ?array
    {
        if ($controller === '' || $action === '' || !class_exists($controller) || !method_exists($controller, $action)) {
            return null;
        }

        static $cache = [];
        $key = "{$controller}::{$action}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $attributes = (new ReflectionMethod($controller, $action))
            ->getAttributes(RequiresPermission::class, ReflectionAttribute::IS_INSTANCEOF);

        $permissions = $attributes === []
            ? null
            : $attributes[0]->newInstance()->permissions;

        return $cache[$key] = $permissions;
    }

    /**
     * 路径是否在白名单。
     */
    private function isExcept(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        foreach (self::EXCEPT as $pattern) {
            if ($path === $pattern || str_starts_with($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
}

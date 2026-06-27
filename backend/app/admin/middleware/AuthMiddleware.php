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
            // 2.1 SSE 专用 ticket 通道（一次性、5 秒过期），仅接受 POST /ai/chat/stream 这类显式 SSE 路径
            $userId = $this->resolveTicket($request);
            if ($userId > 0) {
                $payload = ['user_id' => $userId, 'tv' => null];
            } else {
                throw new UnauthorizedException('请先登录');
            }
        }

        // 3. 用户状态校验
        $user = $this->resolveUser((int) ($payload['user_id'] ?? 0));
        if ($user === null) {
            throw new UnauthorizedException('账号状态异常或不存在');
        }

        // 3.1 Token 版本校验：改密/重置/禁用/登出会自增 token_version 使旧 Token 失效
        //     ticket 通道由签发时校验过用户态，tv === null 时跳过校验
        if ($payload['tv'] !== null && (int) ($payload['tv']) !== (int) ($user['token_version'] ?? 0)) {
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
     * 从 Authorization 头中提取 Token（不再接受 ?token= 查询参数，避免 URL 中携带长期凭证）。
     */
    private function extractToken(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            return $token !== '' ? $token : null;
        }
        return null;
    }

    /**
     * 校验并消费 SSE 一次性 ticket。
     *
     *  - 仅允许显式 SSE 路径使用（避免普通接口也接受 URL 鉴权）
     *  - 命中后立刻删除缓存键，保证"一次性"
     *  - 校验通过返回 user_id，否则 0
     */
    private function resolveTicket(Request $request): int
    {
        $ticket = (string) $request->get('ticket', '');
        if ($ticket === '' || !$this->isSseStreamPath($request->path())) {
            return 0;
        }

        $key   = 'sse_ticket:' . $ticket;
        $value = cache($key);
        if (!is_numeric($value)) {
            return 0;
        }
        // 消费即销毁，防重放
        try {
            cache()->delete($key);
        } catch (\Throwable) {
        }
        return (int) $value;
    }

    /** 允许通过 SSE ticket 鉴权的路径白名单（按需扩展） */
    private const SSE_STREAM_PATHS = [
        '/admin/ai/chat/stream',
    ];

    private function isSseStreamPath(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        return in_array($path, self::SSE_STREAM_PATHS, true);
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

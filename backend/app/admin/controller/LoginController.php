<?php

namespace app\admin\controller;

use app\admin\service\JwtService;
use app\admin\service\LoginService;
use app\admin\validation\LoginValidator;
use app\common\attribute\RateLimit;
use app\common\traits\ApiResponse;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 后台登录控制器（前后端分离，纯 Token 模式）
 *
 * 路由前缀：/admin
 *
 * 注意：本控制器不继承 BaseController，原因：
 *  - 登录前还没有 admin_user 信息，BaseController 的字段注入是无意义的
 *  - 验证码 / 刷新 Token 也属于登录链路一部分
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class LoginController
{
    use ApiResponse;

    private LoginService $loginService;

    public function __construct()
    {
        $this->loginService = LoginService::getInstance();
    }

    /**
     * 用户登录
     *
     * 限流：同一 IP 每分钟最多 10 次登录尝试，配合 LoginService 的「账号级失败锁定」双重防爆破。
     */
    #[Post('/login')]
    #[RateLimit(limit: 10, window: 60, by: 'ip', key: 'login')]
    #[Validate(validator: LoginValidator::class, scene: 'login')]
    public function login(Request $request): Response
    {
        $data = $this->loginService->login(
            username:   (string) $request->post('username',    ''),
            password:   (string) $request->post('password',    ''),
            captchaKey: (string) $request->post('captcha_key', ''),
            captcha:    (string) $request->post('captcha',     ''),
            ip:         $request->getRealIp(),
        );

        return $this->success($data, '登录成功');
    }

    /**
     * 退出登录
     */
    #[Post('/logout')]
    public function logout(Request $request): Response
    {
        $this->loginService->logout(
            userId:   (int)    ($request->admin_user_id          ?? 0),
            username: (string) ($request->admin_user['username'] ?? ''),
            ip:       $request->getRealIp(),
            ua:       (string) $request->header('user-agent'),
        );
        return $this->success(msg: '退出成功');
    }

    /**
     * 获取图形验证码
     *
     * 限流：防止刷验证码接口耗尽服务端资源。
     * 当 captcha.enabled=false 时不再生成图片，直接 204 No Content，
     * 前端据此决定是否在登录表单里渲染验证码控件。
     */
    #[Get('/captcha')]
    #[RateLimit(limit: 30, window: 60, by: 'ip', key: 'captcha')]
    public function captcha(Request $request): Response
    {
        $data = $this->loginService->captcha();
        if ($data === null) {
            return new Response(204);
        }
        return $this->success($data);
    }

    /**
     * 刷新 Token
     *
     * 加固策略：
     *  - 仅允许在 Token 进入"临近过期窗口"（剩余时间 ≤ 1/3 lifetime）后才刷新，
     *    避免持有者通过频繁刷新获得"永久 Token"；
     *  - 累计刷新次数（claim `rc`）有上限，到达后必须重新登录；
     *  - 旧 Token 立刻经 token_version 自增作废（防止旧 Token 与新 Token 同时存在被复用）。
     */
    #[Post('/refresh')]
    #[RateLimit(limit: 5, window: 60, by: 'user', key: 'refresh')]
    public function refresh(Request $request): Response
    {
        $userId   = (int)    ($request->admin_user_id          ?? 0);
        $username = (string) ($request->admin_user['username'] ?? '');

        if ($userId <= 0) {
            return $this->unauthorized('请先登录');
        }

        // AuthMiddleware 已经把 jwt payload 校验过；这里读取过期时间与刷新链长度需重新解析
        $auth = (string) $request->header('Authorization', '');
        $rawToken = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        $jwt = JwtService::getInstance();
        $payload = $rawToken !== '' ? $jwt->decode($rawToken) : null;
        if ($payload === null) {
            return $this->unauthorized('请先登录');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        $iat = (int) ($payload['iat'] ?? 0);
        $lifetime = max($jwt->getExpire(), $exp - $iat);
        $remaining = $exp - time();

        // 临近过期窗口（剩余 ≤ 1/3 总时长）才允许刷新
        if ($remaining > intdiv($lifetime, 3)) {
            return $this->error('当前 Token 尚未临近过期，请稍后再刷新', 400);
        }

        // 刷新链长度上限：避免无限链式续期
        $refreshCount = (int) ($payload['rc'] ?? 0);
        if ($refreshCount >= 50) {
            return $this->unauthorized('Token 已多次刷新，请重新登录');
        }

        // 自增 token_version 使旧 Token 立刻失效（防止新旧 Token 同时被使用）
        $user = \app\model\SysUser::find($userId);
        if ($user === null) {
            return $this->unauthorized('账号不存在');
        }
        $user->bumpTokenVersion();
        clear_permission_cache($userId);

        $newPayload = [
            'user_id'  => $userId,
            'username' => $username,
            'tv'       => (int) ($request->admin_user['token_version'] ?? 0) + 1,
            'rc'       => $refreshCount + 1,
        ];
        return $this->success([
            'token' => $jwt->encode($newPayload),
            'refresh_count' => $newPayload['rc'],
        ], '刷新成功');
    }

    /**
     * 申请 SSE 一次性票据（短期凭证）。
     *
     * EventSource 标准不支持自定义请求头，只能通过 URL 传参；直接把 Authorization
     * 中的长期 JWT 塞进 ?token= 会让它落入 access log / 浏览器历史 / Referer / CDN，
     * 极易扩散。因此本接口要求调用方先用正常的 Authorization 头登录态调用，
     * 服务端按用户身份签发一张「一次性、5 秒过期、严格绑定 user_id」的 ticket，
     * 中间件识别 ?ticket= 时立即销毁，使 URL 中的字符串失去再次利用的价值。
     */
    #[Post('/sse-ticket')]
    #[RateLimit(limit: 60, window: 60, by: 'user', key: 'sse_ticket')]
    public function sseTicket(Request $request): Response
    {
        $userId = (int) ($request->admin_user_id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorized('请先登录');
        }

        $ticket = bin2hex(random_bytes(24));
        // 5 秒窗口足够浏览器立刻发起 SSE；过短则有时序不稳的风险
        cache(['sse_ticket:' . $ticket => $userId], 5);

        return $this->success([
            'ticket'     => $ticket,
            'expires_in' => 5,
        ]);
    }
}

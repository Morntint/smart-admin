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
     */
    #[Get('/captcha')]
    #[RateLimit(limit: 30, window: 60, by: 'ip', key: 'captcha')]
    public function captcha(Request $request): Response
    {
        return $this->success($this->loginService->captcha());
    }

    /**
     * 刷新 Token
     */
    #[Post('/refresh')]
    public function refresh(Request $request): Response
    {
        $userId   = (int)    ($request->admin_user_id          ?? 0);
        $username = (string) ($request->admin_user['username'] ?? '');

        if ($userId <= 0) {
            return $this->unauthorized('请先登录');
        }

        return $this->success([
            'token' => JwtService::getInstance()->encode([
                'user_id'  => $userId,
                'username' => $username,
                'tv'       => (int) ($request->admin_user['token_version'] ?? 0),
            ]),
        ], '刷新成功');
    }
}

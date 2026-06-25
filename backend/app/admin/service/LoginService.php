<?php

namespace app\admin\service;

use app\common\enum\LoginTypeEnum;
use app\common\enum\ResultStatusEnum;
use app\common\exception\BusinessException;
use app\common\ResponseCode;
use app\common\support\RateLimiter;
use app\model\SysLoginLog;
use app\model\SysUser;

/**
 * 登录业务服务
 *
 * 负责登录/退出/验证码的核心业务规则：
 *  - 用户名格式校验（防 SQL 注入与用户名枚举）
 *  - 验证码校验（按 captcha.enabled 配置开关）
 *  - 账号级失败锁定（连续失败 N 次锁定 M 秒，防暴力破解）
 *  - 登录日志写入（成功 + 失败）
 *  - 登录后清理权限缓存
 *
 * 控制器仅做参数收集与响应包装，不直接接触 Token / Captcha 细节。
 */
class LoginService extends BaseService
{
    /** 验证码缓存 TTL（秒） */
    private const CAPTCHA_TTL = 60;

    /** 用户名格式（3-32 位字母/数字/下划线） */
    private const USERNAME_REGEX = '/^[a-zA-Z0-9_]{3,32}$/';

    /** 账号连续登录失败锁定阈值（次） */
    private const MAX_LOGIN_FAILURES = 5;

    /** 失败计数 / 锁定窗口（秒） */
    private const LOGIN_LOCK_WINDOW = 900;

    protected string $modelClass = SysUser::class;

    /**
     * 用户登录。
     *
     * @return array{token:string,user:array<string,mixed>}
     */
    public function login(
        string $username,
        string $password,
        string $captchaKey,
        string $captcha,
        string $ip
    ): array {
        // 1. 用户名格式校验（防 SQL 注入/枚举攻击）
        if (!preg_match(self::USERNAME_REGEX, $username)) {
            $this->logLogin($username, null, false, '用户名格式不正确', $ip);
            throw new BusinessException('用户名或密码错误', ResponseCode::UNAUTHORIZED);
        }

        // 1.1 账号失败锁定校验：超过阈值直接拒绝，不再校验密码（防暴力破解）
        if (RateLimiter::attempts($this->lockKey($username)) >= self::MAX_LOGIN_FAILURES) {
            $this->logLogin($username, null, false, '账号已被锁定', $ip);
            throw new BusinessException(
                sprintf('密码错误次数过多，账号已锁定，请 %d 分钟后再试', (int) (self::LOGIN_LOCK_WINDOW / 60)),
                ResponseCode::TOO_MANY_REQUESTS
            );
        }

        // 2. 验证码校验（按配置开关）
        if (config('captcha.enabled', false) && !$this->verifyCaptcha($captchaKey, $captcha)) {
            throw BusinessException::badRequest('验证码错误或已过期');
        }

        // 3. 用户存在性 + 状态校验
        /** @var SysUser|null $user */
        $user = SysUser::where('username', $username)->first();
        if (!$user) {
            $this->recordLoginFailure($username);
            $this->logLogin($username, null, false, '用户不存在', $ip);
            throw new BusinessException('用户名或密码错误', ResponseCode::UNAUTHORIZED);
        }
        if ($user->status !== SysUser::STATUS_NORMAL) {
            $this->logLogin($username, $user->id, false, '账号已被禁用', $ip);
            throw new BusinessException('账号已被禁用', ResponseCode::FORBIDDEN);
        }

        // 4. 密码校验
        if (!$user->verifyPassword($password)) {
            $this->recordLoginFailure($username);
            $this->logLogin($username, $user->id, false, '密码错误', $ip);
            throw new BusinessException('用户名或密码错误', ResponseCode::UNAUTHORIZED);
        }

        // 登录成功：清除失败计数
        RateLimiter::clear($this->lockKey($username));

        // 5. 更新登录信息
        $user->login_ip    = $ip;
        $user->login_time  = $this->now();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->save();

        $this->logLogin($username, $user->id, true, '登录成功', $ip);

        return [
            'token' => JwtService::getInstance()->encode([
                'user_id'  => $user->id,
                'username' => $user->username,
                'tv'       => (int) ($user->token_version ?? 0),
            ]),
            'user' => [
                'id'       => $user->id,
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
                'email'    => $user->email,
                'mobile'   => $user->mobile,
            ],
        ];
    }

    /**
     * 用户退出登录。
     */
    public function logout(int $userId, string $username, string $ip, string $ua): void
    {
        if ($userId <= 0) {
            return;
        }

        SysLoginLog::create([
            'user_id'    => $userId,
            'username'   => $username,
            'login_type' => LoginTypeEnum::LOGOUT->value,
            'ip'         => $ip,
            'user_agent' => $ua,
            'status'     => ResultStatusEnum::SUCCESS->value,
            'msg'        => '退出成功',
        ]);

        // 自增 Token 版本，使当前 Token 立即失效（防止登出后旧 Token 仍可用）
        SysUser::where('id', $userId)->increment('token_version');

        clear_permission_cache($userId);
    }

    /**
     * 生成图形验证码，返回 key + base64 图片。
     *
     * @return array{key:string,image:string}
     */
    public function captcha(): array
    {
        $builder = new \Webman\Captcha\CaptchaBuilder();
        $builder->build();

        $key = bin2hex(random_bytes(16));
        cache(['captcha_' . $key => strtolower($builder->getPhrase())], self::CAPTCHA_TTL);

        return [
            'key'   => $key,
            'image' => 'data:image/jpeg;base64,' . base64_encode($builder->getContent()),
        ];
    }

    /**
     * 校验图形验证码（校验后立即销毁，防重放）。
     */
    private function verifyCaptcha(string $key, string $input): bool
    {
        if ($key === '' || $input === '') {
            return false;
        }
        $cacheKey = 'captcha_' . $key;
        $stored   = cache($cacheKey);
        if ($stored === null) {
            return false;
        }
        cache()->delete($cacheKey);
        return strtolower($input) === $stored;
    }

    /**
     * 记录一次登录失败（账号维度计数，窗口内累计）。
     */
    private function recordLoginFailure(string $username): void
    {
        RateLimiter::increment($this->lockKey($username), self::LOGIN_LOCK_WINDOW);
    }

    /**
     * 账号失败锁定计数键（按用户名，忽略大小写差异由格式校验保证）。
     */
    private function lockKey(string $username): string
    {
        return 'login_fail:' . strtolower($username);
    }

    /**
     * 写入登录日志（成功/失败均记录）。
     */
    private function logLogin(string $username, ?int $userId, bool $success, ?string $msg, string $ip): void
    {
        SysLoginLog::create([
            'user_id'    => $userId,
            'username'   => $username,
            'login_type' => LoginTypeEnum::LOGIN->value,
            'ip'         => $ip,
            'user_agent' => request()->header('user-agent'),
            'status'     => $success ? ResultStatusEnum::SUCCESS->value : ResultStatusEnum::FAIL->value,
            'msg'        => $msg,
        ]);
    }
}

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

    /** 账号+IP 连续登录失败锁定阈值（次） */
    private const MAX_LOGIN_FAILURES = 5;

    /** 失败计数 / 锁定窗口（秒） */
    private const LOGIN_LOCK_WINDOW = 900;

    /** 单账号在窗口内累计失败到此阈值后，强制走验证码（防止任意 IP 把账号锁死，仅多了"必须看图"成本） */
    private const FORCE_CAPTCHA_THRESHOLD = 3;

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

        // 1.1 「账号 + IP」维度失败锁定：同一 IP 连续错 N 次直接拒绝（防同 IP 暴力破解）
        if (RateLimiter::attempts($this->lockKey($username, $ip)) >= self::MAX_LOGIN_FAILURES) {
            $this->logLogin($username, null, false, '账号已被锁定（同 IP 连续失败）', $ip);
            throw new BusinessException(
                sprintf('密码错误次数过多，请 %d 分钟后再试', (int) (self::LOGIN_LOCK_WINDOW / 60)),
                ResponseCode::TOO_MANY_REQUESTS
            );
        }

        // 1.2 单账号在窗口内的失败总数达到「强制验证码」阈值后，必须带 captcha；
        //     此分支不直接拒绝，避免任意 IP 把账号锁死，但拉高攻击者的人工成本
        $forceCaptcha = RateLimiter::attempts($this->userFailKey($username)) >= self::FORCE_CAPTCHA_THRESHOLD;

        // 2. 验证码校验（按配置开关；强制验证码场景始终校验）
        $captchaRequired = $forceCaptcha || config('captcha.enabled', false);
        if ($captchaRequired && !$this->verifyCaptcha($captchaKey, $captcha)) {
            throw BusinessException::badRequest('验证码错误或已过期');
        }

        // 3. 用户存在性 + 状态校验
        /** @var SysUser|null $user */
        $user = SysUser::where('username', $username)->first();
        if (!$user) {
            $this->recordLoginFailure($username, $ip);
            $this->logLogin($username, null, false, '用户不存在', $ip);
            throw new BusinessException('用户名或密码错误', ResponseCode::UNAUTHORIZED);
        }
        if ($user->status !== SysUser::STATUS_NORMAL) {
            $this->logLogin($username, $user->id, false, '账号已被禁用', $ip);
            throw new BusinessException('账号已被禁用', ResponseCode::FORBIDDEN);
        }

        // 4. 密码校验
        if (!$user->verifyPassword($password)) {
            $this->recordLoginFailure($username, $ip);
            $this->logLogin($username, $user->id, false, '密码错误', $ip);
            throw new BusinessException('用户名或密码错误', ResponseCode::UNAUTHORIZED);
        }

        // 登录成功：清除两个维度的失败计数
        RateLimiter::clear($this->lockKey($username, $ip));
        RateLimiter::clear($this->userFailKey($username));

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
     * 若 captcha 全局开关关闭（{@see config('captcha.enabled')}），不再生成图片浪费资源，
     * 返回 null —— 控制器据此回 204 No Content。
     *
     * @return array{key:string,image:string}|null
     */
    public function captcha(): ?array
    {
        if (!config('captcha.enabled', false)) {
            return null;
        }

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
     * 记录一次登录失败：同时累计「账号+IP」与「账号」两个维度。
     */
    private function recordLoginFailure(string $username, string $ip): void
    {
        RateLimiter::increment($this->lockKey($username, $ip), self::LOGIN_LOCK_WINDOW);
        RateLimiter::increment($this->userFailKey($username), self::LOGIN_LOCK_WINDOW);
    }

    /**
     * 账号+IP 双维度锁定 key。
     *
     * 旧实现按 username 单维度，任意 IP 失败 5 次即锁定 → 定向 DoS。改为
     * 同 IP 攻击者只能"锁住自己这条 IP"对同一账号 15 分钟；其它 IP 的正常用户不受影响。
     */
    private function lockKey(string $username, string $ip): string
    {
        return 'login_fail:' . strtolower($username) . ':' . $ip;
    }

    /**
     * 仅按 username 计的全局失败计数（用于触发强制验证码，而不是直接拒绝）。
     */
    private function userFailKey(string $username): string
    {
        return 'login_fail_user:' . strtolower($username);
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

<?php

namespace app\model;

/**
 * 登录日志模型
 *
 * 表：sys_login_log
 *
 * 业务约束：
 *  - 仅插入不更新（$timestamps = false）
 *  - 由 LoginService 写入，OperationLog 中间件不会触碰
 */
class SysLoginLog extends BaseModel
{
    /** 登录类型：登录 */
    public const TYPE_LOGIN  = 1;
    /** 登录类型：登出 */
    public const TYPE_LOGOUT = 2;

    /** 状态：失败 */
    public const STATUS_FAIL    = 0;
    /** 状态：成功 */
    public const STATUS_SUCCESS = 1;

    protected $table = 'sys_login_log';

    /** 关闭 Eloquent 自动维护时间戳；日志表只插入、不更新 */
    public $timestamps = false;

    /**
     * 允许批量赋值的字段。
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'username',
        'login_type',
        'ip',
        'user_agent',
        'status',
        'msg',
    ];

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    public function getLoginTypeTextAttribute(): string
    {
        return (int) $this->login_type === self::TYPE_LOGIN ? '登录' : '登出';
    }

    public function getStatusTextAttribute(): string
    {
        return (int) $this->status === self::STATUS_SUCCESS ? '成功' : '失败';
    }

    // -------------------------------------------------------------------------
    // 写入快捷方法（推荐通过 LoginService 调用）
    // -------------------------------------------------------------------------

    /**
     * 记录登录日志。
     */
    public static function recordLogin(?int $userId, string $username, bool $success = true, ?string $msg = null): self
    {
        return self::create([
            'user_id'    => $userId,
            'username'   => $username,
            'login_type' => self::TYPE_LOGIN,
            'ip'         => request()->getRealIp(),
            'user_agent' => request()->header('user-agent'),
            'status'     => $success ? self::STATUS_SUCCESS : self::STATUS_FAIL,
            'msg'        => $msg,
        ]);
    }

    /**
     * 记录登出日志。
     */
    public static function recordLogout(?int $userId, string $username): self
    {
        return self::create([
            'user_id'    => $userId,
            'username'   => $username,
            'login_type' => self::TYPE_LOGOUT,
            'ip'         => request()->getRealIp(),
            'user_agent' => request()->header('user-agent'),
            'status'     => self::STATUS_SUCCESS,
            'msg'        => '登出成功',
        ]);
    }
}

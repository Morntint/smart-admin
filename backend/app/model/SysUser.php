<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统用户模型
 *
 * 表：sys_user
 *
 * 业务约束：
 *  - id = 1 为超级管理员（不可删除/不可改状态）
 *  - 密码使用 bcrypt 哈希（make_password / verifyPassword）
 *  - status = 1 正常 / 0 禁用，禁用后中间件拒绝其访问
 */
class SysUser extends BaseModel
{
    use SoftDeletes;

    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：正常 */
    public const STATUS_NORMAL   = 1;

    /** 性别：未知 */
    public const SEX_UNKNOWN = 0;
    /** 性别：男 */
    public const SEX_MALE    = 1;
    /** 性别：女 */
    public const SEX_FEMALE  = 2;

    protected $table      = 'sys_user';
    protected $primaryKey = 'id';
    public    $timestamps = false;

    /** 隐藏字段（toArray / toJson 时不输出） */
    protected $hidden = ['password'];

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'username',
        'password',
        'nickname',
        'avatar',
        'email',
        'mobile',
        'sex',
        'status',
        'dept_id',
        'remark',
        'login_ip',
        'login_time',
        'login_count',
        'created_by',
        'updated_by',
    ];

    /**
     * 状态映射（值 → 中文）。
     *
     * @var array<int,string>
     */
    public static array $statusMap = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_NORMAL   => '正常',
    ];

    /**
     * 性别映射（值 → 中文）。
     *
     * @var array<int,string>
     */
    public static array $sexMap = [
        self::SEX_UNKNOWN => '未知',
        self::SEX_MALE    => '男',
        self::SEX_FEMALE  => '女',
    ];

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    public function getStatusTextAttribute(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }

    public function getSexTextAttribute(): string
    {
        return self::$sexMap[$this->sex] ?? '未知';
    }

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(SysDepartment::class, 'dept_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SysRole::class, SysUserRole::class, 'user_id', 'role_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    /**
     * 验证密码（支持哈希升级）。
     *
     * 当存储的哈希 cost 不再满足要求时，自动重新哈希并保存。
     */
    public function verifyPassword(string $password): bool
    {
        if (!password_verify($password, (string) $this->password)) {
            return false;
        }

        if (password_needs_rehash($this->password, PASSWORD_BCRYPT, ['cost' => 10])) {
            try {
                $this->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $this->save();
            } catch (\Throwable) {
                // 静默失败：升级失败不影响登录
            }
        }

        return true;
    }

    /**
     * 获取用户角色 ID 列表（依赖关联预加载或自动触发查询）。
     *
     * @return int[]
     */
    public function getRoleIds(): array
    {
        return $this->roles->pluck('id')->all();
    }

    /**
     * 是否为超级管理员（按 id = 1 判定，与 PermissionService 双保险）。
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->id === 1;
    }
}

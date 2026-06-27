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
 *
 * @property string               $username
 * @property string               $password
 * @property string|null          $nickname
 * @property string|null          $avatar
 * @property string|null          $email
 * @property string|null          $mobile
 * @property int                  $sex
 * @property int                  $status
 * @property int|null             $dept_id
 * @property string|null          $login_ip
 * @property string|null          $login_time
 * @property int                  $login_count
 * @property int                  $token_version
 * @property string|null          $remark
 * @property-read string          $status_text
 * @property-read string          $sex_text
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysRole> $roles
 * @property-read \app\model\SysDepartment|null $department
 * @property-read int[]           $role_ids
 * @property-write mixed          $dept_name
 * @property-write mixed          $role_names
 * @property-write mixed          $role_ids
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
        'token_version',
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
     * L-9：升级失败旧实现完全静默，问题难以察觉；改为记 warning 日志。
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
            } catch (\Throwable $e) {
                \support\Log::warning('密码哈希升级失败', [
                    'user_id' => $this->id,
                    'error'   => $e->getMessage(),
                ]);
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

    /**
     * 自增 Token 版本号，使该用户此前签发的所有 Token 立即失效。
     *
     * 调用时机：修改密码、重置密码、禁用账号、删除账号、强制下线。
     * 原子自增，避免并发覆盖；同时清理用户缓存由调用方负责（clear_permission_cache）。
     */
    public function bumpTokenVersion(): void
    {
        $this->increment('token_version');
    }

    /**
     * 模型事件挂钩：用户 save / delete 时自动失效相关缓存。
     *
     * 解决 M-5：避免散落在各处的「忘记调 clear_permission_cache」导致旧 token_version
     * / 旧角色权限继续在缓存里活 5 分钟。任何对 SysUser 的写操作（含批量 update via
     * 模型实例、status 切换、role 同步）都会触发，从根上保证缓存与 DB 一致。
     */
    protected static function booted(): void
    {
        static::saved(function (SysUser $user): void {
            clear_permission_cache((int) $user->id);
        });
        static::deleted(function (SysUser $user): void {
            clear_permission_cache((int) $user->id);
        });
    }
}

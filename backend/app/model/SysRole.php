<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统角色模型
 *
 * 表：sys_role
 *
 * 业务约束：
 *  - code = 'super_admin' 为超级管理员（不可删除/不可改 code）
 *  - data_scope 表示数据权限范围（DataScopeEnum）
 *  - data_scope_depts：自定义部门范围（逗号分隔的部门 ID 列表）
 *
 * @property string               $name
 * @property string               $code
 * @property int                  $sort
 * @property int                  $status
 * @property int                  $data_scope
 * @property string|null          $data_scope_depts
 * @property string|null          $remark
 * @property-read string          $status_text
 * @property-read string          $data_scope_text
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysUser> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysMenu> $menus
 * @property mixed $menu_ids
 * @property mixed $menu_permissions
 */
class SysRole extends BaseModel
{
    use SoftDeletes;

    // ------- 数据范围 -------
    /** 全部数据 */
    public const DATA_SCOPE_ALL              = 1;
    /** 本部门数据 */
    public const DATA_SCOPE_DEPT             = 2;
    /** 本部门及以下数据 */
    public const DATA_SCOPE_DEPT_AND_CHILD   = 3;
    /** 仅本人数据 */
    public const DATA_SCOPE_SELF             = 4;
    /** 自定义数据（需配合 data_scope_depts） */
    public const DATA_SCOPE_CUSTOM           = 5;

    // ------- 状态 -------
    public const STATUS_DISABLED = 0;
    public const STATUS_NORMAL   = 1;

    protected $table = 'sys_role';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'code',
        'sort',
        'status',
        'data_scope',
        'data_scope_depts',
        'remark',
        'created_by',
        'updated_by',
    ];

    /** @var array<int,string> */
    public static array $statusMap = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_NORMAL   => '正常',
    ];

    /** @var array<int,string> */
    public static array $dataScopeMap = [
        self::DATA_SCOPE_ALL            => '全部数据',
        self::DATA_SCOPE_DEPT           => '本部门数据',
        self::DATA_SCOPE_DEPT_AND_CHILD => '本部门及以下数据',
        self::DATA_SCOPE_SELF           => '仅本人数据',
        self::DATA_SCOPE_CUSTOM         => '自定义数据',
    ];

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    public function getStatusTextAttribute(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }

    public function getDataScopeTextAttribute(): string
    {
        return self::$dataScopeMap[$this->data_scope] ?? '未知';
    }

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SysUser::class, SysUserRole::class, 'role_id', 'user_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(SysMenu::class, SysRoleMenu::class, 'role_id', 'menu_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    /**
     * @return int[]
     */
    public function getUserIds(): array
    {
        return $this->users->pluck('id')->all();
    }

    /**
     * @return int[]
     */
    public function getMenuIds(): array
    {
        return $this->menus->pluck('id')->all();
    }
}

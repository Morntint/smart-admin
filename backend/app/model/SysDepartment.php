<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统部门模型
 *
 * 表：sys_department
 *
 * 业务约束：
 *  - parent_id 自关联实现无限层级
 *  - id = 1 为根部门（不可删除）
 *  - 删除前需保证无子部门、无关联用户
 *
 * @property int                  $parent_id
 * @property string               $name
 * @property string|null          $leader
 * @property string|null          $mobile
 * @property string|null          $email
 * @property int                  $sort
 * @property int                  $status
 * @property string|null          $remark
 * @property-read string          $status_text
 * @property-read \app\model\SysDepartment|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysDepartment> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysUser> $users
 * @property-write int|null $user_count
 */
class SysDepartment extends BaseModel
{
    use SoftDeletes;

    public const STATUS_DISABLED = 0;
    public const STATUS_NORMAL   = 1;

    protected $table = 'sys_department';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'leader',
        'mobile',
        'email',
        'sort',
        'status',
        'remark',
        'created_by',
        'updated_by',
    ];

    /** @var array<int,string> */
    public static array $statusMap = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_NORMAL   => '正常',
    ];

    public function getStatusTextAttribute(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<static,static>
     */
    public function children(): HasMany
    {
        /** @var HasMany<static,static> $relation */
        $relation = $this->hasMany(self::class, 'parent_id')->orderBy('sort', 'asc');
        return $relation;
    }

    public function users(): HasMany
    {
        return $this->hasMany(SysUser::class, 'dept_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    public function getFullPath(): string
    {
        $paths  = [$this->name];
        $parent = $this->parent;
        while ($parent && $parent->id > 0) {
            array_unshift($paths, $parent->name);
            $parent = $parent->parent;
        }
        return implode(' / ', $paths);
    }

    /**
     * @return int[]
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $child->getDescendantIds());
        }
        return $ids;
    }

    /**
     * @return int[]
     */
    public function getAncestorIds(): array
    {
        $ids    = [];
        $parent = $this->parent;
        while ($parent && $parent->id > 0) {
            $ids[]  = $parent->id;
            $parent = $parent->parent;
        }
        return $ids;
    }

    public function getUserCount(): int
    {
        return $this->users()->count();
    }
}

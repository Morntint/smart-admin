<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统菜单模型
 *
 * 表：sys_menu
 *
 * 业务约束：
 *  - 菜单分三类：DIR(1=目录) / MENU(2=菜单) / BUTTON(3=按钮)
 *  - 按钮必须设置 permission（权限标识）
 *  - 同 type 列表、parent_id 自关联实现树结构
 *
 * @property int                  $parent_id
 * @property string               $name
 * @property string|null          $route_name
 * @property string|null          $icon
 * @property string|null          $path
 * @property string|null          $component
 * @property string|null          $redirect
 * @property int                  $type
 * @property string|null          $permission
 * @property int                  $sort
 * @property int                  $status
 * @property int                  $is_external
 * @property int                  $is_cache
 * @property int                  $is_visible
 * @property int                  $is_hide_tab
 * @property int                  $is_iframe
 * @property int                  $is_full_page
 * @property int                  $fixed_tab
 * @property string|null          $active_path
 * @property string|null          $remark
 * @property-read string          $type_text
 * @property-read string          $status_text
 * @property-read \app\model\SysMenu|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysMenu> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\app\model\SysRole> $roles
 * @property-read string          $parent_name
 * @property-write string|null    $parent_name
 */
class SysMenu extends BaseModel
{
    use SoftDeletes;

    // ------- 菜单类型 -------
    /** 目录 */
    public const TYPE_DIR    = 1;
    /** 菜单 */
    public const TYPE_MENU   = 2;
    /** 按钮 */
    public const TYPE_BUTTON = 3;

    // ------- 状态 -------
    public const STATUS_DISABLED = 0;
    public const STATUS_NORMAL   = 1;

    // ------- 是否外链 -------
    public const EXTERNAL_NO  = 0;
    public const EXTERNAL_YES = 1;

    // ------- 是否缓存 -------
    public const CACHE_NO  = 0;
    public const CACHE_YES = 1;

    // ------- 是否显示 -------
    public const VISIBLE_NO  = 0;
    public const VISIBLE_YES = 1;

    // ------- 是否隐藏标签 -------
    public const HIDE_TAB_NO  = 0;
    public const HIDE_TAB_YES = 1;

    // ------- 是否Iframe -------
    public const IFRAME_NO  = 0;
    public const IFRAME_YES = 1;

    // ------- 是否全屏 -------
    public const FULL_PAGE_NO  = 0;
    public const FULL_PAGE_YES = 1;

    // ------- 是否固定标签 -------
    public const FIXED_TAB_NO  = 0;
    public const FIXED_TAB_YES = 1;

    protected $table = 'sys_menu';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'route_name',
        'icon',
        'path',
        'component',
        'redirect',
        'type',
        'permission',
        'sort',
        'status',
        'is_external',
        'is_cache',
        'is_visible',
        'is_hide_tab',
        'is_iframe',
        'is_full_page',
        'fixed_tab',
        'active_path',
        'remark',
        'created_by',
        'updated_by',
    ];

    /** @var array<int,string> */
    public static array $typeMap = [
        self::TYPE_DIR    => '目录',
        self::TYPE_MENU   => '菜单',
        self::TYPE_BUTTON => '按钮',
    ];

    /** @var array<int,string> */
    public static array $statusMap = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_NORMAL   => '正常',
    ];

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    public function getTypeTextAttribute(): string
    {
        return self::$typeMap[$this->type] ?? '未知';
    }

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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SysRole::class, SysRoleMenu::class, 'menu_id', 'role_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    /**
     * 完整菜单路径（祖先 → 自身，斜杠连接）。
     */
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
     * 获取所有后代菜单 ID（递归）。
     *
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

    public function isButton(): bool { return (int) $this->type === self::TYPE_BUTTON; }
    public function isMenu():   bool { return (int) $this->type === self::TYPE_MENU; }
    public function isDir():    bool { return (int) $this->type === self::TYPE_DIR; }
}

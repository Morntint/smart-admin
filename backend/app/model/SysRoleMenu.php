<?php

namespace app\model;

/**
 * 角色 - 菜单关联表
 *
 * 表：sys_role_menu
 *
 * 仅作为多对多中间表，不维护时间戳。
 */
class SysRoleMenu extends BaseModel
{
    protected $table   = 'sys_role_menu';
    public    $timestamps = false;

    /** @var string[] */
    protected $fillable = ['role_id', 'menu_id'];

    /**
     * 批量重设角色的菜单（先全删后插入，幂等操作）。
     *
     * @param int[] $menuIds
     */
    public static function addRoleMenus(int $roleId, array $menuIds): void
    {
        self::where('role_id', $roleId)->delete();

        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        if ($menuIds === []) {
            return;
        }

        $rows = array_map(
            fn(int $menuId) => ['role_id' => $roleId, 'menu_id' => $menuId],
            $menuIds
        );
        self::insert($rows);
    }
}

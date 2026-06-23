<?php

namespace app\model;

/**
 * 用户 - 角色关联表
 *
 * 表：sys_user_role
 *
 * 仅作为多对多中间表，不维护时间戳。
 */
class SysUserRole extends BaseModel
{
    protected $table   = 'sys_user_role';
    public    $timestamps = false;

    /** @var string[] */
    protected $fillable = ['user_id', 'role_id'];

    /**
     * 批量重设用户的角色（先全删后插入，幂等操作）。
     *
     * @param int[] $roleIds
     */
    public static function addUserRoles(int $userId, array $roleIds): void
    {
        self::where('user_id', $userId)->delete();

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return;
        }

        $rows = array_map(
            fn(int $roleId) => ['user_id' => $userId, 'role_id' => $roleId],
            $roleIds
        );
        self::insert($rows);
    }
}

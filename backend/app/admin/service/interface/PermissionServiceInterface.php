<?php

namespace app\admin\service\interface;

/**
 * 权限服务接口
 *
 * 实现：app\admin\service\PermissionService
 */
interface PermissionServiceInterface
{
    /**
     * 判断用户是否为超级管理员。
     */
    public function isSuperAdmin(int $userId): bool;

    /**
     * 判断用户是否拥有指定权限（满足其一即可）。
     *
     * @param string[] $permissions
     */
    public function hasAnyPermission(int $userId, array $permissions): bool;

    /**
     * 获取用户所有权限标识。
     *
     * @return string[]
     */
    public function getPermissions(int $userId): array;

    /**
     * 获取用户角色 code 列表。
     *
     * @return string[]
     */
    public function getRoleCodes(int $userId): array;

    /**
     * 清除用户的所有权限相关缓存。
     */
    public function clearCache(int $userId): void;
}

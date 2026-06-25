<?php

namespace app\admin\service;

use app\admin\service\interface\PermissionServiceInterface;
use app\model\SysUser;

/**
 * 权限服务
 *
 * 负责权限标识与角色 code 的查询与缓存：
 *  - 缓存键：user_permissions_{uid}、user_role_codes_{uid}（auth_user_{uid} 由中间件维护）
 *  - 缓存 TTL 较短（5min），保证权限变更后不久就生效；写操作通过 clearCache() 主动失效
 *  - 超级管理员通过 role.code = 'super_admin' 标记，跳过所有权限校验
 */
class PermissionService implements PermissionServiceInterface
{
    /** 权限缓存 TTL（秒） */
    private const PERM_CACHE_TTL = 300;

    /** 超级管理员角色 code */
    public const SUPER_ADMIN_CODE = 'super_admin';

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 判断用户是否为超级管理员。
     */
    public function isSuperAdmin(int $userId): bool
    {
        return in_array(self::SUPER_ADMIN_CODE, $this->getRoleCodes($userId), true);
    }

    /**
     * 判断用户是否拥有指定权限（满足其一即可）。
     *
     * @param string[] $permissions
     */
    public function hasAnyPermission(int $userId, array $permissions): bool
    {
        if ($permissions === [] || $this->isSuperAdmin($userId)) {
            return true;
        }

        $userPerms = $this->getPermissions($userId);
        foreach ($permissions as $perm) {
            if (in_array($perm, $userPerms, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取用户所有权限标识（带缓存）。
     *
     * @return string[]
     */
    public function getPermissions(int $userId): array
    {
        $cacheKey = $this->permKey($userId);
        $cached   = cache($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $user = SysUser::find($userId);
        if (!$user) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int,\app\model\SysRole> $roles */
        $roles = $user->roles()->with('menus')->get();
        $permissions = $roles
            ->flatMap(fn(\app\model\SysRole $role) => $role->menus->pluck('permission'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        cache([$cacheKey => $permissions], self::PERM_CACHE_TTL);
        return $permissions;
    }

    /**
     * 获取用户角色 code 列表（带缓存）。
     *
     * @return string[]
     */
    public function getRoleCodes(int $userId): array
    {
        $cacheKey = $this->roleKey($userId);
        $cached   = cache($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $user = SysUser::find($userId);
        if (!$user) {
            return [];
        }

        $codes = $user->roles()->pluck('code')->toArray();
        cache([$cacheKey => $codes], self::PERM_CACHE_TTL);
        return $codes;
    }

    /**
     * 清除用户的所有权限相关缓存（含 AuthMiddleware 的用户缓存与数据范围缓存）。
     */
    public function clearCache(int $userId): void
    {
        cache()->delete(
            $this->permKey($userId),
            $this->roleKey($userId),
            "auth_user_{$userId}"
        );

        // 连带清除数据范围缓存（角色变更 / 换部门均需失效）
        DataScopeService::getInstance()->clearCache($userId);
    }

    private function permKey(int $userId): string
    {
        return "user_permissions_{$userId}";
    }

    private function roleKey(int $userId): string
    {
        return "user_role_codes_{$userId}";
    }
}

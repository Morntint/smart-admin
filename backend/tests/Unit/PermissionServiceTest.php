<?php

namespace Tests\Unit;

use app\admin\service\PermissionService;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * PermissionService 单测。
 *
 * 覆盖：
 *  ✓ 未知用户 isSuperAdmin → false
 *  ✓ 未知用户 hasAnyPermission([]) 直接 true（空权限列表始终放行）
 *  ✓ 未知用户 hasAnyPermission(['x']) → false
 *  ✓ 普通用户角色 + 菜单 → getPermissions 命中
 *  ✓ 角色被禁用后 → getPermissions 不再返回该角色的权限（与 M-3 行为对齐）
 *  ✓ 菜单被禁用后 → 该菜单 permission 也不返回
 *  ✓ clearCache 删除所有相关 key
 *
 * 注：本测试和 AiChatSendTest 共享同一 SQLite 数据库（tests/bootstrap.php 单次 init）。
 * 用 IF NOT EXISTS 建表，setUp 中 DELETE 清数据避免互相干扰。
 */
class PermissionServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $c = self::conn();
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sys_user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(64) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL DEFAULT '',
                nickname VARCHAR(64) DEFAULT '',
                status INT DEFAULT 1,
                dept_id INT DEFAULT NULL,
                token_version INT DEFAULT 0,
                login_count INT DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME
            )
        SQL);
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sys_role (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(64) NOT NULL,
                code VARCHAR(64) NOT NULL UNIQUE,
                sort INT DEFAULT 0,
                status INT DEFAULT 1,
                data_scope INT DEFAULT 1,
                data_scope_depts TEXT,
                remark TEXT,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME
            )
        SQL);
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sys_menu (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INT DEFAULT 0,
                name VARCHAR(64) NOT NULL,
                type INT NOT NULL DEFAULT 2,
                path VARCHAR(255) DEFAULT '',
                permission VARCHAR(128) DEFAULT NULL,
                status INT DEFAULT 1,
                sort INT DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME
            )
        SQL);
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sys_user_role (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                created_at DATETIME
            )
        SQL);
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sys_role_menu (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INT NOT NULL,
                menu_id INT NOT NULL,
                created_at DATETIME
            )
        SQL);
    }

    protected function setUp(): void
    {
        $c = self::conn();
        foreach (['sys_role_menu', 'sys_user_role', 'sys_menu', 'sys_role', 'sys_user'] as $t) {
            $c->statement("DELETE FROM {$t}");
        }
        // 清缓存（array 驱动，进程内）
        try {
            cache()->delete(
                'user_permissions_1', 'user_role_codes_1', 'user_menu_ids_1', 'auth_user_1', 'user_data_scope_1'
            );
        } catch (\Throwable) {}
    }

    public function testUnknownUserIsNotSuperAdmin(): void
    {
        $this->assertFalse(PermissionService::getInstance()->isSuperAdmin(999999));
    }

    public function testEmptyRequiredPermissionsAlwaysPasses(): void
    {
        $this->assertTrue(PermissionService::getInstance()->hasAnyPermission(999999, []));
    }

    public function testUnknownUserFailsAnyRequiredPermission(): void
    {
        $this->assertFalse(PermissionService::getInstance()->hasAnyPermission(999999, ['system:user:add']));
    }

    public function testPermissionPropagatesThroughRoleAndMenu(): void
    {
        $userId = $this->seedUser('alice', 1);
        $roleId = $this->seedRole('editor', 1);
        $menuId = $this->seedMenu('用户管理', 'system:user:add', 1);
        $this->bindUserRole($userId, $roleId);
        $this->bindRoleMenu($roleId, $menuId);

        $perms = PermissionService::getInstance()->getPermissions($userId);
        $this->assertContains('system:user:add', $perms);
        $this->assertTrue(PermissionService::getInstance()->hasAnyPermission($userId, ['system:user:add']));
    }

    public function testDisabledRoleDoesNotGrantPermissions(): void
    {
        $userId = $this->seedUser('bob', 1);
        $roleId = $this->seedRole('disabled_role', 0); // status=0 禁用
        $menuId = $this->seedMenu('删除', 'system:user:delete', 1);
        $this->bindUserRole($userId, $roleId);
        $this->bindRoleMenu($roleId, $menuId);

        $this->assertFalse(PermissionService::getInstance()->hasAnyPermission($userId, ['system:user:delete']));
    }

    public function testDisabledMenuPermissionRevoked(): void
    {
        $userId = $this->seedUser('carol', 1);
        $roleId = $this->seedRole('editor2', 1);
        $menuId = $this->seedMenu('禁用按钮', 'system:user:edit', 0); // 菜单禁用
        $this->bindUserRole($userId, $roleId);
        $this->bindRoleMenu($roleId, $menuId);

        $this->assertFalse(PermissionService::getInstance()->hasAnyPermission($userId, ['system:user:edit']));
    }

    public function testClearCacheRemovesAllRelatedKeys(): void
    {
        $userId = $this->seedUser('dan', 1);
        // 触发缓存写入
        PermissionService::getInstance()->getPermissions($userId);
        PermissionService::getInstance()->getRoleCodes($userId);

        $this->assertNotNull(cache("user_permissions_{$userId}"));

        PermissionService::getInstance()->clearCache($userId);
        $this->assertNull(cache("user_permissions_{$userId}"));
        $this->assertNull(cache("user_role_codes_{$userId}"));
    }

    // ─────── helpers ───────

    private function seedUser(string $username, int $status): int
    {
        return $this->insert('sys_user', [
            'username' => $username,
            'status'   => $status,
        ]);
    }

    private function seedRole(string $code, int $status): int
    {
        return $this->insert('sys_role', [
            'name'   => $code,
            'code'   => $code,
            'status' => $status,
        ]);
    }

    private function seedMenu(string $name, string $permission, int $status): int
    {
        return $this->insert('sys_menu', [
            'name'       => $name,
            'permission' => $permission,
            'type'       => 3, // BUTTON
            'status'     => $status,
        ]);
    }

    private function bindUserRole(int $uid, int $rid): void
    {
        $this->insert('sys_user_role', ['user_id' => $uid, 'role_id' => $rid]);
    }

    private function bindRoleMenu(int $rid, int $mid): void
    {
        $this->insert('sys_role_menu', ['role_id' => $rid, 'menu_id' => $mid]);
    }

    private function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $conn = self::conn();
        $conn->insert($sql, $data);
        return (int) $conn->getPdo()->lastInsertId();
    }

    private static function conn(): Connection
    {
        return Model::getConnectionResolver()->connection();
    }
}

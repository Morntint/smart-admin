<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysRole;
use app\model\SysRoleMenu;
use app\model\SysUserRole;
use support\Request;

/**
 * 角色业务服务
 *
 * 业务规则：
 *  - 角色 code 全局唯一；超级管理员（super_admin）的 code 不可改、不可删
 *  - 角色与菜单为多对多关系，关联通过 SysRoleMenu 维护
 *  - 数据权限范围由 data_scope + data_scope_depts（自定义部门 ID 列表）决定
 */
class RoleService extends BaseService
{
    /** 超级管理员角色 code（不可删除 / 不可改 code） */
    public const SUPER_ADMIN_CODE = 'super_admin';

    protected string $modelClass = SysRole::class;

    /**
     * 角色分页列表。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = SysRole::with('menus');
        $this->applyFilters($query, filters: [
            'status' => $request->get('status', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['name', 'code']
        );

        $result = $this->paginate($query, $request);
        $result['list']->each(function (SysRole $role) {
            $role->menu_ids = $role->menus->pluck('id');
            $role->makeHidden(['menus']);
        });
        return $result;
    }

    /**
     * 查询角色详情（含菜单 ID 与按钮权限标识）。
     */
    public function detail(int $id): SysRole
    {
        /** @var SysRole $role */
        $role = $this->findOrFail($id, ['menus'], '角色不存在');
        $role->menu_ids         = $role->menus->pluck('id');
        $role->menu_permissions = $role->menus->whereNotNull('permission')->pluck('permission');
        $role->makeHidden(['menus']);
        return $role;
    }

    /**
     * 创建角色。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysRole
    {
        $code = trim((string) ($data['code'] ?? ''));
        $this->assertUnique('code', $code, null, '角色代码已存在');

        return $this->transaction(function () use ($data, $code, $operatorId) {
            $dataScope = (int) ($data['data_scope'] ?? SysRole::DATA_SCOPE_ALL);
            $role = SysRole::create([
                'name'             => trim((string) ($data['name'] ?? '')),
                'code'             => $code,
                'sort'             => (int) ($data['sort']       ?? 0),
                'status'           => (int) ($data['status']     ?? SysRole::STATUS_NORMAL),
                'data_scope'       => $dataScope,
                'data_scope_depts' => $this->normalizeDepts($dataScope, $data['data_scope_depts'] ?? null),
                'remark'           => trim((string) ($data['remark'] ?? '')),
                'created_by'       => $operatorId,
                'created_at'       => $this->now(),
            ]);

            $menuIds = (array) ($data['menu_ids'] ?? []);
            if ($menuIds !== []) {
                SysRoleMenu::addRoleMenus($role->id, $menuIds);
            }

            return $role;
        });
    }

    /**
     * 更新角色。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysRole
    {
        /** @var SysRole $role */
        $role = $this->findOrFail($id, [], '角色不存在');
        $code = trim((string) ($data['code'] ?? ''));

        // 超级管理员的 code 不可变更
        if ($role->code !== self::SUPER_ADMIN_CODE && $code !== '') {
            $this->assertUnique('code', $code, $id, '角色代码已存在');
            $role->code = $code;
        }

        $this->transaction(function () use ($role, $data, $operatorId) {
            $dataScope = (int) ($data['data_scope'] ?? SysRole::DATA_SCOPE_ALL);
            $role->fill([
                'name'             => trim((string) ($data['name'] ?? '')),
                'sort'             => (int) ($data['sort']       ?? 0),
                'status'           => (int) ($data['status']     ?? SysRole::STATUS_NORMAL),
                'data_scope'       => $dataScope,
                'data_scope_depts' => $this->normalizeDepts($dataScope, $data['data_scope_depts'] ?? null),
                'remark'           => trim((string) ($data['remark'] ?? '')),
                'updated_by'       => $operatorId,
                'updated_at'       => $this->now(),
            ])->save();

            if (array_key_exists('menu_ids', $data) && is_array($data['menu_ids'])) {
                SysRoleMenu::addRoleMenus($role->id, $data['menu_ids']);
            }
        });

        // 角色（菜单 / 数据范围 / 状态）变更 → 清除关联用户的权限与数据范围缓存
        $this->clearRoleUsersCache($id);

        return $role;
    }

    /**
     * 删除角色。
     */
    public function delete(int $id): void
    {
        /** @var SysRole $role */
        $role = $this->findOrFail($id, [], '角色不存在');

        if ($role->code === self::SUPER_ADMIN_CODE) {
            throw new BusinessException('不能删除超级管理员角色');
        }
        if (SysUserRole::where('role_id', $id)->exists()) {
            throw new BusinessException('该角色已有用户关联，请先解除关联');
        }

        $this->transaction(function () use ($id) {
            SysRole::where('id', $id)->delete();
            SysRoleMenu::where('role_id', $id)->delete();
        });
    }

    /**
     * 分配菜单（角色 → 菜单）。
     *
     * @param int[] $menuIds
     */
    public function assignMenus(int $id, array $menuIds): void
    {
        $this->findOrFail($id, [], '角色不存在');
        SysRoleMenu::addRoleMenus($id, $menuIds);

        // 角色菜单变更 → 清除该角色下所有用户的权限缓存
        $this->clearRoleUsersCache($id);
    }

    /**
     * 设置数据权限范围。
     */
    public function setDataScope(int $id, int $dataScope, mixed $depts, int $operatorId): void
    {
        /** @var SysRole $role */
        $role = $this->findOrFail($id, [], '角色不存在');
        $role->data_scope       = $dataScope;
        $role->data_scope_depts = $this->normalizeDepts($dataScope, $depts);
        $role->updated_by       = $operatorId;
        $role->updated_at       = $this->now();
        $role->save();

        // 数据范围变更 → 清除该角色下所有用户的缓存
        $this->clearRoleUsersCache($id);
    }

    /**
     * 标准化自定义部门：非「自定义数据」一律清空，避免脏数据。
     */
    private function normalizeDepts(int $dataScope, mixed $depts): ?string
    {
        if ($dataScope !== SysRole::DATA_SCOPE_CUSTOM) {
            return null;
        }
        return $this->parseDataScopeDepts($depts);
    }

    /**
     * 清除指定角色下所有用户的权限与数据范围缓存。
     */
    private function clearRoleUsersCache(int $roleId): void
    {
        SysUserRole::where('role_id', $roleId)->pluck('user_id')
            ->each(fn($uid) => clear_permission_cache((int) $uid));
    }

    /**
     * 将 data_scope_depts 标准化为以英文逗号分隔的字符串。
     */
    private function parseDataScopeDepts(mixed $input): ?string
    {
        if (empty($input)) {
            return null;
        }
        return is_array($input) ? implode(',', $input) : (string) $input;
    }
}

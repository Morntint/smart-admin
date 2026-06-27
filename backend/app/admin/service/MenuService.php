<?php

namespace app\admin\service;

use app\common\enum\MenuTypeEnum;
use app\common\exception\BusinessException;
use app\model\SysMenu;
use app\model\SysRoleMenu;
use support\Request;

/**
 * 菜单业务服务
 *
 * 业务规则：
 *  - 菜单分三类：DIR(目录) / MENU(菜单) / BUTTON(按钮)
 *  - 按钮必须设置 permission（权限标识），AuthMiddleware 据此鉴权
 *  - 不能将自身或后代设为父级（防止循环引用）
 *  - 删除菜单需先删除其子菜单
 */
class MenuService extends BaseService
{
    protected string $modelClass = SysMenu::class;

    /**
     * 菜单列表（树形）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function treeList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'type' => $request->get('type', ''),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int,\app\model\SysMenu> $list */
        $list = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
        return $list->toTree();
    }

    /**
     * 前端路由菜单列表（树形，适配 AppRouteRecord 结构）。
     *
     * 按当前用户权限过滤：
     *  - 超级管理员：返回全部正常菜单
     *  - 普通用户：仅返回其角色关联的菜单
     *
     * 隐藏路由（is_visible=0）的扩展授权（BE-N6）：
     *  - 隐藏路由通常是"详情页 / 编辑页 / 发送页"等不在导航里展示但要能跳转的 URL；
     *  - 每个隐藏路由都单独维护 sys_role_menu 关联过于繁琐；
     *  - 这里采用"父级被授权即视为对其隐藏子路由也授权"：
     *    若用户已能访问某菜单（含权限），则它直接挂的 is_visible=0 子菜单一并返回，
     *    让前端 router 能注册并跳转。
     *
     * 查询包含 BUTTON 类型仅用于在 convertToRoute() 中收集 meta.authList，
     * BUTTON 本身不会生成前端路由（convertToRoute 已将其从 children 过滤）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function routeTree(int $userId): array
    {
        $query = SysMenu::where('status', SysMenu::STATUS_NORMAL)
            ->whereIn('type', [SysMenu::TYPE_DIR, SysMenu::TYPE_MENU, SysMenu::TYPE_BUTTON]);

        if (!PermissionService::getInstance()->isSuperAdmin($userId)) {
            $menuIds = PermissionService::getInstance()->getMenuIds($userId);
            if ($menuIds === []) {
                return [];
            }
            // 把隐藏子路由的 ID 扩展进来：只要其父级菜单在用户菜单内，子隐藏路由也允许
            $hiddenChildrenIds = SysMenu::where('status', SysMenu::STATUS_NORMAL)
                ->where('is_visible', 0)
                ->whereIn('type', [SysMenu::TYPE_DIR, SysMenu::TYPE_MENU])
                ->whereIn('parent_id', $menuIds)
                ->pluck('id')
                ->all();

            $effectiveIds = array_values(array_unique(array_merge($menuIds, $hiddenChildrenIds)));
            $query->whereIn('id', $effectiveIds);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int,\app\model\SysMenu> $menuList */
        $menuList = $query->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $menus = $menuList->toTree();

        return array_map([$this, 'convertToRoute'], $menus);
    }

    /**
     * 将菜单模型转换为前端路由结构。
     *
     * @param array<string,mixed> $menu
     * @return array<string,mixed>
     */
    private function convertToRoute(array $menu): array
    {
        $route = [
            'id'        => $menu['id'],
            'path'      => $menu['path'],
            'name'      => $menu['route_name'] ?? $this->generateRouteName($menu['path']),
            'component' => $menu['component'] ?? '',
            'redirect'  => $menu['redirect'] ?? null,
            'meta'      => [
                'title'     => $menu['name'],
                'icon'      => $menu['icon'] ?? '',
                'isHide'    => (int) $menu['is_visible'] === 0,
                'isHideTab' => (int) ($menu['is_hide_tab'] ?? 0) === 1,
                'keepAlive' => (int) ($menu['is_cache'] ?? 0) === 1,
                'isIframe'  => (int) ($menu['is_iframe'] ?? 0) === 1,
                'isFullPage' => (int) ($menu['is_full_page'] ?? 0) === 1,
                'fixedTab'  => (int) ($menu['fixed_tab'] ?? 0) === 1,
                'link'      => (int) ($menu['is_external'] ?? 0) === 1 ? $menu['path'] : null,
                'activePath' => $menu['active_path'] ?? null,
            ],
        ];

        // 移除 null 值
        if ($route['redirect'] === null) {
            unset($route['redirect']);
        }
        foreach ($route['meta'] as $key => $value) {
            if ($value === null) {
                unset($route['meta'][$key]);
            }
        }

        // 收集直接子节点中的按钮权限到 meta.authList（供前端 v-auth / hasAuth 使用）
        if (isset($menu['children']) && is_array($menu['children'])) {
            $authList = [];
            foreach ($menu['children'] as $child) {
                if ((int) $child['type'] === SysMenu::TYPE_BUTTON && !empty($child['permission'])) {
                    $authList[] = [
                        'title'    => $child['name'],
                        'authMark' => $child['permission'],
                    ];
                }
            }
            if ($authList !== []) {
                $route['meta']['authList'] = $authList;
            }
        }

        // 递归处理子菜单（按钮已在上面收集，这里仅保留目录/菜单）
        if (isset($menu['children']) && is_array($menu['children']) && count($menu['children']) > 0) {
            $children = array_filter($menu['children'], function ($child) {
                return (int) $child['type'] !== SysMenu::TYPE_BUTTON;
            });
            if (count($children) > 0) {
                $route['children'] = array_map([$this, 'convertToRoute'], $children);
            }
        }

        return $route;
    }

    /**
     * 从路径生成路由名称（PascalCase）。
     *
     * 例如：/system/user → SystemUser
     */
    private function generateRouteName(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $name = implode('', array_map('ucfirst', $segments));
        return $name === '' ? 'Root' : $name;
    }

    /**
     * 菜单列表（平铺）。
     */
    public function flatList(Request $request): iterable
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'type' => $request->get('type', ''),
        ]);
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
    }

    /**
     * 父级菜单选项（排除自身与所有后代）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function parentOptions(int $excludeId = 0): array
    {
        $query = $this->newQuery()
            ->whereIn('type', [MenuTypeEnum::DIR->value, MenuTypeEnum::MENU->value]);

        if ($excludeId > 0) {
            $menu = SysMenu::find($excludeId);
            if ($menu) {
                $query->whereNotIn('id', array_merge([$excludeId], $menu->getDescendantIds()));
            }
        }

        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')
                     ->get(['id', 'parent_id', 'name', 'type'])->toArray();
    }

    /**
     * 按钮权限列表（用于角色分配权限选择）。
     */
    public function buttonPermissions(): iterable
    {
        return SysMenu::where('type', MenuTypeEnum::BUTTON->value)
                      ->where('status', SysMenu::STATUS_NORMAL)
                      ->whereNotNull('permission')
                      ->where('permission', '<>', '')
                      ->orderBy('sort', 'asc')
                      ->get(['id', 'name', 'permission', 'parent_id']);
    }

    /**
     * 菜单详情。
     */
    public function detail(int $id): SysMenu
    {
        /** @var SysMenu $menu */
        $menu = $this->findOrFail($id, [], '菜单不存在');
        if ($menu->parent_id > 0) {
            $menu->parent_name = SysMenu::find($menu->parent_id)?->name;
        }
        return $menu;
    }

    /**
     * 创建菜单。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysMenu
    {
        $type       = (int) ($data['type']      ?? MenuTypeEnum::MENU->value);
        $parentId   = (int) ($data['parent_id'] ?? 0);
        $permission = trim((string) ($data['permission'] ?? ''));

        if ($type === MenuTypeEnum::BUTTON->value && $permission === '') {
            throw new BusinessException('按钮权限标识不能为空');
        }
        $this->assertParentValid($parentId);

        return SysMenu::create($this->buildMenuPayload($data, $type, $parentId, $permission, $operatorId, isUpdate: false));
    }

    /**
     * 更新菜单。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysMenu
    {
        /** @var SysMenu $menu */
        $menu     = $this->findOrFail($id, [], '菜单不存在');
        $parentId = (int) ($data['parent_id'] ?? 0);

        if ($parentId === $id) {
            throw new BusinessException('不能将自己设为父级菜单');
        }
        if ($parentId > 0 && in_array($parentId, $menu->getDescendantIds(), true)) {
            throw new BusinessException('不能将自己的子级设为父级菜单');
        }

        $type       = (int) ($data['type'] ?? MenuTypeEnum::MENU->value);
        $permission = trim((string) ($data['permission'] ?? ''));

        $menu->fill($this->buildMenuPayload($data, $type, $parentId, $permission, $operatorId, isUpdate: true))->save();
        return $menu;
    }

    /**
     * 删除菜单。
     */
    public function delete(int $id): void
    {
        $this->findOrFail($id, [], '菜单不存在');
        if (SysMenu::where('parent_id', $id)->exists()) {
            throw new BusinessException('请先删除子菜单');
        }

        $this->transaction(function () use ($id) {
            SysMenu::where('id', $id)->delete();
            SysRoleMenu::where('menu_id', $id)->delete();
        });
    }

    /**
     * 批量更新菜单状态。
     *
     * @param int[] $ids
     */
    public function batchUpdateStatus(array $ids, int $status, int $operatorId): int
    {
        if ($ids === []) {
            return 0;
        }
        return SysMenu::whereIn('id', $ids)->update([
            'status'     => $status,
            'updated_by' => $operatorId,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * 校验父级菜单合法性（必须存在且不能是按钮）。
     */
    private function assertParentValid(int $parentId): void
    {
        if ($parentId <= 0) {
            return;
        }
        $parent = SysMenu::find($parentId);
        if (!$parent) {
            throw new BusinessException('父级菜单不存在');
        }
        if ((int) $parent->type === MenuTypeEnum::BUTTON->value) {
            throw new BusinessException('按钮不能作为父级菜单');
        }
    }

    /**
     * 构建菜单字段（创建/更新共用）。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function buildMenuPayload(
        array $data,
        int $type,
        int $parentId,
        string $permission,
        int $operatorId,
        bool $isUpdate
    ): array {
        $payload = [
            'parent_id'    => $parentId,
            'name'         => trim((string) ($data['name']        ?? '')),
            'route_name'   => trim((string) ($data['route_name']  ?? '')) ?: null,
            'icon'         => trim((string) ($data['icon']        ?? '')),
            'path'         => trim((string) ($data['path']        ?? '')),
            'component'    => trim((string) ($data['component']   ?? '')),
            'redirect'     => trim((string) ($data['redirect']    ?? '')) ?: null,
            'type'         => $type,
            'is_external'  => (int) ($data['is_external']  ?? 0),
            'is_cache'     => (int) ($data['is_cache']     ?? 0),
            'is_visible'   => (int) ($data['is_visible']   ?? 1),
            'is_hide_tab'  => (int) ($data['is_hide_tab']  ?? 0),
            'is_iframe'    => (int) ($data['is_iframe']    ?? 0),
            'is_full_page' => (int) ($data['is_full_page'] ?? 0),
            'fixed_tab'    => (int) ($data['fixed_tab']    ?? 0),
            'active_path'  => trim((string) ($data['active_path'] ?? '')) ?: null,
            'sort'         => (int) ($data['sort']         ?? 0),
            'permission'   => $permission !== '' ? $permission : null,
            'status'       => (int) ($data['status']       ?? SysMenu::STATUS_NORMAL),
            'remark'       => trim((string) ($data['remark']      ?? '')),
        ];

        if ($isUpdate) {
            $payload['updated_by'] = $operatorId;
            $payload['updated_at'] = $this->now();
        } else {
            $payload['created_by'] = $operatorId;
            $payload['created_at'] = $this->now();
        }

        return $payload;
    }
}

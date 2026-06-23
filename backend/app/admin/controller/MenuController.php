<?php

namespace app\admin\controller;

use app\admin\service\MenuService;
use app\admin\validation\MenuValidator;
use app\common\attribute\RequiresPermission;
use app\model\SysMenu;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 菜单管理（后台）
 *
 * 路由前缀：/admin/menu
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class MenuController extends BaseController
{
    private MenuService $menuService;

    public function __construct()
    {
        parent::__construct();
        $this->menuService = MenuService::getInstance();
    }

    /**
     * 菜单列表（树形）
     */
    #[Get('/menu')]
    #[Validate(rules: ['type' => 'string|in:1,2,3'])]
    public function index(Request $request): Response
    {
        return $this->success($this->menuService->treeList($request));
    }

    /**
     * 前端路由菜单列表（树形结构，适配 AppRouteRecord）
     * 用于前端动态路由加载
     */
    #[Get('/menu/routes')]
    public function routes(Request $request): Response
    {
        return $this->success($this->menuService->routeTree($this->userId));
    }

    /**
     * 菜单列表（平铺）
     */
    #[Get('/menu/list')]
    public function list(Request $request): Response
    {
        return $this->success($this->menuService->flatList($request));
    }

    /**
     * 父级菜单选项（选择父级时使用，可排除当前节点及其后代）
     */
    #[Get('/menu/options')]
    public function options(Request $request): Response
    {
        return $this->success(
            $this->menuService->parentOptions((int) $request->get('exclude_id', 0))
        );
    }

    /**
     * 按钮权限列表
     */
    #[Get('/menu/permissions')]
    public function permissions(Request $request): Response
    {
        return $this->success($this->menuService->buttonPermissions());
    }

    /**
     * 导出菜单（树形结构）
     */
    #[Get('/menu/export')]
    public function export(Request $request): Response
    {
        return $this->success(
            SysMenu::orderBy('sort', 'asc')->orderBy('id', 'asc')->get()->toTree()
        );
    }

    /**
     * 菜单详情
     */
    #[Get('/menu/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->menuService->detail($id));
    }

    /**
     * 创建菜单
     */
    #[Post('/menu')]
    #[RequiresPermission('system:menu:add')]
    #[Validate(validator: MenuValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $menu = $this->menuService->create($request->post(), $this->userId);
        return $this->success(['id' => $menu->id], '创建成功');
    }

    /**
     * 更新菜单
     */
    #[Put('/menu/{id}')]
    #[RequiresPermission('system:menu:edit')]
    #[Validate(validator: MenuValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->menuService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除菜单
     */
    #[Delete('/menu/{id}')]
    #[RequiresPermission('system:menu:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->menuService->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 批量更新菜单状态
     */
    #[Post('/menu/batch-status')]
    #[RequiresPermission('system:menu:edit')]
    #[Validate(validator: MenuValidator::class, scene: 'batchStatus')]
    public function batchStatus(Request $request): Response
    {
        $this->menuService->batchUpdateStatus(
            (array) $request->post('ids', []),
            (int) $request->post('status', SysMenu::STATUS_NORMAL),
            $this->userId
        );
        return $this->success(msg: '操作成功');
    }
}

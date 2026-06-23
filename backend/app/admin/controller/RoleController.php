<?php

namespace app\admin\controller;

use app\admin\service\RoleService;
use app\admin\validation\RoleValidator;
use app\common\attribute\RequiresPermission;
use app\model\SysRole;
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
 * 角色管理（后台）
 *
 * 路由前缀：/admin/role
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class RoleController extends BaseController
{
    private RoleService $roleService;

    public function __construct()
    {
        parent::__construct();
        $this->roleService = RoleService::getInstance();
    }

    /**
     * 角色分页列表
     */
    #[Get('/role')]
    #[Validate(rules: [
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'status'  => 'string|in:0,1',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->roleService->pageList($request));
    }

    /**
     * 所有启用角色（用于下拉框）
     */
    #[Get('/role/all')]
    public function all(Request $request): Response
    {
        return $this->success(
            SysRole::where('status', SysRole::STATUS_NORMAL)
                   ->orderBy('sort', 'asc')
                   ->get(['id', 'name', 'code'])
        );
    }

    /**
     * 角色详情
     */
    #[Get('/role/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->roleService->detail($id));
    }

    /**
     * 角色拥有的菜单 ID 列表
     */
    #[Get('/role/{id}/menus')]
    public function menus(Request $request, int $id): Response
    {
        return $this->success($this->roleService->detail($id)->menu_ids);
    }

    /**
     * 角色数据范围
     */
    #[Get('/role/{id}/data-scope')]
    public function dataScope(Request $request, int $id): Response
    {
        $role = $this->roleService->detail($id);
        return $this->success([
            'data_scope'       => $role->data_scope,
            'data_scope_depts' => $role->data_scope_depts ? explode(',', $role->data_scope_depts) : [],
        ]);
    }

    /**
     * 创建角色
     */
    #[Post('/role')]
    #[RequiresPermission('system:role:add')]
    #[Validate(validator: RoleValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $role = $this->roleService->create($request->post(), $this->userId);
        return $this->success(['id' => $role->id], '创建成功');
    }

    /**
     * 更新角色
     */
    #[Put('/role/{id}')]
    #[RequiresPermission('system:role:edit')]
    #[Validate(validator: RoleValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->roleService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除角色
     */
    #[Delete('/role/{id}')]
    #[RequiresPermission('system:role:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->roleService->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 分配菜单权限
     */
    #[Post('/role/{id}/menus')]
    #[RequiresPermission('system:role:assign')]
    #[Validate(validator: RoleValidator::class, scene: 'assignMenus')]
    public function assignMenus(Request $request, int $id): Response
    {
        $this->roleService->assignMenus($id, (array) $request->post('menu_ids', []));
        return $this->success(msg: '分配成功');
    }

    /**
     * 修改数据范围
     */
    #[Put('/role/{id}/data-scope')]
    #[RequiresPermission('system:role:assign')]
    #[Validate(validator: RoleValidator::class, scene: 'dataScope')]
    public function setDataScope(Request $request, int $id): Response
    {
        $this->roleService->setDataScope(
            $id,
            (int) $request->post('data_scope', SysRole::DATA_SCOPE_ALL),
            $request->post('data_scope_depts', []),
            $this->userId
        );
        return $this->success(msg: '设置成功');
    }
}

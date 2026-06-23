<?php

namespace app\admin\controller;

use app\admin\service\DeptService;
use app\admin\validation\DeptValidator;
use app\common\attribute\RequiresPermission;
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
 * 部门管理（后台）
 *
 * 路由前缀：/admin/dept
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class DeptController extends BaseController
{
    private DeptService $deptService;

    public function __construct()
    {
        parent::__construct();
        $this->deptService = DeptService::getInstance();
    }

    /**
     * 部门列表（树形）
     */
    #[Get('/dept')]
    #[Validate(rules: [
        'keyword' => 'string|max:100',
        'status'  => 'string|in:0,1',
    ])]
    public function index(Request $request): Response
    {
        return $this->success($this->deptService->treeList($request));
    }

    /**
     * 部门列表（平铺）
     */
    #[Get('/dept/list')]
    public function list(Request $request): Response
    {
        return $this->success($this->deptService->flatList());
    }

    /**
     * 父级部门选项（选择父级时使用，可排除当前节点及其后代）
     */
    #[Get('/dept/options')]
    public function options(Request $request): Response
    {
        return $this->success(
            $this->deptService->parentOptions((int) $request->get('exclude_id', 0))
        );
    }

    /**
     * 部门用户树（用于选人组件）
     */
    #[Get('/dept/user-tree')]
    public function userTree(Request $request): Response
    {
        return $this->success($this->deptService->userTree());
    }

    /**
     * 部门详情
     */
    #[Get('/dept/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->deptService->detail($id));
    }

    /**
     * 创建部门
     */
    #[Post('/dept')]
    #[RequiresPermission('system:dept:add')]
    #[Validate(validator: DeptValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $dept = $this->deptService->create($request->post(), $this->userId);
        return $this->success(['id' => $dept->id], '创建成功');
    }

    /**
     * 更新部门
     */
    #[Put('/dept/{id}')]
    #[RequiresPermission('system:dept:edit')]
    #[Validate(validator: DeptValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->deptService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除部门
     */
    #[Delete('/dept/{id}')]
    #[RequiresPermission('system:dept:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->deptService->delete($id);
        return $this->success(msg: '删除成功');
    }
}

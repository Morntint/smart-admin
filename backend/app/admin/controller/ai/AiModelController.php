<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiModelService;
use app\common\attribute\RequiresPermission;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;

/**
 * AI 模型管理
 * 路由前缀：/admin/ai/model
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiModelController extends BaseController
{
    private AiModelService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiModelService::getInstance();
    }

    /**
     * 模型分页列表
     */
    #[Get('/ai/model')]
    #[RequiresPermission('ai:model:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request));
    }

    /**
     * 启用的模型列表（下拉选项）
     */
    #[Get('/ai/model/enabled')]
    public function enabledList(): Response
    {
        return $this->success($this->service->enabledList());
    }

    /**
     * 模型详情
     */
    #[Get('/ai/model/{id}')]
    #[RequiresPermission('ai:model:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->service->detail($id));
    }

    /**
     * 创建模型
     */
    #[Post('/ai/model')]
    #[RequiresPermission('ai:model:create')]
    public function store(Request $request): Response
    {
        $model = $this->service->create($request->post(), $this->userId);
        return $this->success(['id' => $model->id], '创建成功');
    }

    /**
     * 更新模型
     */
    #[Put('/ai/model/{id}')]
    #[RequiresPermission('ai:model:update')]
    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除模型
     */
    #[Delete('/ai/model/{id}')]
    #[RequiresPermission('ai:model:delete')]
    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($id);
        return $this->success(msg: '删除成功');
    }
}

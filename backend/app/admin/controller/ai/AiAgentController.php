<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiAgentService;
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
 * AI Agent 管理
 * 路由前缀：/admin/ai/agent
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiAgentController extends BaseController
{
    private AiAgentService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiAgentService::getInstance();
    }

    /**
     * Agent 分页列表
     */
    #[Get('/ai/agent')]
    #[RequiresPermission('ai:agent:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request));
    }

    /**
     * 公开 Agent 列表（对话工作台用）
     */
    #[Get('/ai/agent/public')]
    public function publicList(Request $request): Response
    {
        return $this->pageResponse($this->service->publicList($request));
    }

    /**
     * Agent 详情（含工具）
     */
    #[Get('/ai/agent/{id}')]
    #[RequiresPermission('ai:agent:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->service->detail($id));
    }

    /**
     * 创建 Agent
     */
    #[Post('/ai/agent')]
    #[RequiresPermission('ai:agent:create')]
    public function store(Request $request): Response
    {
        $agent = $this->service->create($request->post(), $this->userId);
        return $this->success(['id' => $agent->id], '创建成功');
    }

    /**
     * 更新 Agent
     */
    #[Put('/ai/agent/{id}')]
    #[RequiresPermission('ai:agent:update')]
    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除 Agent
     */
    #[Delete('/ai/agent/{id}')]
    #[RequiresPermission('ai:agent:delete')]
    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($id);
        return $this->success(msg: '删除成功');
    }
}

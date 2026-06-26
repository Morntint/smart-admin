<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiAgentToolService;
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
 * AI Agent 工具库管理
 * 路由前缀：/admin/ai/agent-tool
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiAgentToolController extends BaseController
{
    private AiAgentToolService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiAgentToolService::getInstance();
    }

    /**
     * 工具分页列表
     */
    #[Get('/ai/agent-tool')]
    #[RequiresPermission('ai:tool:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request));
    }

    /**
     * 获取所有可用工具（Agent 绑定选择用）
     */
    #[Get('/ai/agent-tool/available')]
    #[RequiresPermission('ai:tool:list')]
    public function available(): Response
    {
        return $this->success($this->service->availableTools());
    }

    /**
     * 工具详情
     */
    #[Get('/ai/agent-tool/{id}')]
    #[RequiresPermission('ai:tool:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->service->detail($id));
    }

    /**
     * 创建工具
     */
    #[Post('/ai/agent-tool')]
    #[RequiresPermission('ai:tool:create')]
    public function store(Request $request): Response
    {
        $tool = $this->service->create($request->post(), $this->userId);
        return $this->success(['id' => $tool->id], '创建成功');
    }

    /**
     * 更新工具
     */
    #[Put('/ai/agent-tool/{id}')]
    #[RequiresPermission('ai:tool:update')]
    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除工具
     */
    #[Delete('/ai/agent-tool/{id}')]
    #[RequiresPermission('ai:tool:delete')]
    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($id);
        return $this->success(msg: '删除成功');
    }
}

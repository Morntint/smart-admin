<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiPromptService;
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
 * AI 提示词管理
 * 路由前缀：/admin/ai/prompt
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiPromptController extends BaseController
{
    private AiPromptService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiPromptService::getInstance();
    }

    /**
     * 提示词分页列表
     */
    #[Get('/ai/prompt')]
    #[RequiresPermission('ai:prompt:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request));
    }

    /**
     * 按分类分组列表（下拉用）
     */
    #[Get('/ai/prompt/categories')]
    public function categories(): Response
    {
        return $this->success($this->service->listByCategory());
    }

    /**
     * 提示词详情
     */
    #[Get('/ai/prompt/{id}')]
    #[RequiresPermission('ai:prompt:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->service->detail($id));
    }

    /**
     * 创建提示词
     */
    #[Post('/ai/prompt')]
    #[RequiresPermission('ai:prompt:create')]
    public function store(Request $request): Response
    {
        $template = $this->service->create($request->post(), $this->userId);
        return $this->success(['id' => $template->id], '创建成功');
    }

    /**
     * 更新提示词
     */
    #[Put('/ai/prompt/{id}')]
    #[RequiresPermission('ai:prompt:update')]
    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除提示词
     */
    #[Delete('/ai/prompt/{id}')]
    #[RequiresPermission('ai:prompt:delete')]
    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 按编码获取并渲染提示词
     */
    #[Post('/ai/prompt/render')]
    public function render(Request $request): Response
    {
        $code      = (string) $request->post('code', '');
        $variables = $request->post('variables', []);
        return $this->success(['content' => $this->service->renderByCode($code, $variables)]);
    }
}

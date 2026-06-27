<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiKnowledgeService;
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
 * AI 知识库管理
 * 路由前缀：/admin/ai/knowledge
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiKnowledgeController extends BaseController
{
    private AiKnowledgeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiKnowledgeService::getInstance();
    }

    /**
     * 知识库分页列表
     */
    #[Get('/ai/knowledge')]
    #[RequiresPermission('ai:knowledge:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request, $this->userId));
    }

    /**
     * 知识库详情
     */
    #[Get('/ai/knowledge/{id}')]
    #[RequiresPermission('ai:knowledge:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->service->detail($id, $this->userId));
    }

    /**
     * 创建知识库
     */
    #[Post('/ai/knowledge')]
    #[RequiresPermission('ai:knowledge:create')]
    public function store(Request $request): Response
    {
        $kb = $this->service->create($request->post(), $this->userId);
        return $this->success(['id' => $kb->id], '创建成功');
    }

    /**
     * 更新知识库
     */
    #[Put('/ai/knowledge/{id}')]
    #[RequiresPermission('ai:knowledge:update')]
    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除知识库
     */
    #[Delete('/ai/knowledge/{id}')]
    #[RequiresPermission('ai:knowledge:delete')]
    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    // === 文档管理 ===

    /**
     * 知识库文档列表
     */
    #[Get('/ai/knowledge/{id}/documents')]
    #[RequiresPermission('ai:knowledge:list')]
    public function documents(Request $request, int $id): Response
    {
        return $this->pageResponse($this->service->documentPageList($id, $request, $this->userId));
    }

    /**
     * 上传文档
     */
    #[Post('/ai/knowledge/{id}/documents')]
    #[RequiresPermission('ai:knowledge:upload')]
    public function uploadDocument(Request $request, int $id): Response
    {
        // 支持文件上传和文本内容两种方式
        $file = $request->file('file');
        $data = $request->post();

        if ($file) {
            $doc = $this->service->uploadFileDocument($id, $file, $data, $this->userId);
        } else {
            $doc = $this->service->uploadDocument($id, $data, $this->userId);
        }

        return $this->success(['id' => $doc->id], '上传成功，正在处理中');
    }

    /**
     * 删除文档
     */
    #[Delete('/ai/knowledge/document/{id}')]
    #[RequiresPermission('ai:knowledge:delete')]
    public function destroyDocument(Request $request, int $id): Response
    {
        $this->service->deleteDocument($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 重新处理文档
     */
    #[Post('/ai/knowledge/document/{id}/reprocess')]
    #[RequiresPermission('ai:knowledge:upload')]
    public function reprocessDocument(Request $request, int $id): Response
    {
        $doc = \app\model\AiKnowledgeDocument::findOrFail($id);
        $kb  = \app\model\AiKnowledgeBase::findOrFail($doc->kb_id);
        // 对象级权限：仅 KB 创建者 / 超管可触发重处理
        $this->service->assertKbAccessPublic($kb, $this->userId);
        $this->service->processDocument($doc, $kb);
        return $this->success(msg: '处理完成');
    }
}

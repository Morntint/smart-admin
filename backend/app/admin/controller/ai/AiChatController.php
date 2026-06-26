<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiConversationService;
use app\model\AiAgent;
use app\model\AiTool;
use support\Log;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Put;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;

/**
 * AI 对话工作台
 * 路由前缀：/admin/ai/chat
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiChatController extends BaseController
{
    private AiConversationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiConversationService::getInstance();
    }

    /**
     * 对话会话列表
     */
    #[Get('/ai/chat/conversations')]
    public function conversations(Request $request): Response
    {
        return $this->pageResponse($this->service->conversationList($request, $this->userId));
    }

    /**
     * 创建新会话
     */
    #[Post('/ai/chat/conversations')]
    public function createConversation(Request $request): Response
    {
        $agentId = (int) $request->post('agent_id', 0);
        $toolIds = $request->post('tool_ids');

        // 解析 tool_ids：null 表示使用 Agent 默认工具，[] 表示不使用工具，[1,2] 表示使用指定工具
        $selectedToolIds = null;
        if ($toolIds !== null) {
            $selectedToolIds = is_array($toolIds) ? $toolIds : [];
        }

        $conv = $this->service->createConversation($agentId, $this->userId, $selectedToolIds);
        return $this->success($conv->toArray(), '创建成功');
    }

    /**
     * 删除会话
     */
    #[Delete('/ai/chat/conversations/{id}')]
    public function deleteConversation(Request $request, int $id): Response
    {
        $this->service->deleteConversation($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 获取会话消息历史
     */
    #[Get('/ai/chat/conversations/{id}/messages')]
    public function messages(Request $request, int $id): Response
    {
        return $this->success($this->service->getMessages($id, $this->userId));
    }

    /**
     * 更新会话的工具选择
     */
    #[Put('/ai/chat/conversations/{id}/tools')]
    public function updateTools(Request $request, int $id): Response
    {
        $toolIds = $request->post('tool_ids');

        // 解析 tool_ids：null 表示使用 Agent 默认工具，[] 表示不使用工具，[1,2] 表示使用指定工具
        $selectedToolIds = null;
        if ($toolIds !== null) {
            $selectedToolIds = is_array($toolIds) ? $toolIds : [];
        }

        $conv = \app\model\AiConversation::where('id', $id)
            ->where('user_id', $this->userId)
            ->first();

        if (!$conv) {
            return $this->error('会话不存在');
        }

        $conv->selected_tool_ids = $selectedToolIds;
        $conv->save();

        return $this->success($conv->toArray(), '工具选择已更新');
    }

    /**
     * 获取 Agent 可用的工具列表（供用户选择）
     */
    #[Get('/ai/chat/agents/{id}/tools')]
    public function getAgentTools(Request $request, int $id): Response
    {
        /** @var AiAgent|null $agent */
        $agent = \app\model\AiAgent::with(['tools'])
            ->where('id', $id)
            ->where('status', 1)
            ->first();

        if (!$agent) {
            return $this->error('Agent 不存在或已禁用');
        }

        $tools = $agent->tools->map(function ($tool) {
            /** @var AiTool $tool */
            return [
                'id'          => $tool->id,
                'name'        => $tool->name,
                'code'        => $tool->code,
                'description' => $tool->description,
                'icon'        => $tool->icon ?? null,
            ];
        })->toArray();

        return $this->success($tools);
    }

    /**
     * 发送消息（非流式）
     */
    #[Post('/ai/chat/send')]
    public function send(Request $request): Response
    {
        $params = $request->post();
        // 解析 tool_ids 参数
        $toolIds = $params['tool_ids'] ?? null;
        if ($toolIds !== null) {
            $params['tool_ids'] = is_array($toolIds) ? $toolIds : [];
        }

        $result = $this->service->sendMessage($params, $this->userId);
        return $this->success($result);
    }

    /**
     * SSE 流式发送消息
     *
     * 注意：webman 下流式输出需要先收集所有内容再一次性返回
     * 真正的逐块流式需要配合 Connection 直接操作 TCP 连接
     */
    #[Post('/ai/chat/stream')]
    public function sendStream(Request $request): Response
    {
        $params = $request->post();
        // 解析 tool_ids 参数
        $toolIds = $params['tool_ids'] ?? null;
        if ($toolIds !== null) {
            $params['tool_ids'] = is_array($toolIds) ? $toolIds : [];
        }

        try {
            $stream = $this->service->sendMessageStream($params, $this->userId);

            $body = '';
            foreach ($stream as $chunk) {
                $body .= "data: {$chunk}\n\n";
            }

            return response($body)
                ->withHeader('Content-Type', 'text/event-stream')
                ->withHeader('Cache-Control', 'no-cache')
                ->withHeader('Connection', 'keep-alive')
                ->withHeader('X-Accel-Buffering', 'no')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Throwable $e) {
            // 如果异常已经在 service 层处理过，这里直接抛出
            // 否则记录错误日志（service 层应该已经记录了用量）
            throw $e;
        }
    }
}

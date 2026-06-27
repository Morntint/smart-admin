<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiAgentService;
use app\admin\service\ai\AiConversationService;
use app\common\attribute\RateLimit;
use app\common\attribute\RequiresPermission;
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
    private AiAgentService $agentService;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiConversationService::getInstance();
        $this->agentService = AiAgentService::getInstance();
    }

    /**
     * 对话会话列表
     */
    #[Get('/ai/chat/conversations')]
    #[RequiresPermission('ai:chat:use')]
    public function conversations(Request $request): Response
    {
        return $this->pageResponse($this->service->conversationList($request, $this->userId));
    }

    /**
     * 创建新会话
     */
    #[Post('/ai/chat/conversations')]
    #[RequiresPermission('ai:chat:use')]
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
    #[RequiresPermission('ai:chat:use')]
    public function deleteConversation(Request $request, int $id): Response
    {
        $this->service->deleteConversation($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 获取会话消息历史
     */
    #[Get('/ai/chat/conversations/{id}/messages')]
    #[RequiresPermission('ai:chat:use')]
    public function messages(Request $request, int $id): Response
    {
        return $this->success($this->service->getMessages($id, $this->userId));
    }

    /**
     * 更新会话的工具选择
     */
    #[Put('/ai/chat/conversations/{id}/tools')]
    #[RequiresPermission('ai:chat:use')]
    public function updateTools(Request $request, int $id): Response
    {
        $toolIds = $request->post('tool_ids');

        // 解析 tool_ids：null 表示使用 Agent 默认工具，[] 表示不使用工具，[1,2] 表示使用指定工具
        $selectedToolIds = null;
        if ($toolIds !== null) {
            $selectedToolIds = is_array($toolIds)
                ? array_values(array_unique(array_map('intval', $toolIds)))
                : [];
        }

        $conv = \app\model\AiConversation::where('id', $id)
            ->where('user_id', $this->userId)
            ->first();

        if (!$conv) {
            return $this->error('会话不存在');
        }

        // 校验所选工具必须属于该 Agent 绑定的工具集合（防止绕过 Agent 限制）
        if (!empty($selectedToolIds)) {
            $allowedToolIds = $this->agentService->getAgentToolIds((int) $conv->agent_id);
            $invalid = array_diff($selectedToolIds, $allowedToolIds);
            if ($invalid !== []) {
                return $this->error('工具 ' . implode(',', $invalid) . ' 不属于该 Agent');
            }
        }

        $conv->selected_tool_ids = $selectedToolIds;
        $conv->save();

        return $this->success($conv->toArray(), '工具选择已更新');
    }

    /**
     * 获取 Agent 可用的工具列表（供用户选择）
     */
    #[Get('/ai/chat/agents/{id}/tools')]
    #[RequiresPermission('ai:agent:list')]
    public function getAgentTools(Request $request, int $id): Response
    {
        return $this->success($this->agentService->getAgentTools($id));
    }

    /**
     * 发送消息（非流式）
     */
    #[Post('/ai/chat/send')]
    #[RequiresPermission('ai:chat:use')]
    #[RateLimit(limit: 30, window: 60, by: 'user', key: 'ai_chat_send')]
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
     * 直接通过 TcpConnection 以 HTTP/1.1 chunked transfer-encoding 推送字节，
     * 让前端 EventSource 拿到真实分片，而不是等 service 跑完后一次性回包。
     *
     * 注意：这里不返回普通 Response，最终用 connection->close('') 关闭连接；
     * webman/Workerman 看到 closed=true 后不会再发任何东西。
     */
    #[Post('/ai/chat/stream')]
    #[RequiresPermission('ai:chat:use')]
    #[RateLimit(limit: 30, window: 60, by: 'user', key: 'ai_chat_stream')]
    public function sendStream(Request $request): Response
    {
        $params = $request->post();
        // 解析 tool_ids 参数
        $toolIds = $params['tool_ids'] ?? null;
        if ($toolIds !== null) {
            $params['tool_ids'] = is_array($toolIds) ? $toolIds : [];
        }

        $connection = $request->connection ?? null;
        if (!$connection) {
            // 极少数情况下连接对象拿不到（如单测），退化为旧聚合行为
            return $this->sendStreamFallback($params);
        }

        // 1. 先发出 SSE 头部（chunked 编码），让浏览器立刻进入流式接收
        $header = "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/event-stream; charset=utf-8\r\n"
            . "Cache-Control: no-cache, no-transform\r\n"
            . "Connection: keep-alive\r\n"
            . "X-Accel-Buffering: no\r\n"
            . "Transfer-Encoding: chunked\r\n\r\n";
        $connection->send($header, true);

        $sendChunk = function (string $payload) use ($connection): void {
            $line = "data: {$payload}\n\n";
            // chunked 编码：<hex-len>\r\n<data>\r\n
            $connection->send(dechex(strlen($line)) . "\r\n" . $line . "\r\n", true);
        };

        try {
            $stream = $this->service->sendMessageStream($params, $this->userId);
            foreach ($stream as $chunk) {
                $sendChunk($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('SSE 流式异常', ['err' => $e->getMessage()]);
            // 把错误也作为最后一条事件发出去，前端再统一处理
            $sendChunk(json_encode([
                'type' => 'error',
                'data' => ['message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
        } finally {
            // chunked 终止符 + 关闭连接
            $connection->send("0\r\n\r\n", true);
            $connection->close('');
        }

        // 返回一个空响应；webman 会发现连接已被我们手动 close（closed=true），不再发送
        return response('');
    }

    /**
     * 极少数场景（如脱离 webman 的连接对象）退化路径：仍按旧逻辑聚合再回包。
     */
    private function sendStreamFallback(array $params): Response
    {
        $stream = $this->service->sendMessageStream($params, $this->userId);
        $body = '';
        foreach ($stream as $chunk) {
            $body .= "data: {$chunk}\n\n";
        }
        return response($body)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no');
    }
}

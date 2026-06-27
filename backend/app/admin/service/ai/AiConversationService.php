<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\admin\service\ai\tool\NL2SqlPrompt;
use app\admin\service\ai\tool\ToolGovernance;
use app\common\exception\BusinessException;
use app\model\AiAgent;
use app\model\AiConversation;
use app\model\AiConversationMessage;
use app\model\AiUsageRecord;
use support\Request;
use support\Response;
use support\Log;

/**
 * AI 对话服务
 *
 * 核心职责：
 *  - 对话会话管理（创建/列表/删除）
 *  - 聊天消息发送（含 RAG 知识增强 + 工具调用）
 *  - 流式(SSE)/非流式调用
 *  - 用量记录
 */
class AiConversationService extends BaseService
{
    protected string $modelClass = AiConversation::class;

    /** @var int 最大工具调用次数，防止无限循环 */
    private const MAX_TOOL_CALLS = 5;

    /** @var int 工具结果回灌 LLM context 时的最大字符长度，防止表膨胀 + Token 超限 */
    private const MAX_TOOL_RESULT_CHARS = 16384;

    /**
     * 对话列表
     */
    public function conversationList(Request $request, int $userId): array
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = AiConversation::query()
            ->with(['agent:id,name,icon'])
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc');

        return $this->paginate($query, $request, 20, 50);
    }

    /**
     * 创建新会话
     */
    public function createConversation(int $agentId, int $userId, ?array $selectedToolIds = null): AiConversation
    {
        /** @var AiAgent $agent */
        $agent = AiAgent::findOrFail($agentId);
        if ($agent->status !== 1) {
            throw new BusinessException('Agent 已禁用');
        }
        // 仅公开 Agent 或创建者本人可创建会话（避免普通用户用任意 agent_id 烧 Token）
        if ((int) ($agent->is_public ?? 0) !== 1 && (int) ($agent->created_by ?? 0) !== $userId) {
            throw new BusinessException('无权使用该 Agent');
        }

        return AiConversation::createData([
            'user_id'           => $userId,
            'agent_id'          => $agentId,
            'selected_tool_ids' => $selectedToolIds,
            'title'             => $agent->welcome_message ? mb_substr($agent->welcome_message, 0, 50) : '新对话',
        ]);
    }

    /**
     * 删除会话
     */
    public function deleteConversation(int $id, int $userId): void
    {
        $conv = AiConversation::where('id', $id)->where('user_id', $userId)->first();
        if (!$conv) {
            throw new BusinessException('会话不存在');
        }
        $this->transaction(function () use ($id, $conv) {
            AiConversationMessage::where('conversation_id', $id)->delete();
            $conv->delete();
        });
    }

    /**
     * 获取会话消息历史
     */
    public function getMessages(int $conversationId, int $userId): array
    {
        $conv = AiConversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if (!$conv) {
            throw new BusinessException('会话不存在');
        }

        return AiConversationMessage::where('conversation_id', $conversationId)
            ->orderBy('round_index')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    /**
     * 发送消息（非流式）
     *
     * @return array{message:array, usage:array}
     */
    public function sendMessage(array $params, int $userId): array
    {
        $conversationId = $params['conversation_id'] ?? null;
        $content        = $params['content'] ?? '';
        $agentId        = $params['agent_id'] ?? null;

        // 获取或验证会话
        $conv = $this->resolveConversation($conversationId, $agentId, $userId);

        // 获取 Agent 配置（含工具）
        $agentConfig = AiAgentService::getInstance()->getAgentConfig($conv->agent_id);

        // 构建消息上下文
        $messages = $this->buildMessages($conv->id, $agentConfig, $content, $conv->max_history_rounds ?? 10);

        // 获取要使用的工具：本次消息发送时用户选择的工具优先，否则使用 Agent 绑定的所有工具
        $requestedToolIds = $params['tool_ids'] ?? null;
        $toolsToUse = $this->getToolsToUse($agentConfig['tools'] ?? [], $requestedToolIds);

        // 准备工具（转换为 OpenAI 格式）
        $tools = $this->formatTools($toolsToUse);
        $options = [
            'temperature' => $agentConfig['temperature'] ?? null,
            'max_tokens'  => $agentConfig['max_tokens'] ?? null,
        ];
        if (!empty($tools)) {
            $options['tools'] = $tools;
            $options['tool_choice'] = 'auto';
        }

        Log::debug('AI 对话配置', [
            'agent_id' => $conv->agent_id,
            'agent_name' => $agentConfig['name'] ?? 'unknown',
            'tools_count' => count($tools),
            'tools' => array_map(function($t) { return $t['function']['name']; }, $tools),
            'has_tools_option' => isset($options['tools']),
        ]);

        $roundIndex     = 0;
        $totalUsage     = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $finalContent   = '';
        $toolCallsData  = [];
        $startTime      = microtime(true);
        $gateway        = AiGateway::fromModel($agentConfig['model']);
        $result         = [];
        $finalResult    = null;

        try {
            // 在 try 内分配 round_index 并落 user 消息：若后续 LLM 调用失败，统一在 catch 里做补偿删除，
            // 保证"失败 → 不留任何脏数据"，便于前端重试同一条消息时不重复占位 round。
            $roundIndex = $this->allocateRoundAndPersistUserMessage($conv, $content);

            // 工具调用循环
            $callCount = 0;
            while ($callCount < self::MAX_TOOL_CALLS) {
            $callCount++;

            // 调用 AI
            $result = $gateway->chat($messages, $options);

            // 累计用量
            $totalUsage['prompt_tokens']     += $result['usage']['prompt_tokens'] ?? 0;
            $totalUsage['completion_tokens'] += $result['usage']['completion_tokens'] ?? 0;
            $totalUsage['total_tokens']      += $result['usage']['total_tokens'] ?? 0;

            // 如果有工具调用
            if (!empty($result['tool_calls'])) {
                Log::info('检测到工具调用', [
                    'tool_calls' => $result['tool_calls'],
                    'round'      => $callCount,
                ]);

                // 保存助手的工具调用消息
                $assistantMsg = AiConversationMessage::createData([
                    'conversation_id' => $conv->id,
                    'round_index'     => $roundIndex,
                    'role'            => 'assistant',
                    'content'         => $result['content'] ?? '',
                    'tool_calls'      => $result['tool_calls'],
                ]);
                $toolCallsData[] = $result['tool_calls'];

                // 将助手回复加入上下文
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $result['content'] ?? '',
                    'tool_calls' => $result['tool_calls'],
                ];

                // 执行每个工具调用
                foreach ($result['tool_calls'] as $toolCall) {
                    $toolResult = $this->executeToolCall($toolCall, [
                        'user_id'         => $userId,
                        'conversation_id' => $conv->id,
                        'agent_id'        => $conv->agent_id,
                    ]);

                    // 保存工具返回消息
                    AiConversationMessage::createData([
                        'conversation_id' => $conv->id,
                        'round_index'     => $roundIndex,
                        'role'            => 'tool',
                        'content'         => $this->encodeToolResult($toolResult),
                        'tool_call_id'    => $toolCall['id'] ?? null,
                        'name'            => $toolCall['function']['name'] ?? null,
                    ]);

                    // 将工具结果加入上下文
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? null,
                        'name'         => $toolCall['function']['name'] ?? null,
                        'content'      => $this->encodeToolResult($toolResult),
                    ];
                }

                // 继续循环，让 AI 根据工具结果继续生成
                continue;
            }

            // 没有工具调用，拿到最终回复
            $finalContent = $result['content'] ?? '';
            break;
        }

        // 如果因为达到最大调用次数退出但还没有拿到最终回复，强制 AI 生成最终内容
        if (empty($finalContent) && !empty($messages)) {
            Log::warning('达到最大工具调用次数，强制获取最终回复', [
                'max_calls' => self::MAX_TOOL_CALLS,
            ]);
            $finalOptions = array_diff_key($options, array_flip(['tools', 'tool_choice']));
            $finalResult = $gateway->chat($messages, $finalOptions);
            $finalContent = $finalResult['content'] ?? '';
            $totalUsage['prompt_tokens']     += $finalResult['usage']['prompt_tokens'] ?? 0;
            $totalUsage['completion_tokens'] += $finalResult['usage']['completion_tokens'] ?? 0;
            $totalUsage['total_tokens']      += $finalResult['usage']['total_tokens'] ?? 0;
        }

        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // M-2 修复：达到 MAX_TOOL_CALLS 走兜底时元数据应取最终一次响应（$finalResult），否则取最后一次循环响应（$result）
        $finalSource = $finalResult ?? $result;
        $finalModelName = $finalSource['model'] ?? $agentConfig['model']['model_name'] ?? 'unknown';

        // 保存最终助手回复
        $assistantMsg = AiConversationMessage::createData([
            'conversation_id' => $conv->id,
            'round_index'     => $roundIndex,
            'role'            => 'assistant',
            'content'         => $finalContent,
            'token_usage'     => $totalUsage,
            'cost'            => AiGateway::calculateCost(
                $finalModelName,
                $totalUsage['prompt_tokens'],
                $totalUsage['completion_tokens']
            ),
            'duration'        => $duration,
            'model_name'      => $finalModelName,
        ]);

        // 更新会话统计
        $this->updateConversationStats($conv, ['usage' => $totalUsage], $roundIndex);

        // 记录用量
        $this->recordUsage($userId, $conv->agent_id, [
            'usage' => $totalUsage,
            'model' => $finalModelName,
        ], $duration);

        return [
            'message'    => $assistantMsg->toArray(),
            'usage'      => $totalUsage,
            'tool_calls' => $toolCallsData,
        ];
        } catch (\Throwable $e) {
            Log::error('非流式对话异常', [
                'user_id' => $userId,
                'agent_id' => $conv->agent_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 补偿：失败时清除本轮已落库的所有消息（user + 中间过程产生的 assistant/tool），
            // 保证调用方"失败 → 无脏数据"的语义，便于上层重试。
            if ($roundIndex > 0) {
                try {
                    AiConversationMessage::where('conversation_id', $conv->id)
                        ->where('round_index', $roundIndex)
                        ->delete();
                } catch (\Throwable $ce) {
                    Log::warning('回滚本轮消息失败', ['error' => $ce->getMessage()]);
                }
            }

            throw $e;
        }
    }

    /**
     * SSE 流式发送消息（简化版，暂不支持流式工具调用）
     */
    public function sendMessageStream(array $params, int $userId): \Generator
    {
        $conversationId = $params['conversation_id'] ?? null;
        $content        = $params['content'] ?? '';
        $agentId        = $params['agent_id'] ?? null;

        $conv        = $this->resolveConversation($conversationId, $agentId, $userId);
        $agentConfig = AiAgentService::getInstance()->getAgentConfig($conv->agent_id);
        $messages    = $this->buildMessages($conv->id, $agentConfig, $content, $agentConfig['max_history_rounds'] ?? 10);

        // 获取要使用的工具：本次消息发送时用户选择的工具优先，否则使用 Agent 绑定的所有工具
        $requestedToolIds = $params['tool_ids'] ?? null;
        $toolsToUse = $this->getToolsToUse($agentConfig['tools'] ?? [], $requestedToolIds);

        // 准备工具（转换为 OpenAI 格式）
        $tools = $this->formatTools($toolsToUse);
        $options = [
            'temperature' => $agentConfig['temperature'] ?? null,
            'max_tokens'  => $agentConfig['max_tokens'] ?? null,
        ];
        if (!empty($tools)) {
            $options['tools'] = $tools;
            $options['tool_choice'] = 'auto';
        }

        $gateway        = AiGateway::fromModel($agentConfig['model']);
        $roundIndex     = 0;
        $fullContent    = '';
        $usageData      = null;
        $startTime      = microtime(true);
        $hasToolCalls   = false;
        $assistantMsg   = null;
        $errorOccurred  = false;

        try {
            // 在 try 内分配 round_index 并落 user 消息；失败时统一在 catch 中补偿删除。
            $roundIndex = $this->allocateRoundAndPersistUserMessage($conv, $content);

            // 流式输出
            $stream = $gateway->chatStream($messages, $options);

        $toolCalls = [];
        $hasFinishReason = false;

        foreach ($stream as $chunk) {
            $data = json_decode($chunk, true);
            if (!$data) continue;

            // 如果是错误响应，跳过
            if (isset($data['error'])) {
                Log::warning('流式响应包含错误', ['error' => $data['error']]);
                continue;
            }

            // 兼容不同模型的流式响应格式
            $choices = $data['choices'] ?? [];
            if (empty($choices)) continue;

            $choice = $choices[0] ?? [];
            $delta  = $choice['delta'] ?? ($choice['message'] ?? []);
            $text   = $delta['content'] ?? '';

            // 收集工具调用 - 修复合并逻辑
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tc) {
                    $idx = $tc['index'] ?? 0;
                    if (!isset($toolCalls[$idx])) {
                        $toolCalls[$idx] = [
                            'id' => $tc['id'] ?? '',
                            'type' => $tc['type'] ?? 'function',
                            'function' => [
                                'name' => '',
                                'arguments' => ''
                            ]
                        ];
                    }

                    // 累积 id
                    if (!empty($tc['id'])) {
                        $toolCalls[$idx]['id'] = $tc['id'];
                    }

                    // 累积 type
                    if (!empty($tc['type'])) {
                        $toolCalls[$idx]['type'] = $tc['type'];
                    }

                    // 累积 function 名称和参数
                    if (isset($tc['function'])) {
                        $func = $tc['function'];
                        if (!empty($func['name'])) {
                            $toolCalls[$idx]['function']['name'] .= $func['name'];
                        }
                        if (!empty($func['arguments'])) {
                            $toolCalls[$idx]['function']['arguments'] .= $func['arguments'];
                        }
                    }
                }
            }

            // 注意：有些模型第一个 chunk 的 content 是空字符串
            if ($text !== '' && $text !== null) {
                $fullContent .= $text;
                yield json_encode(['type' => 'content', 'data' => $text]);
            }

            // 某些模型最后一个 chunk 会有 usage，某些在 finish_reason=stop 时才会有
            if (isset($data['usage']) && $data['usage'] !== null) {
                $usageData = $data['usage'];
            }

            $finishReason = $data['choices'][0]['finish_reason'] ?? null;
            if ($finishReason) {
                $hasFinishReason = true;
            }

            // 支持 tool_calls 和 stop 两种结束原因
            if (($finishReason === 'stop' || $finishReason === 'tool_calls')
                && $usageData === null
                && isset($data['usage'])) {
                $usageData = $data['usage'];
            }
        }

        // 如果检测到工具调用（流式暂只支持非流式执行工具）
        if (!empty($toolCalls)) {
            $toolCalls = array_values($toolCalls);
            $hasToolCalls = true;

            Log::info('检测到工具调用，开始执行工具流程', [
                'tool_count' => count($toolCalls),
                'tools' => array_map(function($tc) {
                    return [
                        'name' => $tc['function']['name'],
                        'args' => $tc['function']['arguments'],
                    ];
                }, $toolCalls),
            ]);

            // 通知前端开始调用工具
            yield json_encode(['type' => 'tool_call_start', 'data' => ['count' => count($toolCalls)]]);

            // 保存助手的工具调用消息
            AiConversationMessage::createData([
                'conversation_id' => $conv->id,
                'round_index'     => $roundIndex,
                'role'            => 'assistant',
                'content'         => $fullContent,
                'tool_calls'      => $toolCalls,
            ]);

            // 将助手回复加入上下文
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $fullContent,
                'tool_calls' => $toolCalls,
            ];

            // 执行每个工具调用
            foreach ($toolCalls as $toolCall) {
                $toolName = $toolCall['function']['name'] ?? 'unknown';
                $toolCallId = is_array($toolCall['id'] ?? null)
                    ? json_encode($toolCall['id'], JSON_UNESCAPED_UNICODE)
                    : (string) ($toolCall['id'] ?? '');

                yield json_encode(['type' => 'tool_call_executing', 'data' => ['name' => $toolName]]);

                $toolResult = $this->executeToolCall($toolCall, [
                    'user_id'         => $userId,
                    'conversation_id' => $conv->id,
                    'agent_id'        => $conv->agent_id,
                ]);

                // 保存工具返回消息
                AiConversationMessage::createData([
                    'conversation_id' => $conv->id,
                    'round_index'     => $roundIndex,
                    'role'            => 'tool',
                    'content'         => $this->encodeToolResult($toolResult),
                    'tool_call_id'    => $toolCallId,
                    'name'            => $toolName,
                ]);

                // 将工具结果加入上下文
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name'         => $toolName,
                    'content'      => $this->encodeToolResult($toolResult),
                ];
            }

            // 通知前端工具调用完成
            yield json_encode(['type' => 'tool_call_end', 'data' => ['count' => count($toolCalls)]]);

            // 第二轮：用流式拿最终回复（去掉 tools/tool_choice，避免再触发一次工具调用）。
            // 旧实现这里走的是非流式 chat() + mb_str_split + usleep 模拟"打字效果"，
            // 既丢失真实流式响应、又额外阻塞 50ms × N 次。改成 chatStream 后用户体验一致。
            $finalOptions = array_diff_key($options, array_flip(['tools', 'tool_choice']));
            $finalContent = '';
            $finalStream  = $gateway->chatStream($messages, $finalOptions);
            foreach ($finalStream as $chunk) {
                $data = json_decode($chunk, true);
                if (!$data) continue;
                if (isset($data['error'])) {
                    Log::warning('第二轮流式响应包含错误', ['error' => $data['error']]);
                    continue;
                }
                $choices2 = $data['choices'] ?? [];
                if (empty($choices2)) continue;
                $delta2 = $choices2[0]['delta'] ?? ($choices2[0]['message'] ?? []);
                $text2  = $delta2['content'] ?? '';
                if ($text2 !== '' && $text2 !== null) {
                    $finalContent .= $text2;
                    yield json_encode(['type' => 'content', 'data' => $text2]);
                }
                if (isset($data['usage']) && $data['usage'] !== null) {
                    $usageData = $data['usage'];
                }
            }

            // 保存最终助手回复
            $costPromptTokens = is_array($usageData)
                ? ($usageData['prompt_tokens'] ?? 0)
                : 0;
            $costCompletionTokens = is_array($usageData)
                ? ($usageData['completion_tokens'] ?? 0)
                : 0;

            $assistantMsg = AiConversationMessage::createData([
                'conversation_id' => $conv->id,
                'round_index'     => $roundIndex,
                'role'            => 'assistant',
                'content'         => $finalContent,
                'token_usage'     => $usageData,
                'cost'            => AiGateway::calculateCost(
                    $agentConfig['model']['model_name'] ?? 'unknown',
                    (int) $costPromptTokens,
                    (int) $costCompletionTokens
                ),
                'duration'        => (int) ((microtime(true) - $startTime) * 1000),
                'model_name'      => $agentConfig['model']['model_name'] ?? '',
            ]);
        }

        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // 组装 usage（某些流式 API usage 在最后一条消息里）
        // 确保 usage 始终是一个数组
        if (is_array($usageData)) {
            $usage = [
                'prompt_tokens'     => (int) ($usageData['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usageData['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usageData['total_tokens'] ?? 0),
            ];
        } else {
            $usage = [
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
            ];
        }

        // 没有工具调用时保存流式消息
        if (!$hasToolCalls) {
            $assistantMsg = AiConversationMessage::createData([
                'conversation_id' => $conv->id,
                'round_index'     => $roundIndex,
                'role'            => 'assistant',
                'content'         => $fullContent,
                'token_usage'     => $usage,
                'cost'            => AiGateway::calculateCost(
                    $agentConfig['model']['model_name'] ?? 'unknown',
                    $usage['prompt_tokens'],
                    $usage['completion_tokens']
                ),
                'duration'        => $duration,
                'model_name'      => $agentConfig['model']['model_name'] ?? '',
            ]);
        }

        // 更新会话统计：用 raw 表达式做原子累加，避免并发覆盖
        AiConversation::where('id', $conv->id)->update([
            'round_count'  => $roundIndex,
            'total_tokens' => $conv->getConnection()->raw('total_tokens + ' . (int) $usage['total_tokens']),
        ]);

        // 自动更新标题
        if ($roundIndex === 1) {
            AiConversation::where('id', $conv->id)->update([
                'title' => mb_substr($content, 0, 50),
            ]);
        }

        // 记录用量
        $this->recordUsage($userId, $conv->agent_id, [
            'usage' => $usage,
            'model' => $agentConfig['model']['model_name'] ?? '',
        ], $duration);

        // 发送完成信号
        yield json_encode([
            'type'    => 'done',
            'data'    => [
                'message_id'      => $assistantMsg->id,
                'conversation_id' => $conv->id,
                'usage'           => $usage,
                'model'           => $assistantMsg->model_name ?? ($agentConfig['model']['model_name'] ?? ''),
            ],
        ]);
        } catch (\Throwable $e) {
            Log::error('流式对话异常', [
                'user_id' => $userId,
                'agent_id' => $conv->agent_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 补偿：失败时清除本轮已落库的所有消息
            if ($roundIndex > 0) {
                try {
                    AiConversationMessage::where('conversation_id', $conv->id)
                        ->where('round_index', $roundIndex)
                        ->delete();
                } catch (\Throwable $ce) {
                    Log::warning('回滚本轮流式消息失败', ['error' => $ce->getMessage()]);
                }
            }

            throw $e;
        }
    }

    // === 私有方法 ===

    private function resolveConversation(?int $conversationId, ?int $agentId, int $userId): AiConversation
    {
        if ($conversationId) {
            /** @var AiConversation|null $conv */
            $conv = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->first();
            if (!$conv) {
                throw new BusinessException('会话不存在');
            }
            return $conv;
        }

        if (!$agentId) {
            throw new BusinessException('必须指定 Agent');
        }
        return $this->createConversation($agentId, $userId);
    }

    /**
     * 构建 LLM 消息列表（含系统提示词 + RAG 知识 + 历史 + 当前消息）
     */
    private function buildMessages(int $conversationId, array $agentConfig, string $currentContent, int $maxHistoryRounds): array
    {
        $messages = [];

        // 1. 系统提示词
        $systemPrompt = $agentConfig['system_prompt'] ?? '你是一个有用的 AI 助手。';

        // 如果 Agent 启用了 query_database 工具，把表/字段白名单和相对日期约定
        // 折叠为 markdown 注入 system prompt —— 收敛此前硬编码在本方法里的 14 行
        // "相对日期使用建议"以及 DateParser 的描述，单源真相。
        $agentTools = $agentConfig['tools'] ?? [];
        if (NL2SqlPrompt::isEnabled($agentTools)) {
            $systemPrompt .= NL2SqlPrompt::build();
        }

        // RAG 知识增强
        $kbIds = $agentConfig['knowledge_base_ids'] ?? [];
        if (!empty($kbIds)) {
            $knowledgeService = AiKnowledgeService::getInstance();
            $contexts = [];
            foreach ($kbIds as $kbId) {
                $chunks = $knowledgeService->searchChunks((int) $kbId, $currentContent, 3);
                $contexts = array_merge($contexts, $chunks);
            }
            if (!empty($contexts)) {
                $systemPrompt .= "\n\n## 参考知识库\n" . implode("\n---\n", array_slice($contexts, 0, 5));
            }
        }

        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // 2. 历史消息：按 round_index 区间查询，不再 ::all() 全表扫
        //    会话很久后消息表有上万条，旧实现把全部行加载到内存再 PHP 端截断，
        //    既慢又吃内存。现在先取 MAX(round_index) 算出本次需要的下限，
        //    再用 where + orderBy + limit 在 DB 侧裁剪。
        $maxRound = (int) AiConversationMessage::where('conversation_id', $conversationId)
            ->max('round_index');
        $minRound = max(1, $maxRound - $maxHistoryRounds + 1);

        /** @var \Illuminate\Database\Eloquent\Collection<int,AiConversationMessage> $history */
        $history = AiConversationMessage::where('conversation_id', $conversationId)
            ->where('round_index', '>=', $minRound)
            ->orderBy('round_index')
            ->orderBy('id')
            ->get();

        // 按轮次分组并取最近 N 轮
        $recentRounds = [];
        $roundCount = 0;
        foreach ($history->reverse() as $msg) {
            $r = $msg->round_index;
            if (!isset($recentRounds[$r])) {
                $recentRounds[$r] = [];
                $roundCount++;
            }
            if ($roundCount > $maxHistoryRounds) break;
            $recentRounds[$r][] = $msg;
        }

        // 反转时间顺序，构建消息
        foreach (array_reverse($recentRounds) as $roundMessages) {
            foreach (array_reverse($roundMessages) as $msg) {
                $msgItem = ['role' => $msg->role];
                if ($msg->role === 'assistant') {
                    $msgItem['content'] = $msg->content ?? '';
                    $toolCalls = $msg->tool_calls;
                    if (!empty($toolCalls) && is_array($toolCalls)) {
                        // 规范化 tool_calls 格式 - 兼容通义千问等严格格式要求
                        $normalizedToolCalls = [];
                        foreach ($toolCalls as $idx => $tc) {
                            if (!is_array($tc)) {
                                continue;
                            }
                            // 确保 id 是字符串（可能之前保存的是数组）
                            $toolId = $tc['id'] ?? 'call_' . uniqid();
                            if (is_array($toolId)) {
                                $toolId = json_encode($toolId, JSON_UNESCAPED_UNICODE);
                            }
                            // 确保 type 是字符串
                            $toolType = $tc['type'] ?? 'function';
                            if (is_array($toolType)) {
                                $toolType = json_encode($toolType, JSON_UNESCAPED_UNICODE);
                            }
                            // 确保 function 字段存在
                            $funcName = $tc['function']['name'] ?? '';
                            if (is_array($funcName)) {
                                $funcName = json_encode($funcName, JSON_UNESCAPED_UNICODE);
                            }
                            $funcArgs = $tc['function']['arguments'] ?? '{}';
                            if (is_array($funcArgs)) {
                                $funcArgs = json_encode($funcArgs, JSON_UNESCAPED_UNICODE);
                            }
                            $normalizedToolCalls[] = [
                                'id'       => (string) $toolId,
                                'type'     => (string) $toolType,
                                'index'    => $idx,  // Qwen 等模型要求必须有 index 字段
                                'function' => [
                                    'name'      => (string) $funcName,
                                    'arguments' => (string) $funcArgs,
                                ],
                            ];
                        }
                        $msgItem['tool_calls'] = $normalizedToolCalls;
                    }
                } elseif ($msg->role === 'tool') {
                    $msgItem['content'] = $msg->content;
                    if ($msg->tool_call_id) {
                        $msgItem['tool_call_id'] = (string) $msg->tool_call_id;
                    }
                    if ($msg->name) {
                        $msgItem['name'] = $msg->name;
                    }
                } else {
                    $msgItem['content'] = $msg->content;
                }
                $messages[] = $msgItem;
            }
        }

        // 3. 当前消息
        $messages[] = ['role' => 'user', 'content' => $currentContent];

        return $messages;
    }

    /**
     * 获取当前对话要使用的工具列表
     *
     * 优先级规则：
     * 1. 如果本次发送选择了工具（tool_ids 是数组），只使用选中的工具
     * 2. 如果 tool_ids 是 null（未设置），使用 Agent 绑定的所有工具
     * 3. 如果 tool_ids 是空数组，不使用任何工具
     *
     * @param array $agentTools Agent 绑定的工具列表
     * @param array|null $requestedToolIds 本次请求用户选择的工具 ID
     * @return array 最终要使用的工具列表
     */
    private function getToolsToUse(array $agentTools, ?array $requestedToolIds): array
    {
        // null 表示未设置，使用 Agent 绑定的所有工具
        if ($requestedToolIds === null) {
            return $agentTools;
        }

        // 空数组表示不使用任何工具
        if (empty($requestedToolIds)) {
            return [];
        }

        // 用户选择了特定工具
        $selectedIdMap = array_flip($requestedToolIds);
        return array_filter($agentTools, function ($tool) use ($selectedIdMap) {
            return isset($selectedIdMap[$tool['id']]);
        });
    }

    /**
     * 格式化工具为 OpenAI Function Calling 格式。
     * 工具表查询时已 where status=1 过滤；此处只做格式转换。
     */
    private function formatTools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $tool['code'],
                    'description' => $tool['description'] ?? '',
                    'parameters'  => $tool['parameters_schema'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ];
        }
        return $formatted;
    }

    /**
     * 把工具调用结果安全编码为字符串：
     *  - JSON 编码失败时退化为字符串形式
     *  - 超过 MAX_TOOL_RESULT_CHARS 时截断，并附说明
     *
     * 截断既能避免 sys_operation_log / 消息表膨胀，也能避免下一轮 LLM 调用直接超出上下文窗口。
     */
    private function encodeToolResult(mixed $result): string
    {
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = (string) (is_scalar($result) ? $result : '[unserializable]');
        }
        if (mb_strlen($encoded) > self::MAX_TOOL_RESULT_CHARS) {
            $encoded = mb_substr($encoded, 0, self::MAX_TOOL_RESULT_CHARS)
                . sprintf('...[truncated, total %d chars]', mb_strlen($encoded));
        }
        return $encoded;
    }

    /**
     * 执行单个工具调用。
     *
     * 通过 {@see ToolGovernance::invoke()} 串起：DB 校验 / 限流 / schema 校验 /
     * 派发到 ToolRegistry / 截断 / 审计；任何失败都被包成结构化错误而非异常，
     * LLM 可基于错误自行修正参数后再次调用。
     */
    private function executeToolCall(array $toolCall, array $context): mixed
    {
        $funcName = $toolCall['function']['name'] ?? '';
        $args     = $toolCall['function']['arguments'] ?? '{}';

        if (is_string($args)) {
            $decoded = json_decode($args, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $args = $decoded;
            } else {
                Log::warning('工具调用参数 JSON 解析失败', [
                    'tool'  => $funcName,
                    'args'  => $args,
                    'error' => json_last_error_msg(),
                ]);
                $args = [];
            }
        }
        if (!is_array($args)) {
            $args = [];
        }

        $outcome = ToolGovernance::getInstance()->invoke((string) $funcName, $args, $context);

        // 成功：直接把工具原结果回给 LLM 上下文
        if (($outcome['success'] ?? false) === true) {
            return $outcome['result'] ?? null;
        }
        // 失败：把治理层包好的错误结构直接交给 LLM；不再二次包裹，避免冗余字段
        return [
            'success' => false,
            'error'   => $outcome['error'] ?? '工具调用失败',
            'code'    => $outcome['code']  ?? 'UNKNOWN',
            'tool'    => $funcName,
        ];
    }

    private function updateConversationStats(AiConversation $conv, array $result, int $roundIndex): void
    {
        $usage = $result['usage'] ?? [];
        $totalTokens = (int) ($usage['total_tokens'] ?? 0);
        // 用 increment / raw 表达式替代 PHP 端"读-改-写"，避免并发同会话两次发送相互覆盖
        AiConversation::where('id', $conv->id)->update([
            'round_count'  => $roundIndex,
            'total_tokens' => $conv->getConnection()->raw('total_tokens + ' . $totalTokens),
        ]);
    }

    /**
     * 原子分配下一个 round_index 并写入用户消息。
     *
     * 在一个事务里：
     *  1. 锁定会话行（lockForUpdate），同会话并发请求按行锁串行；
     *  2. 取消息表的 MAX(round_index)+1，避免依赖 round_count 这种聚合字段；
     *  3. 写入 user 消息。
     *
     * 返回新分配的 round_index。
     */
    private function allocateRoundAndPersistUserMessage(AiConversation $conv, string $content): int
    {
        return $this->transaction(function () use ($conv, $content): int {
            // 锁会话行，让并发请求按行锁串行
            AiConversation::where('id', $conv->id)->lockForUpdate()->first();

            $maxRound = (int) AiConversationMessage::where('conversation_id', $conv->id)->max('round_index');
            $roundIndex = $maxRound + 1;

            AiConversationMessage::createData([
                'conversation_id' => $conv->id,
                'round_index'     => $roundIndex,
                'role'            => 'user',
                'content'         => $content,
            ]);

            return $roundIndex;
        });
    }

    private function recordUsage(
        int $userId,
        int $agentId,
        array $result,
        int $duration,
        int $status = 1,
        ?string $errorMsg = null
    ): void {
        $usage = $result['usage'] ?? [];
        $modelName = $result['model'] ?? 'unknown';

        AiUsageRecord::createData([
            'user_id'           => $userId,
            'agent_id'          => $agentId,
            'model_name'        => $modelName,
            'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens'      => $usage['total_tokens'] ?? 0,
            'cost'              => AiGateway::calculateCost(
                $modelName,
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0
            ),
            'endpoint'          => 'chat',
            'duration'          => $duration,
            'status'            => $status,
            'error_msg'         => $errorMsg,
        ]);
    }
}

<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\common\exception\BusinessException;
use app\model\AiAgent;
use app\model\AiConversation;
use app\model\AiConversationMessage;
use app\model\AiUsageRecord;
use app\model\AiTool;
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
        AiConversationMessage::where('conversation_id', $id)->delete();
        $conv->delete();
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
        $agentConfig = (new AiAgentService())->getAgentConfig($conv->agent_id);

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

        $roundIndex     = $conv->round_count + 1;
        $totalUsage     = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $finalContent   = '';
        $toolCallsData  = [];
        $startTime      = microtime(true);
        $gateway        = AiGateway::fromModel($agentConfig['model']);

        // 保存用户消息
        AiConversationMessage::createData([
            'conversation_id' => $conv->id,
            'round_index'     => $roundIndex,
            'role'            => 'user',
            'content'         => $content,
        ]);

        try {
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
                        'content'         => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                        'tool_call_id'    => $toolCall['id'] ?? null,
                        'name'            => $toolCall['function']['name'] ?? null,
                    ]);

                    // 将工具结果加入上下文
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? null,
                        'name'         => $toolCall['function']['name'] ?? null,
                        'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
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

        // 保存最终助手回复
        $assistantMsg = AiConversationMessage::createData([
            'conversation_id' => $conv->id,
            'round_index'     => $roundIndex,
            'role'            => 'assistant',
            'content'         => $finalContent,
            'token_usage'     => $totalUsage,
            'cost'            => AiGateway::calculateCost(
                $result['model'] ?? $agentConfig['model']['model_name'] ?? 'unknown',
                $totalUsage['prompt_tokens'],
                $totalUsage['completion_tokens']
            ),
            'duration'        => $duration,
            'model_name'      => $result['model'] ?? $agentConfig['model']['model_name'] ?? '',
        ]);

        // 更新会话统计
        $this->updateConversationStats($conv, ['usage' => $totalUsage], $roundIndex);

        // 记录用量
        $this->recordUsage($userId, $conv->agent_id, [
            'usage' => $totalUsage,
            'model' => $result['model'] ?? $agentConfig['model']['model_name'] ?? '',
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

            $errorDuration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordUsage($userId, $conv->agent_id, [
                'usage' => $totalUsage,
                'model' => $agentConfig['model']['model_name'] ?? '',
            ], $errorDuration, 0, $e->getMessage());

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
        $agentConfig = (new AiAgentService())->getAgentConfig($conv->agent_id);
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
        $roundIndex     = $conv->round_count + 1;
        $fullContent    = '';
        $usageData      = null;
        $startTime      = microtime(true);
        $hasToolCalls   = false;
        $assistantMsg   = null;
        $errorOccurred  = false;

        // 保存用户消息
        AiConversationMessage::createData([
            'conversation_id' => $conv->id,
            'round_index'     => $roundIndex,
            'role'            => 'user',
            'content'         => $content,
        ]);

        try {
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
                    'content'         => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    'tool_call_id'    => $toolCallId,
                    'name'            => $toolName,
                ]);

                // 将工具结果加入上下文
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name'         => $toolName,
                    'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }

            // 通知前端工具调用完成
            yield json_encode(['type' => 'tool_call_end', 'data' => ['count' => count($toolCalls)]]);

            // 再次调用 AI 获取最终回复（非流式）- 移除 tools 配置，强制 AI 返回最终内容
            $finalOptions = array_diff_key($options, array_flip(['tools', 'tool_choice']));
            $finalResult = $gateway->chat($messages, $finalOptions);
            $finalContent = $finalResult['content'] ?? '';
            $usageData = $finalResult['usage'] ?? $usageData;

            // 如果第二次调用 AI 仍然产生了工具调用，说明工具结果不够清晰或需要多轮工具调用
            // 这种情况下我们把工具调用的内容也作为回复的一部分
            if (!empty($finalResult['tool_calls'])) {
                Log::warning('第二次 AI 调用仍然产生了工具调用，跳过执行，直接返回内容', [
                    'tool_calls_count' => count($finalResult['tool_calls']),
                    'content' => $finalContent,
                ]);
            }

            // 输出最终回复内容 - 按 20 字符的 chunk 输出，平衡用户体验和性能
            $chunks = mb_str_split($finalContent, 20);
            foreach ($chunks as $chunk) {
                yield json_encode(['type' => 'content', 'data' => $chunk]);
                usleep(50000); // 50ms 间隔，流畅的打字效果
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

        // 更新会话统计
        $conv->round_count  = $roundIndex;
        $conv->total_tokens += $usage['total_tokens'];
        $conv->save();

        // 自动更新标题
        if ($roundIndex === 1) {
            $conv->title = mb_substr($content, 0, 50);
            $conv->save();
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

            $errorDuration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordUsage($userId, $conv->agent_id, [
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'model' => $agentConfig['model']['model_name'] ?? '',
            ], $errorDuration, 0, $e->getMessage());

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

        // AI 工具使用建议（相对日期，避免 AI 缺少实时时间感知）
        $toolUsageHint = <<<EOD

【重要】工具使用建议：
当用户查询"今天"、"本周"、"本月"、"最近7天"等时间相关内容时，请在调用工具时使用相对日期参数：
- "今天" / "今日" → start_date: "today"
- "昨天" → start_date: "yesterday"
- "本周" → start_date: "this_week"
- "本月" → start_date: "this_month"
- "最近7天" → start_date: "last_7_days"
- "最近30天" → start_date: "last_30_days"
- "上周" → start_date: "last_week"
- "上月" → start_date: "last_month"

这样可以避免因缺少实时时间感知而产生日期错误。
EOD;
        $systemPrompt .= $toolUsageHint;

        // RAG 知识增强
        $kbIds = $agentConfig['knowledge_base_ids'] ?? [];
        if (!empty($kbIds)) {
            $knowledgeService = new AiKnowledgeService();
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

        // 2. 历史消息
        $history = AiConversationMessage::where('conversation_id', $conversationId)
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
     * 格式化工具为 OpenAI Function Calling 格式
     */
    private function formatTools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            // 只使用状态启用的工具
            if (empty($tool['status']) || $tool['status'] !== 1) {
                continue;
            }
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
     * 执行单个工具调用
     */
    private function executeToolCall(array $toolCall, array $context): mixed
    {
        $funcName = $toolCall['function']['name'] ?? '';
        $args     = $toolCall['function']['arguments'] ?? '{}';

        Log::debug('executeToolCall 原始参数', [
            'funcName' => $funcName,
            'args_type' => gettype($args),
            'args_value' => $args,
        ]);

        if (is_string($args)) {
            $decoded = json_decode($args, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $args = $decoded;
            } else {
                Log::warning('工具调用参数 JSON 解析失败', [
                    'args' => $args,
                    'error' => json_last_error_msg(),
                ]);
                $args = [];
            }
        }

        if (!is_array($args)) {
            $args = [];
        }

        // 查找工具定义
        /** @var AiTool|null $tool */
        $tool = AiTool::where('code', $funcName)->first();
        if (!$tool) {
            return ['error' => "工具不存在: {$funcName}"];
        }

        try {
            return ToolExecutor::execute($tool, $args, $context);
        } catch (\Throwable $e) {
            Log::error('工具执行异常', [
                'tool' => $funcName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    private function updateConversationStats(AiConversation $conv, array $result, int $roundIndex): void
    {
        $usage = $result['usage'] ?? [];
        $conv->round_count  = $roundIndex;
        $conv->total_tokens += ($usage['total_tokens'] ?? 0);
        $conv->save();
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

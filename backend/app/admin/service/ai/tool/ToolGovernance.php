<?php

namespace app\admin\service\ai\tool;

use app\common\exception\BusinessException;
use app\common\support\RateLimiter;
use app\model\AiTool;
use support\Log;

/**
 * AI 工具治理层。
 *
 * 把所有 LLM 触发的工具调用收敛到 {@see self::invoke()} 一处，串起：
 *   1. 工具存在性校验  → DB 必须有 ai_tool 记录且 status=1
 *   2. 类型派发        → function 走 {@see ToolRegistry}；api/plugin 暂未启用
 *   3. 参数 schema 校验 → {@see JsonSchemaValidator}（基于 ai_tool.parameters_schema）
 *   4. 限流            → 按 user_id + tool_code 防止 LLM 反复触发烧资源
 *   5. 执行 + 异常包裹  → 失败统一返回结构化错误，避免抛到 LLM 上下文
 *   6. 结果截断        → 超过 maxResultChars 的 JSON 输出截断 + 附 size 元信息
 *   7. 审计日志        → 成功/失败均记 info/warn，附 user/agent/conversation 上下文
 *
 * 调用方（{@see \app\admin\service\ai\AiConversationService::executeToolCall()}）
 * 只关心 `code + args + context`，治理细节全部内聚在本类。
 */
final class ToolGovernance
{
    private static ?self $instance = null;

    /** 默认每用户每分钟调用次数（可被 RegisteredTool::rateLimit 覆盖） */
    private const DEFAULT_RATE_LIMIT  = 30;
    private const RATE_LIMIT_WINDOW   = 60;

    /** 默认工具结果最大字符数（与 AiConversationService::MAX_TOOL_RESULT_CHARS 对齐） */
    private const DEFAULT_MAX_RESULT_CHARS = 16384;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 入口：执行一次工具调用。
     *
     * 返回值约定（让 LLM 自己判断成败）：
     *  - 成功：`{ success: true,  result: <工具原返回> }`
     *  - 失败：`{ success: false, error: <用户可读消息>, code: <错误码> }`
     * 永远不抛出业务异常（除非编程 bug），便于 LLM 在拿到错误后继续推理。
     *
     * @param string               $toolCode 与 ai_tool.code 一致
     * @param array<string,mixed>  $args     LLM 提供的入参（已 JSON decode）
     * @param array<string,mixed>  $context  {user_id, conversation_id, agent_id}
     * @return array{success:bool, result?:mixed, error?:string, code?:string}
     */
    public function invoke(string $toolCode, array $args, array $context): array
    {
        $userId = (int) ($context['user_id'] ?? 0);

        // 1. 取 DB 中的工具元数据（schema / handler / status / tool_type）
        /** @var AiTool|null $tool */
        $tool = AiTool::where('code', $toolCode)->first();
        if (!$tool) {
            return $this->err('TOOL_NOT_FOUND', "工具不存在: {$toolCode}");
        }
        if ((int) ($tool->status ?? 0) !== 1) {
            return $this->err('TOOL_DISABLED', "工具已禁用: {$toolCode}");
        }

        // 2. 限流：按 (user, tool) 防止 LLM 在一轮对话里反复触发烧资源
        if (!$this->checkRateLimit($userId, $toolCode, $tool)) {
            return $this->err('RATE_LIMITED', "工具调用过于频繁，请稍后再试: {$toolCode}");
        }

        // 3. 参数 schema 校验
        try {
            $schema = is_array($tool->parameters_schema) ? $tool->parameters_schema : [];
            if ($schema !== []) {
                JsonSchemaValidator::validate($args, $schema);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->err('INVALID_ARGS', $e->getMessage());
        }

        $started = microtime(true);

        // 4. 执行（按 tool_type 分派）
        try {
            $raw = match ($tool->tool_type ?? 'function') {
                'function' => $this->invokeFunction($tool, $args, $context),
                'api'      => throw new BusinessException('外部 API 类工具尚未启用'),
                'plugin'   => throw new BusinessException('插件类工具尚未启用'),
                default    => throw new BusinessException("不支持的工具类型: {$tool->tool_type}"),
            };
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $started) * 1000);
            Log::warning('AI 工具调用失败', [
                'tool'       => $toolCode,
                'user_id'    => $userId,
                'agent_id'   => $context['agent_id'] ?? null,
                'latency_ms' => $latencyMs,
                'error'      => $e->getMessage(),
            ]);
            return $this->err('EXECUTION_ERROR', $e->getMessage());
        }

        $latencyMs = (int) ((microtime(true) - $started) * 1000);
        $result    = $this->truncateResult($raw, $this->resolveMaxChars($toolCode));

        Log::info('AI 工具调用成功', [
            'tool'       => $toolCode,
            'user_id'    => $userId,
            'agent_id'   => $context['agent_id'] ?? null,
            'latency_ms' => $latencyMs,
        ]);

        return ['success' => true, 'result' => $result];
    }

    /**
     * function 类工具：必须在 {@see ToolRegistry} 注册过。
     *
     * 拒绝 DB 中 handler 字符串直接驱动反射 —— H-2 根因。
     */
    private function invokeFunction(AiTool $tool, array $args, array $context): mixed
    {
        $registered = ToolRegistry::getInstance()->get($tool->code);
        if ($registered === null) {
            throw new BusinessException("工具未在代码侧注册（未通过治理审计）: {$tool->code}");
        }
        return $registered->invoke($args, $context);
    }

    /**
     * 限流：默认 30/min（按 user × tool），注册时可单独覆盖。
     */
    private function checkRateLimit(int $userId, string $toolCode, AiTool $tool): bool
    {
        if ($userId <= 0) {
            return true; // 无用户上下文（如系统脚本）不限流
        }
        $registered = ToolRegistry::getInstance()->get($toolCode);
        $limit      = $registered?->rateLimit ?? self::DEFAULT_RATE_LIMIT;
        if ($limit <= 0) {
            return true;
        }
        $key = "ai_tool:{$userId}:{$toolCode}";
        $res = RateLimiter::hit($key, $limit, self::RATE_LIMIT_WINDOW);
        return $res['allowed'];
    }

    private function resolveMaxChars(string $toolCode): int
    {
        $registered = ToolRegistry::getInstance()->get($toolCode);
        return $registered?->maxResultChars ?? self::DEFAULT_MAX_RESULT_CHARS;
    }

    /**
     * 把工具返回结果转成"塞回 LLM 上下文也安全"的形态：
     *  - 序列化失败时不抛
     *  - 超过 maxChars 时截断（保留 size 与 sample），让 LLM 知道还有更多内容但 token 没爆
     */
    private function truncateResult(mixed $raw, int $maxChars): mixed
    {
        if ($raw === null) {
            return null;
        }
        $encoded = json_encode($raw, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return ['__error__' => 'unserializable result'];
        }
        if (mb_strlen($encoded) <= $maxChars) {
            return $raw;
        }
        return [
            '__truncated__' => true,
            'size'          => mb_strlen($encoded),
            'sample'        => mb_substr($encoded, 0, $maxChars),
        ];
    }

    /**
     * @return array{success:false, error:string, code:string}
     */
    private function err(string $code, string $message): array
    {
        return ['success' => false, 'code' => $code, 'error' => $message];
    }
}

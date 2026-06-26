<?php

namespace app\admin\service\ai;

use app\common\exception\BusinessException;
use Webman\Http\Response;
use support\Response as SupportResponse;
use support\Log;

/**
 * AI Gateway - 统一 LLM 调用抽象层
 *
 * 支持 OpenAI / DeepSeek / Qwen / Zhipu / Moonshot 等
 * 通过 provider 字段自动选择对应的 API 适配器
 *
 * 设计原则：
 *  - 所有 AI 调用必须经过此网关（便于用量统计、费用控制、日志审计）
 *  - 统一的请求/响应结构，上层不感知底层 API 差异
 *  - 支持普通调用和流式(SSE)调用
 *  - API Key 自动从 AiModel 中获取（加密存储）
 */
class AiGateway
{
    /** @var array<string, array<string, mixed>> 提供商配置（base_url 等） */
    private const PROVIDER_CONFIG = [
        'openai'   => ['base_url' => 'https://api.openai.com/v1'],
        'deepseek' => ['base_url' => 'https://api.deepseek.com/v1'],
        'qwen'     => ['base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1'],
        'zhipu'    => ['base_url' => 'https://open.bigmodel.cn/api/paas/v4'],
        'moonshot' => ['base_url' => 'https://api.moonshot.cn/v1'],
        'custom'   => ['base_url' => ''],
    ];

    /** @var array<string, array{prompt: float, completion: float}> 默认价格（美元/1K tokens） */
    private const DEFAULT_PRICES = [
        'gpt-4o'             => ['prompt' => 0.005, 'completion' => 0.015],
        'gpt-4o-mini'        => ['prompt' => 0.00015, 'completion' => 0.0006],
        'gpt-3.5-turbo'      => ['prompt' => 0.0005, 'completion' => 0.0015],
        'deepseek-chat'      => ['prompt' => 0.00014, 'completion' => 0.00028],
        'deepseek-reasoner'  => ['prompt' => 0.00055, 'completion' => 0.00219],
        'qwen-max'           => ['prompt' => 0.00286, 'completion' => 0.00857],
        'glm-4'              => ['prompt' => 0.0143, 'completion' => 0.0143],
        'moonshot-v1-8k'     => ['prompt' => 0.00171, 'completion' => 0.00171],
    ];

    private array $modelConfig;
    private string $baseUrl;
    private string $apiKey;

    /**
     * 测试/扩展用：自定义工厂闭包
     * @var \Closure(array):self|null
     */
    private static ?\Closure $factory = null;

    public function __construct(array $modelConfig)
    {
        $this->modelConfig = $modelConfig;
        $this->apiKey      = $modelConfig['api_key'];
        $this->baseUrl     = $this->resolveBaseUrl($modelConfig);
    }

    /**
     * 从 AiModel 记录创建网关实例
     */
    public static function fromModel(array $model): self
    {
        if (self::$factory) {
            return (self::$factory)($model);
        }
        return new self($model);
    }

    /**
     * 注入测试/自定义工厂
     */
    public static function setFactory(?\Closure $factory): void
    {
        self::$factory = $factory;
    }

    /**
     * 非流式聊天补全
     *
     * @param array $messages    消息列表 [['role' => 'user', 'content' => '...']]
     * @param array $options     额外参数 (temperature, max_tokens, tools 等)
     * @return array{content:string,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int},model:string,tool_calls?:array}
     */
    public function chat(array $messages, array $options = []): array
    {
        $body = $this->buildRequestBody($messages, $options, false);
        $result = $this->request('/chat/completions', $body);

        $choice = $result['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        return [
            'content'       => $message['content'] ?? '',
            'tool_calls'    => $message['tool_calls'] ?? null,
            'usage'         => $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'model'         => $result['model'] ?? $this->modelConfig['model_name'] ?? '',
            'finish_reason' => $choice['finish_reason'] ?? null,
        ];
    }

    /**
     * 流式聊天补全（SSE）
     *
     * @param array $messages 消息列表
     * @param array $options  额外参数
     * @return \Generator<string>
     */
    public function chatStream(array $messages, array $options = []): \Generator
    {
        $body = $this->buildRequestBody($messages, $options, true);
        $body['stream_options'] = ['include_usage' => true];

        $ch = curl_init();
        $url = $this->baseUrl . '/chat/completions';
        $headers = $this->buildHeaders();

        Log::info('AI 流式请求开始', [
            'url' => $url,
            'model' => $body['model'],
            'messages_count' => count($body['messages']),
            'has_tools' => isset($body['tools']),
            'tools_count' => isset($body['tools']) ? count($body['tools']) : 0,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        Log::info('AI 响应完成', [
            'http_code' => $httpCode,
            'curl_error' => $error,
            'response_length' => strlen($response),
        ]);

        if ($httpCode >= 400) {
            // 尝试解析流式错误响应
            $errorMsg = "HTTP {$httpCode}";
            if ($error) {
                $errorMsg .= " - {$error}";
            }

            // 尝试从响应中提取错误信息
            if ($response) {
                // 可能是 SSE 格式的错误，尝试提取 JSON
                $lines = explode("\n", $response);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), 'data: ')) {
                        $json = substr(trim($line), 6);
                        $data = json_decode($json, true);
                        if (isset($data['error']['message'])) {
                            $errorMsg .= " - {$data['error']['message']}";
                            break;
                        }
                    }
                }
            }

            throw new BusinessException("AI 调用失败: {$errorMsg}");
        }

        // 解析流式响应
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);
                if ($json !== '[DONE]' && !empty($json)) {
                    yield $json;
                }
            }
        }
    }

    /**
     * 文本向量化 (Embedding)
     *
     * @param string $input 输入文本
     * @return array 向量数组
     */
    public function embedding(string $input): array
    {
        $body = [
            'model' => $this->modelConfig['embedding_model'] ?? 'text-embedding-3-small',
            'input' => $input,
        ];

        $result = $this->request('/embeddings', $body);
        return $result['data'][0]['embedding'] ?? [];
    }

    /**
     * 计算费用
     */
    public static function calculateCost(string $modelName, int $promptTokens, int $completionTokens): float
    {
        $prices = self::DEFAULT_PRICES[$modelName] ?? ['prompt' => 0.001, 'completion' => 0.002];
        return round(
            ($promptTokens / 1000) * $prices['prompt'] + ($completionTokens / 1000) * $prices['completion'],
            6
        );
    }

    /**
     * 构建请求体
     */
    private function buildRequestBody(array $messages, array $options, bool $stream): array
    {
        $body = [
            'model'       => $this->modelConfig['model_name'],
            'messages'    => $messages,
            'stream'      => $stream,
            'temperature' => $options['temperature'] ?? $this->modelConfig['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? $this->modelConfig['max_tokens'] ?? 4096,
        ];

        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        if (isset($options['tools'])) {
            $body['tools'] = $options['tools'];
        }

        if (isset($options['tool_choice'])) {
            $body['tool_choice'] = $options['tool_choice'];
        }

        return $body;
    }

    /**
     * HTTP 请求（非流式）
     */
    private function request(string $endpoint, array $body): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,  // 延长到 5 分钟，长内容生成需要时间
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new BusinessException("AI 服务连接失败: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ($data['message'] ?? "HTTP {$httpCode}");
            throw new BusinessException("AI 调用失败: {$msg}");
        }

        return $data;
    }

    /**
     * 构建请求头
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];
    }

    /**
     * 解析 API 基础地址
     */
    private function resolveBaseUrl(array $config): string
    {
        if (!empty($config['base_url'])) {
            return rtrim($config['base_url'], '/');
        }
        return rtrim(self::PROVIDER_CONFIG[$config['provider']] ?? '', '/');
    }
}

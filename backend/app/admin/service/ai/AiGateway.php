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
     * 流式聊天补全（SSE）。
     *
     * 改造说明：
     *  - 用 CURLOPT_WRITEFUNCTION 在 cURL 收到字节时立即解析并 yield，避免 RETURNTRANSFER
     *    一次性拿完所有 chunk（旧实现下 worker 会被锁 300 秒）；
     *  - 维持上游的 "data: {...}\n\n" 分片格式，单次 yield 一条 JSON chunk；
     *  - 解析过程中持有一个跨回调的 buffer，处理跨 TCP 包的半行。
     *
     * @param array $messages 消息列表
     * @param array $options  额外参数
     * @return \Generator<string>
     */
    public function chatStream(array $messages, array $options = []): \Generator
    {
        $body = $this->buildRequestBody($messages, $options, true);
        $body['stream_options'] = ['include_usage' => true];

        $url     = $this->baseUrl . '/chat/completions';
        $headers = $this->buildHeaders();

        Log::info('AI 流式请求开始', [
            'url' => $url,
            'model' => $body['model'],
            'messages_count' => count($body['messages']),
            'has_tools' => isset($body['tools']),
            'tools_count' => isset($body['tools']) ? count($body['tools']) : 0,
        ]);

        // 跨回调状态：buffer 累积半行，queue 缓存待 yield 的完整事件
        $buffer = '';
        $queue  = [];
        $errorPayload = '';
        $sawData = false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            // 关键：禁用 RETURNTRANSFER，启用 WRITEFUNCTION 边收边解
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            // 整体超时收紧；BUSY_TIMEOUT 上线靠 LOW_SPEED 兜底，避免某些模型长时间无响应锁住 worker
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME  => 60,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$queue, &$errorPayload, &$sawData) {
            $errorPayload .= $data; // 留作错误时上下文
            $buffer .= $data;

            // 按 \n 拆分上游 SSE 行
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }
                $json = substr($line, 6);
                if ($json === '[DONE]') {
                    continue;
                }
                $queue[] = $json;
                $sawData = true;
            }

            // cURL write callback 必须返回写入的字节数
            return strlen($data);
        });

        // 用一个 helper 让外层 generator 既能 exec、又能在 exec 期间消费 $queue
        // 由于 cURL 是同步的，curl_exec() 在所有数据到达前不返回；我们退而求其次：
        //  - 执行 curl_exec()（期间 WRITEFUNCTION 会被多次调用并填充 queue）
        //  - 执行完成后一次性 yield queue 内所有事件
        // 相比旧实现 (RETURNTRANSFER+explode)，至少 worker 不再持有完整响应体内存，
        // 且 timeout 从 300s → 120s + 60s 慢速兜底，明显降低 worker 占用风险。
        $execOk = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        Log::info('AI 响应完成', [
            'http_code' => $httpCode,
            'curl_error' => $error,
            'events_in_queue' => count($queue),
        ]);

        if ($httpCode >= 400 || ($execOk === false && !$sawData)) {
            $errorMsg = "HTTP {$httpCode}";
            if ($error) {
                $errorMsg .= " - {$error}";
            }
            // 尝试从已收到的字节里提取 JSON 错误
            if ($errorPayload !== '') {
                foreach (explode("\n", $errorPayload) as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }
                    $payload = json_decode(substr($line, 6), true);
                    if (isset($payload['error']['message'])) {
                        $errorMsg .= " - {$payload['error']['message']}";
                        break;
                    }
                }
            }
            throw new BusinessException("AI 调用失败: {$errorMsg}");
        }

        foreach ($queue as $event) {
            yield $event;
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
     * 计算费用。
     *
     * 价格表读自 `config/ai.php` 的 `model_prices`：
     *  - 匹配：按表计算（prompt + completion，单位美元 / 1K tokens）
     *  - 未匹配：返回 0，并 warn 一次（每模型 5 分钟最多一次，避免日志洪流）
     *
     * 把价目表从代码常量挪到配置：新模型上线/调价不需要改代码，PR review 也更直观。
     */
    public static function calculateCost(string $modelName, int $promptTokens, int $completionTokens): float
    {
        $prices = (array) config("ai.model_prices.{$modelName}", []);
        if ($prices === [] || !isset($prices['prompt'], $prices['completion'])) {
            self::warnUnknownModelOnce($modelName);
            return 0.0;
        }
        return round(
            ($promptTokens / 1000) * (float) $prices['prompt']
            + ($completionTokens / 1000) * (float) $prices['completion'],
            6
        );
    }

    /**
     * 节流告警：未在价目表中的模型，单 worker 内每个模型 5 分钟最多记一次 warning。
     * 不依赖 Redis（即便 Redis 故障也不影响主流程），用进程内 static 数组。
     */
    private static function warnUnknownModelOnce(string $modelName): void
    {
        /** @var array<string,int> $seen */
        static $seen = [];
        $now = time();
        if (isset($seen[$modelName]) && $now - $seen[$modelName] < 300) {
            return;
        }
        $seen[$modelName] = $now;
        Log::warning('AI 模型未在价目表中，cost 记为 0', ['model' => $modelName]);
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
            // 非流式：保持 120s 上限；过去的 300s 在 webman 多 worker 模型下少量并发就会塞满 worker
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
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

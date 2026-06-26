<?php

namespace Tests\Unit;

use app\admin\service\ai\AiGateway;

/**
 * 测试用 Fake AiGateway —— 继承真实类以保持签名兼容，
 * 重写 chat() / chatStream() 返回预设数据，不发真实 HTTP。
 */
class FakeAiGateway extends AiGateway
{
    /** 预设的 chat() 响应 */
    public static ?array $nextResponse = null;

    /** 预设的异常（优先于 nextResponse） */
    public static ?\Throwable $nextException = null;

    public static int $callCount = 0;

    public static function reset(): void
    {
        self::$nextResponse = null;
        self::$nextException = null;
        self::$callCount = 0;
        AiGateway::setFactory(null);
    }

    public function chat(array $messages, array $options = []): array
    {
        self::$callCount++;
        if (self::$nextException) {
            throw self::$nextException;
        }
        return self::$nextResponse ?? [
            'content' => 'fake default',
            'usage'   => [
                'prompt_tokens'     => 10,
                'completion_tokens' => 5,
                'total_tokens'      => 15,
            ],
            'model'   => 'fake-model',
        ];
    }
}

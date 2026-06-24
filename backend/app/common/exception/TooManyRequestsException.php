<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 请求过于频繁异常（429）
 *
 * 由 RateLimit 中间件在触发限流时抛出，全局 Handler 统一渲染为 JSON。
 * 携带 retryAfter（秒），供前端提示「请 N 秒后重试」并写入 Retry-After 响应头。
 */
class TooManyRequestsException extends RuntimeException
{
    /** 建议重试等待秒数 */
    public readonly int $retryAfter;

    public function __construct(string $message = '请求过于频繁，请稍后再试', int $retryAfter = 1, ?Throwable $previous = null)
    {
        $this->retryAfter = max(1, $retryAfter);
        parent::__construct($message, ResponseCode::TOO_MANY_REQUESTS->value, $previous);
    }
}

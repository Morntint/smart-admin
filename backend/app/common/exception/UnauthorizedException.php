<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 未登录 / Token 无效异常（401）
 */
class UnauthorizedException extends RuntimeException
{
    public function __construct(string $message = '请先登录', ?Throwable $previous = null)
    {
        parent::__construct($message, ResponseCode::UNAUTHORIZED->value, $previous);
    }
}

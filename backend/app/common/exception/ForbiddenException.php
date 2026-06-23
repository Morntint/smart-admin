<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 无权限访问异常（403）
 */
class ForbiddenException extends RuntimeException
{
    public function __construct(string $message = '无权限访问', ?Throwable $previous = null)
    {
        parent::__construct($message, ResponseCode::FORBIDDEN->value, $previous);
    }
}

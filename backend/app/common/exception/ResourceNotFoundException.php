<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 资源不存在异常（404）
 */
class ResourceNotFoundException extends RuntimeException
{
    public function __construct(string $message = '数据不存在', ?Throwable $previous = null)
    {
        parent::__construct($message, ResponseCode::NOT_FOUND->value, $previous);
    }
}

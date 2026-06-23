<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 验证异常
 *
 * 用于参数/业务规则校验失败场景，区别于框架 Validation 中间件
 * 抛出的异常——本异常属于业务侧语义失败。
 */
class ValidationException extends RuntimeException
{
    /** @var array<string,string> 字段级错误 */
    protected array $errors = [];

    public function __construct(
        string $message = '数据验证失败',
        array $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, ResponseCode::VALIDATION_FAIL->value, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

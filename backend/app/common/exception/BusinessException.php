<?php

namespace app\common\exception;

use app\common\ResponseCode;
use RuntimeException;
use Throwable;

/**
 * 业务异常
 *
 * 用于业务层主动抛出，由全局 Handler 统一转换为 JSON 响应。
 * 业务异常不记录 ERROR 日志，但仍走 render 输出明确的业务码。
 *
 * 抛出示例：
 *   throw new BusinessException('用户不存在', ResponseCode::NOT_FOUND);
 *   throw BusinessException::conflict('用户名已存在');
 */
class BusinessException extends RuntimeException
{
    /** 业务码 */
    protected int $businessCode;

    /**
     * @param string                 $message      业务提示信息
     * @param int|ResponseCode|null  $code         业务码（可传 ResponseCode 枚举）
     * @param Throwable|null         $previous     原异常（异常链）
     */
    public function __construct(
        string $message = '业务处理失败',
        int|ResponseCode|null $code = null,
        ?Throwable $previous = null
    ) {
        $this->businessCode = match (true) {
            $code instanceof ResponseCode => $code->value,
            is_int($code)                 => $code,
            default                       => ResponseCode::BUSINESS_FAIL->value,
        };

        parent::__construct($message, $this->businessCode, $previous);
    }

    public function getBusinessCode(): int
    {
        return $this->businessCode;
    }

    /**
     * 资源冲突（409）：例如唯一字段重复。
     */
    public static function conflict(string $message): self
    {
        return new self($message, ResponseCode::CONFLICT);
    }

    /**
     * 资源不存在（404）。
     */
    public static function notFound(string $message = '数据不存在'): self
    {
        return new self($message, ResponseCode::NOT_FOUND);
    }

    /**
     * 参数错误（400）。
     */
    public static function badRequest(string $message): self
    {
        return new self($message, ResponseCode::BAD_REQUEST);
    }

    /**
     * 无权限（403）。
     */
    public static function forbidden(string $message = '无权限访问'): self
    {
        return new self($message, ResponseCode::FORBIDDEN);
    }

    /**
     * 未登录（401）。
     */
    public static function unauthorized(string $message = '请先登录'): self
    {
        return new self($message, ResponseCode::UNAUTHORIZED);
    }
}

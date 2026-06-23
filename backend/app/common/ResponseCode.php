<?php

namespace app\common;

/**
 * 统一业务响应码枚举
 *
 * 与前端约定的错误码区间：
 *  - 2xx：成功
 *  - 4xx：业务/客户端错误（无权限、参数错误、资源不存在等）
 *  - 5xx：服务端错误
 *
 * 注意：业务码与 HTTP 状态码是解耦的——业务码只表示"业务语义"，
 * 真正影响 HTTP Status 的是全局 Handler 内的映射逻辑。
 */
enum ResponseCode: int
{
    // -------------------------------------------------------------------------
    // 2xx 成功
    // -------------------------------------------------------------------------
    case SUCCESS = 200;

    // -------------------------------------------------------------------------
    // 4xx 客户端/业务错误
    // -------------------------------------------------------------------------
    case BAD_REQUEST        = 400;
    case UNAUTHORIZED       = 401;
    case FORBIDDEN          = 403;
    case NOT_FOUND          = 404;
    case METHOD_NOT_ALLOWED = 405;
    case CONFLICT           = 409;
    case VALIDATION_FAIL    = 422;
    case BUSINESS_FAIL      = 423;
    case TOO_MANY_REQUESTS  = 429;

    // -------------------------------------------------------------------------
    // 5xx 服务端错误
    // -------------------------------------------------------------------------
    case SERVER_ERROR        = 500;
    case SERVICE_UNAVAILABLE = 503;

    /**
     * 默认中文描述，可用于日志或兜底响应。
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESS              => '操作成功',
            self::BAD_REQUEST          => '请求参数错误',
            self::UNAUTHORIZED         => '未登录或登录已过期',
            self::FORBIDDEN            => '无权限访问',
            self::NOT_FOUND            => '资源不存在',
            self::METHOD_NOT_ALLOWED   => '请求方法不被允许',
            self::CONFLICT             => '资源冲突',
            self::VALIDATION_FAIL      => '数据验证失败',
            self::BUSINESS_FAIL        => '业务处理失败',
            self::TOO_MANY_REQUESTS    => '请求过于频繁，请稍后再试',
            self::SERVER_ERROR         => '服务器内部错误',
            self::SERVICE_UNAVAILABLE  => '服务暂不可用',
        };
    }

    /**
     * 是否为客户端/业务错误（4xx）。
     */
    public function isClientError(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    /**
     * 是否为服务端错误（5xx）。
     */
    public function isServerError(): bool
    {
        return $this->value >= 500 && $this->value < 600;
    }
}

<?php

namespace app\exception;

use app\common\exception\BusinessException;
use app\common\exception\ForbiddenException;
use app\common\exception\ResourceNotFoundException;
use app\common\exception\UnauthorizedException;
use app\common\exception\ValidationException;
use app\common\ResponseCode;
use support\exception\Handler as SupportHandler;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 全局异常处理器
 *
 * 继承 webman 默认 Handler 以保留默认行为（HTTP 异常处理、调试视图等），
 * 同时覆盖 report/render 实现统一的错误响应格式。
 *
 * 异常处理约定：
 *  - 业务异常（BusinessException 及其派生类）静默不上报
 *  - 5xx 异常会上报到 error 日志并隐藏敏感信息（路径、邮箱）
 *  - debug=true 下返回完整堆栈，方便定位
 */
class Handler extends SupportHandler
{
    /**
     * 不上报到 ERROR 日志的异常类型（仅业务侧异常）。
     *
     * @var array<int,class-string<Throwable>>
     */
    public $dontReport = [
        ValidationException::class,
        ResourceNotFoundException::class,
        ForbiddenException::class,
        UnauthorizedException::class,
        BusinessException::class,
    ];

    /**
     * 上报异常到日志。
     */
    public function report(Throwable $exception): void
    {
        // 业务异常静默（仍走 render 产生 JSON 响应）
        foreach ($this->dontReport as $type) {
            if ($exception instanceof $type) {
                return;
            }
        }

        $request = request();
        Log::error($exception->getMessage(), [
            'exception'  => get_class($exception),
            'file'       => $exception->getFile(),
            'line'       => $exception->getLine(),
            'method'     => $request?->method(),
            'path'       => $request?->path(),
            'ip'         => $request?->getRealIp(),
            'user_agent' => $request?->header('user-agent'),
            'trace'      => $exception->getTraceAsString(),
        ]);
    }

    /**
     * 渲染异常为 HTTP 响应。
     */
    public function render(Request $request, Throwable $exception): Response
    {
        // 调试模式：返回完整堆栈，便于定位问题
        if (config('app.debug', false)) {
            return $this->jsonResponse(500, [
                'code' => ResponseCode::SERVER_ERROR->value,
                'msg'  => $exception->getMessage(),
                'data' => [
                    'exception' => get_class($exception),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'trace'     => explode("\n", $exception->getTraceAsString()),
                ],
            ]);
        }

        // 生产模式：按异常类型映射业务码
        $code    = $this->getErrorCode($exception);
        $message = $this->getErrorMessage($exception);

        $body = ['code' => $code, 'msg' => $message];

        // 校验异常额外携带字段错误（前端可逐字段提示）
        if ($exception instanceof ValidationException && $exception->getErrors()) {
            $body['data'] = ['errors' => $exception->getErrors()];
        }

        return $this->jsonResponse(
            $code >= 500 ? 500 : 200, // HTTP 状态码：5xx 用 500，其余统一 200（约定使用 body.code 区分）
            $body
        );
    }

    /**
     * 根据异常类型映射业务码。
     */
    private function getErrorCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof BusinessException         => $e->getBusinessCode(),
            $e instanceof ValidationException       => ResponseCode::VALIDATION_FAIL->value,
            $e instanceof ResourceNotFoundException => ResponseCode::NOT_FOUND->value,
            $e instanceof ForbiddenException        => ResponseCode::FORBIDDEN->value,
            $e instanceof UnauthorizedException     => ResponseCode::UNAUTHORIZED->value,
            default                                  => ResponseCode::SERVER_ERROR->value,
        };
    }

    /**
     * 隐藏敏感错误信息（绝对路径、邮箱等）。
     */
    private function getErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage() ?: '服务器内部错误';
        $message = preg_replace('/[a-zA-Z]:\\\\[^\s]+|\/[^\s]+/', '[path]', $message) ?? $message;
        $message = preg_replace('/\b[\w.-]+@[\w.-]+\.\w+\b/', '[email]', $message) ?? $message;
        return $message;
    }

    /**
     * 构建 JSON HTTP 响应。
     *
     * @param array<string,mixed> $body
     */
    private function jsonResponse(int $httpStatus, array $body): Response
    {
        return new Response(
            $httpStatus,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

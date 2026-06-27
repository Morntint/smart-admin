<?php

namespace app\exception;

use app\common\exception\BusinessException;
use app\common\exception\ForbiddenException;
use app\common\exception\ResourceNotFoundException;
use app\common\exception\TooManyRequestsException;
use app\common\exception\UnauthorizedException;
use app\common\exception\ValidationException;
use app\common\ResponseCode;
use app\common\support\TraceContext;
use support\exception\Handler as SupportHandler;
use support\exception\PageNotFoundException;
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
        TooManyRequestsException::class,
        BusinessException::class,
        // 框架级 404：路由未匹配（如浏览器/爬虫探测 /.well-known、favicon 等），
        // 属客户端错误，不应污染 ERROR 日志。
        PageNotFoundException::class,
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
        // 调试模式：返回可读堆栈，但不暴露服务器绝对路径（用 trace id 替代 file/line）
        if (config('app.debug', false)) {
            $requestId = TraceContext::id() ?: 'debug-request';
            return $this->jsonResponse(500, [
                'code' => ResponseCode::SERVER_ERROR->value,
                'msg'  => $this->sanitizeMessage($exception->getMessage()),
                'request_id' => $requestId,
                'data' => [
                    'exception' => get_class($exception),
                    'trace'     => array_map('strval', array_slice(explode("\n", $exception->getTraceAsString()), 0, 20)),
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

        // 服务端错误携带 request_id，便于用户上报、运维按 id 检索日志定位
        if ($code >= 500) {
            $requestId = TraceContext::id();
            if ($requestId !== '') {
                $body['request_id'] = $requestId;
            }
        }

        $response = $this->jsonResponse(
            $code >= 500 ? 500 : 200, // HTTP 状态码：5xx 用 500，其余统一 200（约定使用 body.code 区分）
            $body
        );

        // 限流异常补充 Retry-After，告知客户端重试等待秒数
        if ($exception instanceof TooManyRequestsException) {
            $response->withHeader('Retry-After', (string) $exception->retryAfter);
        }

        return $response;
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
            $e instanceof TooManyRequestsException  => ResponseCode::TOO_MANY_REQUESTS->value,
            default                                  => ResponseCode::SERVER_ERROR->value,
        };
    }

    /**
     * 隐藏敏感错误信息（绝对路径、邮箱等），同时避免误伤合法 URL 和中文。
     *
     * 旧实现使用全局 `/\[a-zA-Z]:\\[^\s]+|\/[^\s]+/`，会把 `/上海`、`https://...` 中路径段
     * 都误替换为 `[path]`。收敛到：仅替换以盘符或 `\app\` 开头的明显服务器绝对路径。
     */
    private function getErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage() ?: '服务器内部错误';
        $message = $this->sanitizeMessage($message);
        return $message;
    }

    /**
     * 脱敏消息中的路径与邮箱。
     */
    private function sanitizeMessage(string $message): string
    {
        // Windows 绝对路径：C:\path\to\... 或 D:\xxx
        $message = preg_replace('/[a-zA-Z]:\\\\[^\\s,;:)\]>"\']+/', '[path]', $message) ?? $message;
        // Unix 路径：/var/www/... 等以 / 开头且包含多个斜杠分段的路径
        $message = preg_replace('#/([a-zA-Z0-9_-]+/){2,}[a-zA-Z0-9_. -]+#', '/[path]', $message) ?? $message;
        // 邮箱
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
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        );
    }
}

<?php

namespace app\middleware;

use app\common\support\TraceContext;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 链路追踪中间件（全局）
 *
 * 职责：
 *  - 为每个请求生成唯一 request_id；若上游（网关/Nginx/前端）已传 X-Request-Id 则透传复用
 *  - 写入请求上下文（TraceContext），供日志 Processor、异常 Handler、业务层读取
 *  - 在响应头回写 X-Request-Id，便于前端上报与跨服务排障
 *  - （可选）记录访问日志：每个请求落一条带 request_id 的 INFO 日志，
 *    使「正常请求」也能用 log:trace 串起链路，而不只是出错/慢查询时才有日志
 *
 * 注册：config/middleware.php 的 '' 全局分组，置于 Cors 之后、业务分组之前，
 *      保证即使 404/异常响应也带上 request_id。
 *
 * 访问日志开关：env ACCESS_LOG=true 开启（默认关闭，避免高并发下日志量过大）。
 *
 * 说明：上游传入的 id 会做长度与字符白名单校验，防止日志注入/超长污染。
 */
class Trace implements MiddlewareInterface
{
    /** 透传请求头名 */
    private const HEADER = 'X-Request-Id';

    /** 透传 id 允许的最大长度（超出视为非法，改用自生成 id） */
    private const MAX_LEN = 64;

    public function process(Request $request, callable $handler): Response
    {
        $requestId = $this->resolveRequestId($request);
        TraceContext::set($requestId);

        $start    = microtime(true);
        $response = $handler($request);
        $response->withHeader(self::HEADER, $requestId);

        $this->writeAccessLog($request, $response, $start);

        return $response;
    }

    /**
     * 记录访问日志（仅在 ACCESS_LOG=true 时）。
     *
     * request_id 由 TraceProcessor 自动附加到日志 extra，无需在此重复写。
     */
    private function writeAccessLog(Request $request, Response $response, float $start): void
    {
        if (!filter_var(env('ACCESS_LOG', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            Log::info('access', [
                'method'   => $request->method(),
                'path'     => $request->path(),
                'status'   => $response->getStatusCode(),
                'ip'       => $request->getRealIp(),
                'duration' => round((microtime(true) - $start) * 1000, 1) . 'ms',
            ]);
        } catch (Throwable) {
            // 访问日志失败不影响业务
        }
    }

    /**
     * 解析 request_id：优先透传上游合法值，否则新生成。
     */
    private function resolveRequestId(Request $request): string
    {
        $incoming = (string) $request->header('x-request-id', '');

        if ($incoming !== ''
            && strlen($incoming) <= self::MAX_LEN
            && preg_match('/^[A-Za-z0-9._-]+$/', $incoming)
        ) {
            return $incoming;
        }

        return TraceContext::generate();
    }
}

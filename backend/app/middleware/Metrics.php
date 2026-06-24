<?php

namespace app\middleware;

use app\common\support\MetricsCollector;
use support\App;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 指标采集中间件（全局）
 *
 * 计时每个请求并把「方法 + 状态码段 + 耗时 + Worker 内存」上报到 MetricsCollector，
 * 供 /metrics 端点以 Prometheus 文本格式输出，做容量规划与 SLA 监控。
 *
 * 注册：config/middleware.php 的 '' 全局分组（Trace 之后即可）。
 * 排除：/metrics 自身不计入，避免抓取动作污染指标。
 *
 * 性能：仅在响应阶段做几次 Redis hIncrBy（O(1)），且全部 fail-open，
 *      不会因监控拖慢或中断业务。
 */
class Metrics implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // /metrics 自身不计入
        if ($request->path() === '/metrics') {
            return $handler($request);
        }

        $start    = microtime(true);
        $response = $handler($request);

        try {
            $durationMs = (microtime(true) - $start) * 1000;
            MetricsCollector::observe(
                method: $request->method(),
                statusCode: $response->getStatusCode(),
                durationMs: $durationMs,
                workerId: (int) (App::worker()?->id ?? 0),
            );
        } catch (Throwable) {
            // fail-open：监控不影响业务
        }

        return $response;
    }
}

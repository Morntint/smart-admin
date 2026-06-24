<?php

namespace app\controller;

use app\common\support\MetricsCollector;
use support\Request;
use support\Response;

/**
 * 指标输出控制器
 *
 * 暴露 /metrics（Prometheus 文本格式），供 Prometheus 抓取。
 *
 * 鉴权：METRICS_TOKEN 环境变量。为空时不鉴权（仅建议内网/开发）；
 *      设置后需在请求头 Authorization: Bearer <token> 或 ?token= 携带。
 *
 * 路由：在 config/route.php 注册为 Route::get('/metrics', ...)，
 *      位于全局中间件链上（Trace/Cors/Metrics），但 Metrics 中间件会跳过本路径。
 */
class MetricsController
{
    public function index(Request $request): Response
    {
        if (!$this->authorized($request)) {
            return new Response(401, ['Content-Type' => 'text/plain; charset=utf-8'], "unauthorized\n");
        }

        return new Response(
            200,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
            MetricsCollector::render()
        );
    }

    /**
     * 校验访问令牌。METRICS_TOKEN 为空则放行。
     */
    private function authorized(Request $request): bool
    {
        $expected = (string) env('METRICS_TOKEN', '');
        if ($expected === '') {
            return true;
        }

        $auth = (string) $request->header('authorization', '');
        $token = str_starts_with($auth, 'Bearer ')
            ? substr($auth, 7)
            : (string) $request->get('token', '');

        return hash_equals($expected, $token);
    }
}

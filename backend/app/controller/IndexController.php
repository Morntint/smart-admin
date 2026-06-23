<?php

namespace app\controller;

use app\common\ResponseCode;
use support\Request;
use support\Response;

/**
 * 入口控制器
 *
 * 仅提供健康检查与服务信息的响应。具体业务请参见 app/admin/controller。
 */
class IndexController
{
    /**
     * 服务首页（无路由前缀）。
     *
     * 返回服务基本信息，便于负载均衡 / 监控探活。
     */
    public function index(Request $request): Response
    {
        return json([
            'code' => ResponseCode::SUCCESS->value,
            'msg'  => 'Webman Admin is running',
            'data' => [
                'name'      => (string) sysConfig('sys_name', 'Webman Admin'),
                'version'   => (string) sysConfig('sys_version', '1.0.0'),
                'env'       => (string) env('APP_ENV', 'production'),
                'timestamp' => time(),
            ],
        ]);
    }

    /**
     * 健康检查（用于 K8s liveness/readiness probe、Nginx upstream 探测）。
     */
    public function ping(Request $request): Response
    {
        return json(['code' => 200, 'msg' => 'pong']);
    }
}

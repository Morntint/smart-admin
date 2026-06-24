<?php

namespace app\middleware;

use app\middleware\Concerns\HasCorsHeaders;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 全局 CORS 跨域中间件
 *
 * 职责：
 *  - 拦截浏览器预检请求（OPTIONS）并直接返回 204，避免落到路由分发被 404
 *  - 为所有响应注入跨域响应头，允许前端 dev server 直连后端（携带 Authorization 头）
 *
 * 说明：作为全局中间件注册（config/middleware.php 的 '' 分组），
 * 即使路由未匹配（404）也会先经过本中间件，预检才能成功。
 * 跨域规则统一来自 config/cors.php，与 StaticFile 共享。
 */
class Cors implements MiddlewareInterface
{
    use HasCorsHeaders;

    public function process(Request $request, callable $handler): Response
    {
        // 预检请求直接放行，不进入业务路由
        if ($request->method() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $handler($request);
        }

        return $this->withCors($request, $response);
    }
}

<?php

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 静态文件兜底中间件
 *
 * 职责：
 *  - 拦截所有以 `/.` 开头的静态资源请求（隐藏文件、.git、.env 等）→ 403
 *  - 透传普通响应；如需 CORS，可在此处统一注入响应头
 */
class StaticFile implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 禁止访问以 . 开头的隐藏文件 / 目录
        if (str_contains($request->path(), '/.')) {
            return response('<h1>403 forbidden</h1>', 403);
        }

        /** @var Response $response */
        $response = $handler($request);

        // 如需开启 CORS，请改成项目实际白名单逻辑
        // $response->withHeaders([
        //     'Access-Control-Allow-Origin'      => '*',
        //     'Access-Control-Allow-Credentials' => 'true',
        // ]);

        return $response;
    }
}

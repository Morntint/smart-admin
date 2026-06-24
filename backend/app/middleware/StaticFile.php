<?php

namespace app\middleware;

use app\middleware\Concerns\HasCorsHeaders;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 静态文件兜底中间件
 *
 * 职责：
 *  - 拦截所有以 `/.` 开头的静态资源请求（隐藏文件、.git、.env 等）→ 403
 *  - 为静态资源注入跨域响应头（规则同 config/cors.php，与 Cors 中间件一致）
 *
 * 说明：webman 静态资源走 config/static.php 的中间件链，不经过全局 '' 分组，
 * 故此处复用 HasCorsHeaders 补齐静态资源的跨域头。
 */
class StaticFile implements MiddlewareInterface
{
    use HasCorsHeaders;

    public function process(Request $request, callable $handler): Response
    {
        // 禁止访问以 . 开头的隐藏文件 / 目录
        if (str_contains($request->path(), '/.')) {
            return response('<h1>403 forbidden</h1>', 403);
        }

        /** @var Response $response */
        $response = $handler($request);

        return $this->withCors($request, $response);
    }
}

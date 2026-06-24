<?php

namespace app\middleware\Concerns;

use Webman\Http\Request;
use Webman\Http\Response;

/**
 * CORS 响应头注入 trait
 *
 * 供 Cors / StaticFile 中间件复用，跨域规则统一读取 config/cors.php，
 * 保证业务接口与静态资源的跨域行为一致。
 */
trait HasCorsHeaders
{
    /**
     * 按 config/cors.php 为响应注入跨域头。
     * 命中白名单（或白名单为空时反射 Origin）才注入，否则原样返回。
     */
    protected function withCors(Request $request, Response $response): Response
    {
        $config       = config('cors', []);
        $allowOrigins = $config['allow_origins'] ?? [];
        $origin       = $request->header('origin', '');

        if (empty($allowOrigins)) {
            // 白名单为空：反射请求 Origin（无 Origin 时回退 *）
            $allowOrigin = $origin !== '' ? $origin : '*';
        } elseif (in_array($origin, $allowOrigins, true)) {
            $allowOrigin = $origin;
        } else {
            // 来源不在白名单内：不注入跨域头
            return $response;
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin'      => $allowOrigin,
            'Access-Control-Allow-Credentials' => ($config['allow_credentials'] ?? true) ? 'true' : 'false',
            'Access-Control-Allow-Methods'     => $config['allow_methods'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'     => $config['allow_headers'] ?? 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
            'Access-Control-Max-Age'           => (string) ($config['max_age'] ?? '86400'),
        ]);
    }
}

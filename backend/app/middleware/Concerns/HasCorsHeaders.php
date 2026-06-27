<?php

namespace app\middleware\Concerns;

use Webman\Http\Request;
use Webman\Http\Response;

/**
 * CORS 响应头注入 trait
 *
 * 供 Cors / StaticFile 中间件复用，跨域规则统一读取 config/cors.php，
 * 保证业务接口与静态资源的跨域行为一致。
 *
 * 安全要点：
 *  - 白名单为空时不再反射 Origin（旧实现 `[] + credentials=true` 等价于全开放带 cookie，
 *    CSRF 风险显著扩大）；
 *  - 显式 `['*']` 时强制 `Allow-Credentials: false`（浏览器规范禁止 `*` 与 credentials 共存）；
 *  - 仅命中白名单或显式通配时才注入跨域头，其它情况原样返回。
 */
trait HasCorsHeaders
{
    /**
     * 按 config/cors.php 为响应注入跨域头。
     */
    protected function withCors(Request $request, Response $response): Response
    {
        $config       = config('cors', []);
        $allowOrigins = $config['allow_origins'] ?? [];
        $origin       = (string) $request->header('origin', '');

        // 同源请求不需要 CORS 头（也避免不必要的 Vary）
        if ($origin === '') {
            return $response;
        }

        $allowOrigin       = null;
        $allowCredentials  = (bool) ($config['allow_credentials'] ?? true);

        if (in_array('*', $allowOrigins, true)) {
            // 显式通配：与 credentials 不兼容，强制关闭
            $allowOrigin      = '*';
            $allowCredentials = false;
        } elseif ($allowOrigins !== [] && in_array($origin, $allowOrigins, true)) {
            $allowOrigin = $origin;
        } else {
            // 白名单为空 或 来源不在白名单：不注入跨域头
            return $response;
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin'      => $allowOrigin,
            'Access-Control-Allow-Credentials' => $allowCredentials ? 'true' : 'false',
            'Access-Control-Allow-Methods'     => $config['allow_methods'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'     => $config['allow_headers'] ?? 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
            'Access-Control-Max-Age'           => (string) ($config['max_age'] ?? '86400'),
            // 显式白名单匹配 origin 时加 Vary，提示中间缓存不要把 origin A 的响应缓存给 origin B
            'Vary'                             => $allowOrigin === '*' ? ($response->getHeader('Vary') ?? '') : 'Origin',
        ]);
    }
}

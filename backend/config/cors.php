<?php

/**
 * CORS 跨域配置（单一数据源）
 *
 * Cors 中间件（业务路由 / 404）与 StaticFile 中间件（public 静态资源）
 * 共用本配置，避免跨域规则散落在多处。
 *
 * allow_origins：来源白名单
 *   - 为空数组 []：仅放行同源请求；不再反射任意 Origin（旧实现下 `[] + credentials=true`
 *     等价于全开放且带 cookie，CSRF 风险扩大）。如需开发期跨域便利，请显式列出 origin
 *     或临时把 allow_origins 设为 ['*']（不与 credentials 共用）。
 *   - 非空：仅放行命中白名单的来源，其余请求不注入跨域头。
 *
 * allow_credentials：仅当 allow_origins 是显式列表时才生效；当 allow_origins 设为
 * `['*']` 时本项被强制视为 false（浏览器规范禁止 `Access-Control-Allow-Origin: *` 与
 * credentials 共存）。
 */
return [
    'allow_origins'     => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_ORIGINS', '')))),
    'allow_credentials' => true,
    'allow_methods'     => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
    'allow_headers'     => 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
    'max_age'           => '86400',
];

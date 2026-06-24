<?php

/**
 * CORS 跨域配置（单一数据源）
 *
 * Cors 中间件（业务路由 / 404）与 StaticFile 中间件（public 静态资源）
 * 共用本配置，避免跨域规则散落在多处。
 *
 * allow_origins：来源白名单
 *   - 为空数组 []：反射请求 Origin（开发便利，等价于放行任意来源）
 *   - 非空：仅放行命中白名单的来源，其余请求不注入跨域头
 */
return [
    'allow_origins'     => [],
    'allow_credentials' => true,
    'allow_methods'     => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
    'allow_headers'     => 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
    'max_age'           => '86400',
];

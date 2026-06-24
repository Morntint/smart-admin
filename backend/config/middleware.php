<?php

/**
 * 后台路由中间件
 *
 * 参与顺序（请求方向）：
 *   Trace  →  Metrics  →  Cors  →  AuthMiddleware  →  RateLimit  →  Idempotent  →  OperationLog  →  Controller
 *
 * Trace        ：生成/透传 request_id，写入上下文并回写响应头（最前，覆盖 404/异常）
 * Metrics      ：请求计时与计数，上报 Prometheus 指标（/metrics 自身不计入）
 * Cors         ：跨域响应头 + OPTIONS 预检
 * AuthMiddleware：解析 Token、注入用户信息、按菜单 permission 鉴权
 * RateLimit    ：按 #[RateLimit] 注解限流（置于 Auth 后，by='user' 才能拿到用户 ID）
 * Idempotent   ：按 #[Idempotent] 注解做写操作去重（置于 Auth 后、OperationLog 前）
 * OperationLog ：捕获写操作请求/响应，落库到 sys_operation_log
 *
 * 全局静态文件中间件（StaticFile）通过 config/static.php 配置。
 * 全局 CORS 中间件（Cors）在 '' 分组，先于任何分组执行，
 * 保证 OPTIONS 预检（含未匹配路由的 404）也能拿到跨域响应头。
 */
return [
    '' => [
        \app\middleware\Trace::class,
        \app\middleware\Metrics::class,
        \app\middleware\Cors::class,
    ],
    'admin' => [
        \app\admin\middleware\AuthMiddleware::class,
        \app\admin\middleware\RateLimit::class,
        \app\admin\middleware\Idempotent::class,
        \app\admin\middleware\OperationLog::class,
    ],
];

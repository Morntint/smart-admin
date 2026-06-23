<?php

/**
 * 后台路由中间件
 *
 * 参与顺序（请求方向）：
 *   AuthMiddleware  →  OperationLog  →  Controller
 *
 * AuthMiddleware：解析 Token、注入用户信息、按菜单 permission 鉴权
 * OperationLog ：捕获写操作请求/响应，落库到 sys_operation_log
 *
 * 全局静态文件中间件（StaticFile）通过 config/static.php 配置。
 * 全局 CORS 中间件（Cors）在 '' 分组，先于任何分组执行，
 * 保证 OPTIONS 预检（含未匹配路由的 404）也能拿到跨域响应头。
 */
return [
    '' => [
        \app\middleware\Cors::class,
    ],
    'admin' => [
        \app\admin\middleware\AuthMiddleware::class,
        \app\admin\middleware\OperationLog::class,
    ],
];

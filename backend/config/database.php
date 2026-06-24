<?php

/**
 * 数据库配置
 *
 * 默认连接：mysql；如需多库，按 connections 数组追加即可。
 *
 * 关键参数：
 *  - charset / collation：统一 utf8mb4，支持 emoji
 *  - PDO::ATTR_EMULATE_PREPARES=false：支持服务端预处理；
 *    Swoole/Swow 协程驱动下必须为 false
 *  - pool：连接池配置（仅在协程驱动下生效）
 *
 * 读写分离（可选）：
 *  - 设置 DB_READ_HOST（从库地址，多个用英文逗号分隔）即自动启用；
 *    未设置则为单库模式，配置结构与原来完全兼容。
 *  - 启用后 Eloquent 自动把 SELECT 路由到从库、写操作路由到主库；
 *    sticky=true 保证「同一请求内写库之后的读」走主库，避免主从延迟读到旧数据。
 *  - 主库地址用 DB_WRITE_HOST（缺省回退 DB_HOST）。
 *  - 详见 docs/READ_WRITE_SPLIT.md。
 */

$pool = [
    'max_connections'    => (int) env('DB_POOL_MAX', 5),
    'min_connections'    => (int) env('DB_POOL_MIN', 1),
    'wait_timeout'       => (int) env('DB_POOL_WAIT', 3),
    'idle_timeout'       => (int) env('DB_POOL_IDLE', 60),
    'heartbeat_interval' => (int) env('DB_POOL_HEARTBEAT', 50),
];

// 连接公共项（不含 host/port，便于单库 / 读写分离两种结构复用）
$common = [
    'driver'    => 'mysql',
    'database'  => env('DB_DATABASE', 'webman_admin'),
    'username'  => env('DB_USERNAME', 'root'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => env('DB_PREFIX', ''),
    'strict'    => true,
    'engine'    => null,
    'options'   => [
        // Swoole / Swow 协程驱动下必须为 false
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
    ],
    'pool' => $pool,
];

$port      = (int) env('DB_PORT', 3306);
$writeHost = (string) env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1'));
$readHosts = array_values(array_filter(array_map('trim', explode(',', (string) env('DB_READ_HOST', '')))));

if ($readHosts !== []) {
    // 读写分离模式：read 走从库（可多个，自动负载均衡），write 走主库
    $mysql = array_merge($common, [
        'read'   => ['host' => $readHosts,    'port' => (int) env('DB_READ_PORT', $port)],
        'write'  => ['host' => [$writeHost],  'port' => (int) env('DB_WRITE_PORT', $port)],
        'sticky' => (bool) env('DB_STICKY', true),
    ]);
} else {
    // 单库模式（与原配置结构一致）
    $mysql = array_merge($common, [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => $port,
    ]);
}

return [
    'default'     => env('DB_DEFAULT', 'mysql'),

    'connections' => [
        'mysql' => $mysql,
    ],
];

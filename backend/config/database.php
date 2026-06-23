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
 */
return [
    'default'     => env('DB_DEFAULT', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => (int) env('DB_PORT', 3306),
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
            'pool' => [
                'max_connections'    => (int) env('DB_POOL_MAX', 5),
                'min_connections'    => (int) env('DB_POOL_MIN', 1),
                'wait_timeout'       => (int) env('DB_POOL_WAIT', 3),
                'idle_timeout'       => (int) env('DB_POOL_IDLE', 60),
                'heartbeat_interval' => (int) env('DB_POOL_HEARTBEAT', 50),
            ],
        ],
    ],
];

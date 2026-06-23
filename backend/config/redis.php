<?php

/**
 * Redis 配置
 *
 * 默认连接：default。如需多库，按需追加：
 *   'cache'   => [..., 'database' => 1],
 *   'session' => [..., 'database' => 2],
 */
return [
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', '') ?: null,
        'database' => (int) env('REDIS_DB', 0),
        'pool'     => [
            'max_connections'    => (int) env('REDIS_POOL_MAX', 5),
            'min_connections'    => (int) env('REDIS_POOL_MIN', 1),
            'wait_timeout'       => (int) env('REDIS_POOL_WAIT', 3),
            'idle_timeout'       => (int) env('REDIS_POOL_IDLE', 60),
            'heartbeat_interval' => (int) env('REDIS_POOL_HEARTBEAT', 50),
        ],
    ],
];

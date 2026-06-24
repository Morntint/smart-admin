<?php

/**
 * redis-queue 连接配置
 *
 * 复用主 Redis 的连接参数（env），队列默认走独立 DB，避免和缓存/会话混用。
 *  - max_attempts ：消费失败重试次数
 *  - retry_seconds：重试间隔（秒）
 */
return [
    'default' => [
        'host'    => 'redis://' . env('REDIS_HOST', '127.0.0.1') . ':' . (int) env('REDIS_PORT', 6379),
        'options' => [
            'auth'          => env('REDIS_PASSWORD', '') ?: null,
            'db'            => (int) env('REDIS_QUEUE_DB', 1),
            'prefix'        => '',
            'max_attempts'  => 5,
            'retry_seconds' => 5,
        ],
    ],
];
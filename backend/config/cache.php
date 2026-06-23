<?php

/**
 * Laravel-style cache config.
 *
 * 默认 file 驱动（无外部依赖），需要时把 CACHE_DRIVER 改成 redis 复用已有 Redis 连接。
 *
 * 用法：
 *   $val = cache('key');                  // 取
 *   cache(['k' => 'v'], 60);              // 写，TTL=60s
 *   cache()->delete('key');               // 删
 */
return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'array' => [
            'driver'    => 'array',
            'serialize' => false,
        ],
        'file' => [
            'driver' => 'file',
            'path'   => runtime_path('cache'),
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
            'lock_connection' => 'default',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'webman_cache'),
];

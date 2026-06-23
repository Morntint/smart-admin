<?php

use Webman\Session\FileSessionHandler;
use Webman\Session\RedisClusterSessionHandler;
use Webman\Session\RedisSessionHandler;

/**
 * Session 配置
 *
 * 本项目以 JWT 鉴权为主，Session 仅用于 CSRF Token 等少量场景。
 * 默认使用文件存储，需高并发场景可切换为 Redis。
 */
return [
    /** 存储类型：file / redis / redis_cluster */
    'type'    => env('SESSION_TYPE', 'file'),

    /** 默认 Handler（type=file 时） */
    'handler' => FileSessionHandler::class,

    'config'  => [
        'file' => [
            'save_path' => runtime_path() . '/sessions',
        ],
        'redis' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'auth'     => env('REDIS_PASSWORD', '') ?: '',
            'timeout'  => 2,
            'database' => env('SESSION_REDIS_DB', ''),
            'prefix'   => 'redis_session_',
        ],
        'redis_cluster' => [
            'host'    => ['127.0.0.1:7000', '127.0.0.1:7001', '127.0.0.1:7001'],
            'timeout' => 2,
            'auth'    => '',
            'prefix'  => 'redis_session_',
        ],
    ],

    'session_name'          => 'PHPSID',
    'auto_update_timestamp' => false,

    /** Session 有效期（秒） */
    'lifetime'              => 7 * 24 * 60 * 60,
    /** Cookie 有效期（秒） */
    'cookie_lifetime'       => 365 * 24 * 60 * 60,
    'cookie_path'           => '/',
    'domain'                => '',

    /** 防止 XSS 读取 Cookie */
    'http_only'             => true,
    /** HTTPS 才发送 Cookie，生产建议 true */
    'secure'                => (bool) env('SESSION_COOKIE_SECURE', false),
    /** SameSite：'Strict' / 'Lax' / 'None' */
    'same_site'             => env('SESSION_SAME_SITE', ''),

    'gc_probability'        => [1, 1000],
];

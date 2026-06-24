<?php

/**
 * Workerman 服务参数
 *
 * 见：http://doc.workerman.net/
 *
 * 关键项：
 *  - event_loop         事件驱动。空=默认（select/event，多进程同步模型）；
 *                       'Swoole' 或 'Swow'=协程驱动，此时 config/database.php、
 *                       config/redis.php 的连接池才真正生效，单进程可并发处理多请求。
 *                       切换前务必确认所有单例无「请求级可变实例字段」
 *                       （AuthMiddleware 已于 P1 改造为无状态），详见 docs/COROUTINE.md。
 *  - stop_timeout       优雅停止超时（秒）
 *  - max_package_size   单个 HTTP 请求最大 body 体积（影响上传大小）
 */
return [
    // 协程驱动：在 .env 设 WM_EVENT_LOOP=Swoole（需 ext-swoole>=5）或 Swow；留空为同步多进程模型
    'event_loop'       => env('WM_EVENT_LOOP', ''),
    'stop_timeout'     => (int) env('WM_STOP_TIMEOUT', 2),
    'pid_file'         => runtime_path() . '/webman.pid',
    'status_file'      => runtime_path() . '/webman.status',
    'stdout_file'      => runtime_path() . '/logs/stdout.log',
    'log_file'         => runtime_path() . '/logs/workerman.log',

    /** 单个请求最大 body 体积，默认 10MB（与上传上限保持一致） */
    'max_package_size' => (int) env('WM_MAX_PACKAGE_SIZE', 10 * 1024 * 1024),
];

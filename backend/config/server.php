<?php

/**
 * Workerman 服务参数
 *
 * 见：http://doc.workerman.net/
 *
 * 关键项：
 *  - stop_timeout       优雅停止超时（秒）
 *  - max_package_size   单个 HTTP 请求最大 body 体积（影响上传大小）
 */
return [
    'event_loop'       => env('WM_EVENT_LOOP', ''),
    'stop_timeout'     => (int) env('WM_STOP_TIMEOUT', 2),
    'pid_file'         => runtime_path() . '/webman.pid',
    'status_file'      => runtime_path() . '/webman.status',
    'stdout_file'      => runtime_path() . '/logs/stdout.log',
    'log_file'         => runtime_path() . '/logs/workerman.log',

    /** 单个请求最大 body 体积，默认 10MB（与上传上限保持一致） */
    'max_package_size' => (int) env('WM_MAX_PACKAGE_SIZE', 10 * 1024 * 1024),
];

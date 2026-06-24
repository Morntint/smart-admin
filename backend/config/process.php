<?php

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

/**
 * 进程配置
 *
 * - webman   ：HTTP 主进程，Worker 数 = CPU 核数 × 4
 * - monitor  ：开发期文件 / 内存监控（仅 Linux + 非 -d 守护模式）
 *
 * 操作日志消费进程由 webman/redis-queue 插件提供，
 * 见 config/plugin/webman/redis-queue/process.php。
 *
 * 注意：监听端口可在 .env 中通过 APP_LISTEN 自定义；生产建议通过 Nginx
 * 反向代理到 0.0.0.0:8787。
 */
return [
    'webman' => [
        'handler'     => Http::class,
        'listen'      => env('APP_LISTEN', 'http://0.0.0.0:8787'),
        'count'       => (int) env('WORKER_COUNT', cpu_count() * 4),
        'user'        => env('WORKER_USER', ''),
        'group'       => env('WORKER_GROUP', ''),
        'reusePort'   => false,
        'eventLoop'   => '',
        'context'     => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger'       => Log::channel('default'),
            'appPath'      => app_path(),
            'publicPath'   => public_path(),
        ],
    ],

    // 文件变更检测 / 自动重载（仅 Linux 下生效，且非 -d 守护模式）
    'monitor' => [
        'handler'     => app\process\Monitor::class,
        'reloadable'  => false,
        'constructor' => [
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'),
               glob(base_path() . '/plugin/*/config'),
               glob(base_path() . '/plugin/*/api')),
            'monitorExtensions' => ['php', 'html', 'htm', 'env'],
            'options' => [
                'enable_file_monitor'   => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * PHPStan / larastan 引导补丁。
 *
 * larastan 默认通过引导完整 Laravel app（bootstrap/app.php）来定义 LARAVEL_VERSION 常量，
 * 但本项目基于 webman + 离散 illuminate 组件，没有 foundation/bootstrap，
 * 会导致 LarastanStubFilesExtension 因常量未定义而 fatal。
 *
 * 这里在 larastan 自身 bootstrap 之前先定义该常量（larastan 内有 !defined 守卫，不会冲突），
 * 版本取实际安装的 illuminate/database 主版本。
 */
if (! defined('LARAVEL_VERSION')) {
    $version = '12.0.0';

    $installed = dirname(__DIR__) . '/vendor/composer/installed.json';
    if (is_file($installed)) {
        $data = json_decode((string) file_get_contents($installed), true);
        $packages = $data['packages'] ?? $data ?? [];
        foreach ($packages as $package) {
            if (($package['name'] ?? '') === 'illuminate/database') {
                $version = ltrim((string) ($package['version'] ?? $version), 'v');
                break;
            }
        }
    }

    define('LARAVEL_VERSION', $version);
}

/**
 * larastan 的部分扩展会调用 Laravel 的路径 helper（database_path 等），
 * 这些在 webman 下不存在。补充最小桩函数仅供静态分析期使用。
 */
if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/database' . ($path !== '' ? '/' . $path : '');
    }
}
if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/app' . ($path !== '' ? '/' . $path : '');
    }
}
if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path !== '' ? '/' . $path : '');
    }
}
if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/config' . ($path !== '' ? '/' . $path : '');
    }
}
if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/resource' . ($path !== '' ? '/' . $path : '');
    }
}
if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/runtime' . ($path !== '' ? '/' . $path : '');
    }
}

// 加载 Collection 宏（toTree 等），让 PHPStan 识别动态注册的方法。
require_once __DIR__ . '/app/functions.php';

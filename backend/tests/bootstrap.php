<?php

/**
 * PHPUnit 测试引导
 *
 * 加载 Composer 自动加载、.env、全部配置与框架 helper 函数，
 * 但不启动 workerman 服务——纯单测/逻辑测试无需常驻进程。
 *
 * 测试默认用 array 缓存驱动（无外部依赖）；需要 DB/Redis 的集成测试
 * 由各测试用例自行跳过或在 CI 里连真实服务。
 */

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env（存在则加载；CI/本地缺省也能跑纯逻辑测试）
if (class_exists(Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
    Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();
}

// 测试环境强制无外部依赖的缓存驱动
putenv('CACHE_DRIVER=array');
$_ENV['CACHE_DRIVER'] = $_SERVER['CACHE_DRIVER'] = 'array';

// 时区
date_default_timezone_set('Asia/Shanghai');

// 加载全部配置（排除 route：注解路由扫描依赖运行时）
support\App::loadAllConfig(['route']);

// helper 函数（functions.php / helper.php）：loadAllConfig 不会加载 autoload.files，
// 这里按 config/autoload.php 声明显式 require，使测试能用 cache()/now_datetime() 等。
foreach ((array) config('autoload.files', []) as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

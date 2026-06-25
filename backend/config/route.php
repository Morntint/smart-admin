<?php
/**
 * 路由配置
 *
 * 本项目使用 **注解路由**（#[Get / #[Post / #[Put / #[Delete / #[Patch]，
 * 由 `webman/console` 在启动时扫描 app/admin/controller 下的注解并自动注册。
 *
 * 因此本文件不需要手动注册路由。
 *
 * 如需额外注册非注解路由（如静态 alias、catch-all 兜底），可在此追加：
 *
 *   use Webman\Route;
 *   Route::get('/health', function () { return json(['code' => 200, 'msg' => 'ok']); });
 *
 * 详见：https://www.workerman.net/doc/webman/route.html
 */

use Webman\Route;

// 健康检查（Docker HEALTHCHECK 用）
Route::get('/ping', function () {
    return json(['code' => 200, 'msg' => 'pong']);
});

// Prometheus 指标端点（鉴权由 MetricsController 内的 METRICS_TOKEN 控制）。
// 显式注册而非注解路由：保持与业务控制器（app/admin）解耦，且不挂 admin 中间件链。
Route::get('/metrics', [app\controller\MetricsController::class, 'index']);

// Swagger UI 页面。静态文件处理不会把 /swagger 解析为目录下的 index.html，
// 故显式注册路由返回该页面（同时兼容 /swagger 与 /swagger/）。
Route::get('/swagger[/]', function () {
    $file = public_path() . '/swagger/index.html';
    return is_file($file)
        ? response(file_get_contents($file))->withHeader('Content-Type', 'text/html; charset=utf-8')
        : json(['code' => 404, 'msg' => 'swagger UI 未找到']);
});

// 浏览器跨域预检（OPTIONS）兜底路由。
// 注解路由只注册了 GET/POST/PUT/DELETE 等业务方法，OPTIONS 预检会落到 404 fallback，
// 而 404 fallback 绕过中间件链，导致 Cors 中间件无法注入跨域头、预检失败。
// 在此注册 catch-all 的 OPTIONS 路由，使其命中 FOUND 分支、走中间件链（Cors 注入头并返回 204）。
Route::options('[{path:.+}]', function () {
    return response('', 204);
});

return [];

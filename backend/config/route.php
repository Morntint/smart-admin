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

// 浏览器跨域预检（OPTIONS）兜底路由。
// 注解路由只注册了 GET/POST/PUT/DELETE 等业务方法，OPTIONS 预检会落到 404 fallback，
// 而 404 fallback 绕过中间件链，导致 Cors 中间件无法注入跨域头、预检失败。
// 在此注册 catch-all 的 OPTIONS 路由，使其命中 FOUND 分支、走中间件链（Cors 注入头并返回 204）。
Route::options('[{path:.+}]', function () {
    return response('', 204);
});

return [];

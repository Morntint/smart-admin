<?php

/**
 * 静态文件配置
 *
 * - enable：是否允许 webman 直接处理 public/ 目录下的静态资源
 *           生产环境建议交给 Nginx 处理
 * - middleware：静态资源专用中间件链
 *           推荐启用 StaticFile 拦截 /. 隐藏文件路径
 */
return [
    'enable'     => true,
    'middleware' => [
        \app\middleware\StaticFile::class,
    ],
];

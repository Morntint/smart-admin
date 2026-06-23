<?php

/**
 * 应用核心配置
 */

use support\Request;

return [
    /** 调试模式：开启后将返回完整异常堆栈，不要在生产环境开启 */
    'debug'             => (bool) env('APP_DEBUG', false),

    /** 错误等级 */
    'error_reporting'   => E_ALL,

    /** 默认时区 */
    'default_timezone'  => env('APP_TIMEZONE', 'Asia/Shanghai'),

    /** Request 类（已扩展并增加用户字段类型提示） */
    'request_class'     => Request::class,

    /** 静态资源根目录 */
    'public_path'       => base_path() . DIRECTORY_SEPARATOR . 'public',

    /** 运行时目录（缓存、日志、Session） */
    'runtime_path'      => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',

    /** 控制器后缀 */
    'controller_suffix' => 'Controller',

    /**
     * 控制器是否复用：
     *  - false：每次请求创建新实例（更安全，避免实例字段污染）
     *  - true ：实例在请求间复用（稍快但需保证无状态）
     */
    'controller_reuse'  => (bool) env('CONTROLLER_REUSE', false),
];

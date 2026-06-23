<?php

/**
 * 全局异常处理器
 *
 * 默认所有未声明专属处理器的异常，都走 app\exception\Handler。
 * 业务异常 / 参数校验异常会被处理为标准 JSON 响应，详见 Handler::render。
 */
return [
    '' => app\exception\Handler::class,
];

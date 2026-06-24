<?php

use app\common\log\TraceProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * 日志配置（基于 Monolog）
 *
 * - 默认 channel：default
 * - 文件按天滚动，保留 30 天（可通过 LOG_MAX_FILES 调整）
 * - 行格式包含时间、上下文与堆栈
 * - processor：TraceProcessor 把 request_id 注入每条日志 extra，
 *   配合 LineFormatter 末位的 extra 输出实现链路聚合（见 app\middleware\Trace）
 *
 * 使用：
 *   support\Log::info('msg', ['key' => 'value']);
 *   support\Log::error($e->getMessage(), ['exception' => $e]);
 */
return [
    'default' => [
        'handlers' => [
            [
                'class'       => RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/webman.log',
                    (int) env('LOG_MAX_FILES', 30),
                    Logger::DEBUG,
                ],
                'formatter'   => [
                    'class'       => LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true, true],
                ],
            ],
        ],
        'processors' => [
            ['class' => TraceProcessor::class],
        ],
    ],
];

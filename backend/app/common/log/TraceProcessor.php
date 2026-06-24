<?php

namespace app\common\log;

use app\common\support\TraceContext;
use Monolog\LogRecord;

/**
 * 链路追踪日志 Processor
 *
 * 把当前请求的 request_id 注入到每条日志的 extra 中，
 * 配合 LineFormatter 的 extra 输出（config/log.php 已开启），
 * 即可在日志行尾看到 {"request_id":"..."}，按 id 聚合一次请求的所有日志。
 *
 * 兼容性：Monolog 3.x 传入 LogRecord 对象；2.x 传入 array。
 * 本项目依赖 monolog/monolog ^2.0，但此处对两种形态都做了处理，便于后续升级。
 */
class TraceProcessor
{
    /**
     * @param LogRecord|array<string,mixed> $record
     * @return LogRecord|array<string,mixed>
     */
    public function __invoke($record)
    {
        $requestId = TraceContext::id();
        if ($requestId === '') {
            return $record;
        }

        // Monolog 3.x：LogRecord 值对象
        if ($record instanceof LogRecord) {
            $record->extra['request_id'] = $requestId;
            return $record;
        }

        // Monolog 2.x：数组
        $record['extra']['request_id'] = $requestId;
        return $record;
    }
}

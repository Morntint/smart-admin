<?php

namespace app\bootstrap;

use app\common\support\TraceContext;
use Illuminate\Database\Events\QueryExecuted;
use support\Db;
use support\Log;
use Throwable;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * 慢查询监控引导
 *
 * 在每个 Worker 启动时注册一次 DB 查询监听器，对执行耗时超过阈值的 SQL
 * 写入慢查询日志，带 request_id（链路追踪）与绑定参数，便于定位与优化。
 *
 * 阈值：SLOW_QUERY_MS（毫秒，默认 200）；设为 0 可关闭监控。
 *
 * 注册：config/bootstrap.php。Worker::$id 仅在 workerman 进程内有值，
 *      命令行/非 worker 上下文（$worker 为 null）直接跳过，避免 CLI 报错。
 *
 * 日志去向：log channel `default`（runtime/logs/webman.log），
 *      已由 TraceProcessor 自动附加 request_id。
 */
class SlowQueryLogger implements Bootstrap
{
    /** 默认慢查询阈值（毫秒） */
    private const DEFAULT_THRESHOLD_MS = 200;

    /** 记录到日志的 SQL 最大长度（防止超长语句撑爆日志） */
    private const MAX_SQL_LEN = 2000;

    public static function start(?Worker $worker): void
    {
        // 非 worker 上下文（如 console 命令）跳过
        if ($worker === null) {
            return;
        }

        $threshold = (int) env('SLOW_QUERY_MS', self::DEFAULT_THRESHOLD_MS);
        if ($threshold <= 0) {
            return;
        }

        try {
            Db::connection()->listen(function (QueryExecuted $query) use ($threshold) {
                if ($query->time < $threshold) {
                    return;
                }
                self::logSlowQuery($query, $threshold);
            });
        } catch (Throwable $e) {
            // 监听注册失败不应阻断 Worker 启动
            Log::warning('SlowQueryLogger 注册失败: ' . $e->getMessage());
        }
    }

    /**
     * 写慢查询日志。
     */
    private static function logSlowQuery(QueryExecuted $query, int $threshold): void
    {
        try {
            $sql = $query->sql;
            if (strlen($sql) > self::MAX_SQL_LEN) {
                $sql = substr($sql, 0, self::MAX_SQL_LEN) . '...(truncated)';
            }

            Log::warning('Slow query', [
                'connection'   => $query->connectionName,
                'time_ms'      => round($query->time, 2),
                'threshold_ms' => $threshold,
                'sql'          => $sql,
                'bindings'     => self::safeBindings($query->bindings),
                'request_id'   => TraceContext::id(),
            ]);
        } catch (Throwable) {
            // 静默：日志失败不影响业务
        }
    }

    /**
     * 绑定参数转为可记录的标量数组（对象/资源转字符串，防 json 失败）。
     *
     * @param array<int|string,mixed> $bindings
     * @return array<int|string,scalar|null>
     */
    private static function safeBindings(array $bindings): array
    {
        $out = [];
        foreach ($bindings as $k => $v) {
            $out[$k] = match (true) {
                is_scalar($v), $v === null => $v,
                $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
                default => '[' . get_debug_type($v) . ']',
            };
        }
        return $out;
    }
}

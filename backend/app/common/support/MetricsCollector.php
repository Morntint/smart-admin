<?php

namespace app\common\support;

use support\Redis;
use Throwable;

/**
 * 指标采集器（Prometheus 风格）
 *
 * 由于 workerman 是多进程模型，各 Worker 内存独立，指标必须聚合到 Redis 才能
 * 在单次 /metrics 抓取里反映全局。本采集器用 Redis Hash 累积计数：
 *  - 请求总数（按 method + 状态码段 200/4xx/5xx）
 *  - 请求耗时直方图（累积桶，单位毫秒）
 *  - 各 Worker 当前内存（按 worker_id）
 *
 * 全部操作 try/catch fail-open：监控不可用绝不影响业务。
 *
 * 采集入口：app\middleware\Metrics（全局中间件）
 * 输出入口：app\controller\MetricsController::index → /metrics
 */
class MetricsCollector
{
    /** Redis key 前缀 */
    private const PREFIX = 'metrics:';

    /** 请求计数 Hash：field = "METHOD|statusClass" */
    private const KEY_REQUESTS = self::PREFIX . 'http_requests_total';

    /** 耗时直方图 Hash：field = 桶上界（ms），value = 落入该桶及以下的累计数 */
    private const KEY_DURATION = self::PREFIX . 'http_duration_bucket';

    /** 耗时求和（ms）与计数，用于算平均 */
    private const KEY_DURATION_SUM   = self::PREFIX . 'http_duration_sum_ms';
    private const KEY_DURATION_COUNT = self::PREFIX . 'http_duration_count';

    /** 各 Worker 内存 Hash：field = worker_id，value = 字节 */
    private const KEY_WORKER_MEM = self::PREFIX . 'worker_memory_bytes';

    /** 直方图桶上界（毫秒），与 Prometheus 习惯一致 */
    private const BUCKETS = [5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000];

    /**
     * 记录一次请求。
     *
     * @param string $method     HTTP 方法
     * @param int    $statusCode HTTP 状态码
     * @param float  $durationMs 耗时（毫秒）
     * @param int    $workerId   workerman Worker id
     */
    public static function observe(string $method, int $statusCode, float $durationMs, int $workerId): void
    {
        try {
            $method = strtoupper($method) ?: 'UNKNOWN';
            $class  = self::statusClass($statusCode);

            Redis::hIncrBy(self::KEY_REQUESTS, $method . '|' . $class, 1);

            // 累积直方图：落入每个 >= 当前耗时的桶
            foreach (self::BUCKETS as $bound) {
                if ($durationMs <= $bound) {
                    Redis::hIncrBy(self::KEY_DURATION, (string) $bound, 1);
                }
            }
            Redis::hIncrBy(self::KEY_DURATION, '+Inf', 1);

            // 求和与计数（hIncrByFloat 求和，便于算 avg）
            Redis::hIncrByFloat(self::KEY_DURATION_SUM, 'sum', round($durationMs, 2));
            Redis::hIncrBy(self::KEY_DURATION_COUNT, 'count', 1);

            // 当前 Worker 内存（覆盖式写入，反映实时值）
            Redis::hSet(self::KEY_WORKER_MEM, (string) $workerId, (string) memory_get_usage(true));
        } catch (Throwable) {
            // fail-open
        }
    }

    /**
     * 导出全部指标为 Prometheus 文本格式。
     */
    public static function render(): string
    {
        $lines = [];

        try {
            // 1. 请求计数
            $requests = Redis::hGetAll(self::KEY_REQUESTS) ?: [];
            $lines[] = '# HELP http_requests_total Total HTTP requests by method and status class.';
            $lines[] = '# TYPE http_requests_total counter';
            foreach ($requests as $field => $count) {
                [$method, $class] = array_pad(explode('|', (string) $field), 2, 'unknown');
                $lines[] = sprintf(
                    'http_requests_total{method="%s",status="%s"} %d',
                    self::escape($method),
                    self::escape($class),
                    (int) $count
                );
            }

            // 2. 耗时直方图
            $buckets = Redis::hGetAll(self::KEY_DURATION) ?: [];
            $sum     = (float) (Redis::hGet(self::KEY_DURATION_SUM, 'sum') ?: 0);
            $count   = (int) (Redis::hGet(self::KEY_DURATION_COUNT, 'count') ?: 0);

            $lines[] = '# HELP http_request_duration_ms Request latency histogram in milliseconds.';
            $lines[] = '# TYPE http_request_duration_ms histogram';
            foreach (self::BUCKETS as $bound) {
                $lines[] = sprintf(
                    'http_request_duration_ms_bucket{le="%d"} %d',
                    $bound,
                    (int) ($buckets[(string) $bound] ?? 0)
                );
            }
            $lines[] = sprintf('http_request_duration_ms_bucket{le="+Inf"} %d', (int) ($buckets['+Inf'] ?? $count));
            $lines[] = sprintf('http_request_duration_ms_sum %s', self::num($sum));
            $lines[] = sprintf('http_request_duration_ms_count %d', $count);

            // 3. 各 Worker 内存
            $mem = Redis::hGetAll(self::KEY_WORKER_MEM) ?: [];
            $lines[] = '# HELP worker_memory_bytes Resident memory per worker process.';
            $lines[] = '# TYPE worker_memory_bytes gauge';
            foreach ($mem as $workerId => $bytes) {
                $lines[] = sprintf('worker_memory_bytes{worker="%s"} %d', self::escape((string) $workerId), (int) $bytes);
            }

            // 4. 队列堆积（尽力而为，失败忽略）
            $backlog = self::queueBacklog();
            if ($backlog !== null) {
                $lines[] = '# HELP queue_pending_jobs Pending jobs in redis-queue waiting list.';
                $lines[] = '# TYPE queue_pending_jobs gauge';
                foreach ($backlog as $queue => $n) {
                    $lines[] = sprintf('queue_pending_jobs{queue="%s"} %d', self::escape($queue), $n);
                }
            }
        } catch (Throwable $e) {
            $lines[] = '# metrics partially unavailable: ' . self::escape($e->getMessage());
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * 读取已知队列的堆积长度（best-effort）。
     *
     * redis-queue 的等待列表 key 为 "{redis-queue}-waiting{queue}"，位于队列专用 DB。
     *
     * @return array<string,int>|null null 表示无法读取
     */
    private static function queueBacklog(): ?array
    {
        $queues = ['operation_log'];
        try {
            $conn = \Webman\RedisQueue\Redis::connection('default');
            /** @var \Redis $client */
            $client = $conn->client();
            $out = [];
            foreach ($queues as $q) {
                $out[$q] = (int) $client->lLen('{redis-queue}-waiting' . $q);
            }
            return $out;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 重置全部指标（运维/测试用）。
     */
    public static function reset(): void
    {
        try {
            Redis::del(
                self::KEY_REQUESTS,
                self::KEY_DURATION,
                self::KEY_DURATION_SUM,
                self::KEY_DURATION_COUNT,
                self::KEY_WORKER_MEM
            );
        } catch (Throwable) {
        }
    }

    /**
     * 状态码归类：2xx / 4xx / 5xx / other。
     */
    private static function statusClass(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300 => '2xx',
            $status >= 400 && $status < 500 => '4xx',
            $status >= 500                  => '5xx',
            default                          => 'other',
        };
    }

    /**
     * 转义 Prometheus 标签值。
     */
    private static function escape(string $v): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $v);
    }

    /**
     * 数字格式化（避免科学计数法）。
     */
    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
    }
}

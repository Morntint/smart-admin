<?php

namespace app\queue\redis;

use app\model\SysOperationLog;
use Webman\RedisQueue\Consumer;

/**
 * 操作日志消费者
 *
 * 消费 OperationLog 中间件投递的日志，写入 sys_operation_log。
 * 由 webman/redis-queue 的 consumer 进程自动调度（config/plugin/webman/redis-queue/process.php）。
 *
 * 消费失败（抛异常）时，redis-queue 会按 redis.php 的 max_attempts/retry_seconds 自动重试，
 * 多次失败后进入失败队列，不会阻塞或丢失。
 */
class OperationLogConsumer implements Consumer
{
    /** 订阅的队列名，需与投递端 Redis::send() 的 queue 一致 */
    public string $queue = 'operation_log';

    /** 连接名，对应 config/plugin/webman/redis-queue/redis.php */
    public string $connection = 'default';

    /**
     * 消费一条日志：整行数据已在投递端组装好，直接落库。
     *
     * @param array<string,mixed> $data
     */
    public function consume($data): void
    {
        SysOperationLog::create($data);
    }
}
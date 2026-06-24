<?php

namespace app\common\support;

use RuntimeException;

/**
 * 雪花算法（Snowflake）ID 生成器
 *
 * 生成 64bit 全局唯一、趋势递增的整数 ID，适合分库分表/分布式主键，
 * 替代数据库自增（避免暴露数据量、避免分库主键冲突）。
 *
 * 位结构（共 63 位，最高位符号位恒为 0 保证正数）：
 *   | 1 bit 符号位(0) | 41 bit 毫秒时间戳 | 10 bit 机器ID | 12 bit 序列号 |
 *
 *  - 41 bit 时间戳：相对自定义纪元的毫秒数，约可用 69 年
 *  - 10 bit 机器ID：0~1023，多机部署时每个进程/实例分配唯一值（WORKER_ID）
 *  - 12 bit 序列号：同一毫秒内自增，单机每毫秒最多 4096 个，超出则等待下一毫秒
 *
 * 配置（.env）：
 *  - SNOWFLAKE_WORKER_ID：本实例机器 ID（0~1023），多实例务必各不相同
 *  - SNOWFLAKE_EPOCH    ：起始纪元毫秒（可选，默认 2024-01-01）
 *
 * 用法：
 *   $id = Snowflake::next();          // 生成一个 ID
 *
 * 注意：进程内单例 + 同步生成，序列号与上次时间戳为可变状态。
 *      多进程（workerman 多 Worker）下各进程独立实例，靠 machine ID 区分；
 *      因此**同一台机器的多个 Worker 必须共用同一 WORKER_ID 时**会有冲突风险——
 *      见下方 resolveWorkerId()：默认用 (WORKER_ID*?)，建议每 Worker 不同，
 *      或将 10 bit 拆为 5 bit 数据中心 + 5 bit 机器，由运维规划。
 */
class Snowflake
{
    /** 默认纪元：2024-01-01 00:00:00 UTC 的毫秒数 */
    private const DEFAULT_EPOCH = 1704067200000;

    /** 各段位宽 */
    private const WORKER_ID_BITS = 10;
    private const SEQUENCE_BITS  = 12;

    /** 最大机器 ID（2^10 - 1 = 1023） */
    private const MAX_WORKER_ID = (1 << self::WORKER_ID_BITS) - 1;

    /** 序列号掩码（2^12 - 1 = 4095） */
    private const SEQUENCE_MASK = (1 << self::SEQUENCE_BITS) - 1;

    /** 位移量 */
    private const WORKER_ID_SHIFT = self::SEQUENCE_BITS;                          // 12
    private const TIMESTAMP_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS;   // 22

    private static ?self $instance = null;

    private int $epoch;
    private int $workerId;
    private int $sequence = 0;
    private int $lastTimestamp = -1;

    private function __construct()
    {
        $this->epoch    = (int) (env('SNOWFLAKE_EPOCH', self::DEFAULT_EPOCH));
        $this->workerId = $this->resolveWorkerId();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 生成下一个 ID（便捷静态入口）。
     */
    public static function next(): int
    {
        return self::getInstance()->generate();
    }

    /**
     * 生成下一个 64bit ID。
     *
     * @throws RuntimeException 时钟回拨时拒绝生成，避免 ID 重复
     */
    public function generate(): int
    {
        $timestamp = $this->now();

        // 时钟回拨：当前时间早于上次生成时间 → 拒绝，避免重复 ID
        if ($timestamp < $this->lastTimestamp) {
            $offset = $this->lastTimestamp - $timestamp;
            // 小幅回拨（<=5ms）等待追平；大幅回拨直接抛错由上层处理
            if ($offset <= 5) {
                usleep(($offset + 1) * 1000);
                $timestamp = $this->now();
                if ($timestamp < $this->lastTimestamp) {
                    throw new RuntimeException("时钟回拨，拒绝生成 ID（回拨 {$offset}ms）");
                }
            } else {
                throw new RuntimeException("时钟回拨过大（{$offset}ms），拒绝生成 ID");
            }
        }

        if ($timestamp === $this->lastTimestamp) {
            // 同一毫秒内自增序列；溢出则自旋到下一毫秒
            $this->sequence = ($this->sequence + 1) & self::SEQUENCE_MASK;
            if ($this->sequence === 0) {
                $timestamp = $this->waitNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - $this->epoch) << self::TIMESTAMP_SHIFT)
            | ($this->workerId << self::WORKER_ID_SHIFT)
            | $this->sequence;
    }

    /**
     * 当前毫秒时间戳。
     */
    private function now(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * 自旋等待直到进入下一毫秒。
     */
    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->now();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->now();
        }
        return $timestamp;
    }

    /**
     * 解析机器 ID：优先 env，校验范围；缺省时用主机名哈希兜底（降低同机冲突概率）。
     */
    private function resolveWorkerId(): int
    {
        $configured = env('SNOWFLAKE_WORKER_ID', null);

        if ($configured !== null && $configured !== '') {
            $id = (int) $configured;
            if ($id < 0 || $id > self::MAX_WORKER_ID) {
                throw new RuntimeException(
                    sprintf('SNOWFLAKE_WORKER_ID 超出范围 0~%d：%d', self::MAX_WORKER_ID, $id)
                );
            }
            return $id;
        }

        // 未配置：用主机名 + PID 哈希落到 0~1023，尽量分散（生产建议显式配置）
        $seed = gethostname() . '|' . getmypid();
        return crc32($seed) % (self::MAX_WORKER_ID + 1);
    }
}

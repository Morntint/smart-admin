<?php

namespace app\common\support;

use support\Redis;
use Throwable;

/**
 * 基于 Redis 的分布式锁
 *
 * 用 `SET key token NX PX ttl` 原子抢锁，释放时用 Lua 校验持有者 token 后再删，
 * 避免「A 的锁过期后 B 拿到锁，A 执行完误删 B 的锁」。
 *
 * 适用场景：
 *  - 定时任务防重入（多实例部署时只允许一个实例执行）
 *  - 库存扣减 / 计数等并发写的临界区保护
 *  - 任何「同一时刻只允许一个执行者」的业务
 *
 * 与 CacheGuard 内置锁的区别：
 *  - CacheGuard 的锁是「缓存回源」专用，fail-open（拿不到锁也回源）；
 *  - 本类是「业务互斥」专用，**fail-safe**（拿不到锁就拒绝执行），且 Redis 故障时抛异常，
 *    由调用方决定如何处理（业务互斥不能在 Redis 故障时静默放行）。
 *
 * 用法：
 *   // 手动管理
 *   $token = Lock::acquire('cron:cleanup', 30);
 *   if ($token === null) { return; } // 没抢到，别人正在执行
 *   try { ... } finally { Lock::release('cron:cleanup', $token); }
 *
 *   // 自动管理（推荐）
 *   $ran = Lock::withLock('cron:cleanup', 30, function () { ... });
 *   if (!$ran) { /* 未获得锁，已跳过 *​/ }
 */
class Lock
{
    /** 锁键统一前缀 */
    private const PREFIX = 'lock:';

    /** 默认锁持有时长（秒） */
    private const DEFAULT_TTL = 30;

    /** 释放锁的 Lua：仅当持有者 token 匹配才删除（防误删） */
    private const RELEASE_LUA = <<<'LUA'
if redis.call('GET', KEYS[1]) == ARGV[1] then
    return redis.call('DEL', KEYS[1])
end
return 0
LUA;

    /**
     * 尝试获取锁（非阻塞）。
     *
     * @param string $name 锁名（业务标识）
     * @param int    $ttl  锁持有时长（秒），到期自动释放，防止持锁者崩溃死锁
     * @return string|null 成功返回锁 token（释放时需回传）；未抢到返回 null
     * @throws \RuntimeException Redis 不可用时抛出（业务互斥不可静默放行）
     */
    public static function acquire(string $name, int $ttl = self::DEFAULT_TTL): ?string
    {
        try {
            $token = bin2hex(random_bytes(16));
            // SET key token NX PX ttl_ms：不存在才写并设毫秒级过期
            $ok = Redis::set(self::PREFIX . $name, $token, 'PX', max(1, $ttl) * 1000, 'NX');
            return $ok ? $token : null;
        } catch (Throwable $e) {
            throw new \RuntimeException('分布式锁 Redis 不可用: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 阻塞式获取锁：在 waitSeconds 内轮询重试，超时仍未获得返回 null。
     *
     * @param string $name        锁名
     * @param int    $ttl         锁持有时长（秒）
     * @param float  $waitSeconds 最长等待秒数
     * @param int    $retryMs     每次重试间隔（毫秒）
     */
    public static function acquireBlocking(
        string $name,
        int $ttl = self::DEFAULT_TTL,
        float $waitSeconds = 3.0,
        int $retryMs = 100
    ): ?string {
        $deadline = self::nowFloat() + $waitSeconds;
        do {
            $token = self::acquire($name, $ttl);
            if ($token !== null) {
                return $token;
            }
            usleep($retryMs * 1000);
        } while (self::nowFloat() < $deadline);

        return null;
    }

    /**
     * 释放锁（校验 token，避免误删他人锁）。
     *
     * @return bool 是否成功删除（token 不匹配/已过期返回 false）
     */
    public static function release(string $name, string $token): bool
    {
        try {
            return (int) Redis::eval(self::RELEASE_LUA, 1, self::PREFIX . $name, $token) > 0;
        } catch (Throwable) {
            // 释放失败不致命：锁会随 PX 自动过期
            return false;
        }
    }

    /**
     * 在锁保护下执行回调（自动获取与释放）。
     *
     * @template T
     * @param string       $name     锁名
     * @param int          $ttl      锁持有时长（秒）
     * @param callable():T $callback 临界区逻辑
     * @return T|false 获得锁并执行返回回调结果；未获得锁返回 false
     * @throws \RuntimeException Redis 不可用
     */
    public static function withLock(string $name, int $ttl, callable $callback): mixed
    {
        $token = self::acquire($name, $ttl);
        if ($token === null) {
            return false;
        }
        try {
            return $callback();
        } finally {
            self::release($name, $token);
        }
    }

    /**
     * 当前时间（浮点秒）。封装便于在协程/测试环境替换。
     */
    private static function nowFloat(): float
    {
        return microtime(true);
    }
}

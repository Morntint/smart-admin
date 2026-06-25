<?php

namespace app\common\support;

use support\Redis;
use Throwable;

/**
 * 缓存防护门面
 *
 * 在「读缓存 → 未命中 → 回源 → 写缓存」的经典流程上叠加三层防护：
 *  - 防穿透：回源结果为 null 时也缓存一个空值标记（短 TTL），
 *           避免恶意/无效 key 每次都打到数据库；
 *  - 防击穿：热点 key 失效瞬间用 Redis 互斥锁，仅放一个请求回源，
 *           其余短暂等待后读新值（fail-open：拿不到锁/Redis 故障则自行回源，不阻塞）；
 *  - 防雪崩：写入正常值时给 TTL 叠加随机抖动，避免大量 key 同时过期。
 *
 * 值的存取复用 cache() 帮助器（file/redis/array 驱动通用）；
 * 互斥锁依赖 Redis（用一段 Lua 保证「校验持有者后删除」的原子性）。
 *
 * 用法：
 *   $cfg = CacheGuard::remember('system_config', 86400, fn() => SysConfig::loadAll());
 *   $user = CacheGuard::remember("user_{$id}", 300, fn() => SysUser::find($id)); // null 也会被短缓存
 */
class CacheGuard
{
    /** 回源结果为 null 时写入的空值标记 */
    private const NULL_SENTINEL = '__CACHE_NULL__';

    /** 空值缓存 TTL（秒）——穿透防护，短一些避免脏数据滞留 */
    private const NULL_TTL = 30;

    /** 互斥锁默认持有时长（秒）——防止持锁进程崩溃导致死锁 */
    private const LOCK_TTL = 10;

    /** 未抢到锁时的最大等待轮次与每轮毫秒数 */
    private const WAIT_TRIES = 20;
    private const WAIT_MS    = 50;

    /** TTL 抖动比例（±10%） */
    private const JITTER_RATIO = 0.1;

    /** 释放锁的 Lua：仅当 value 等于自己的 token 才删除 */
    private const UNLOCK_LUA = <<<'LUA'
if redis.call('GET', KEYS[1]) == ARGV[1] then
    return redis.call('DEL', KEYS[1])
end
return 0
LUA;

    /**
     * 带防护的「记住」：命中直接返回；未命中则回源并写缓存。
     *
     * @template T
     * @param string        $key      缓存键
     * @param int           $ttl      正常值 TTL（秒），内部会叠加抖动
     * @param callable():T  $callback 回源闭包
     * @param float|null    $jitter   抖动比例覆盖（0 表示禁用抖动）；默认用内置 10%
     * @return T|null
     */
    public static function remember(string $key, int $ttl, callable $callback, ?float $jitter = null): mixed
    {
        // 1. 先读缓存（含空值标记）
        $hit = self::readValue($key);
        if ($hit['exists']) {
            return $hit['value'];
        }

        // 2. 抢互斥锁，防止并发回源击穿
        $lockKey = 'cache_lock:' . $key;
        $token   = self::acquireLock($lockKey);

        if ($token === null) {
            // 没抢到锁：短暂等待持锁者回填，期间轮询缓存
            for ($i = 0; $i < self::WAIT_TRIES; $i++) {
                usleep(self::WAIT_MS * 1000);
                $hit = self::readValue($key);
                if ($hit['exists']) {
                    return $hit['value'];
                }
            }
            // 仍未拿到（持锁者慢/挂了）：兜底自行回源，避免请求饿死
            return self::regenerate($key, $ttl, $callback, $jitter);
        }

        // 3. 持锁者：double-check 后回源
        try {
            $hit = self::readValue($key);
            if ($hit['exists']) {
                return $hit['value'];
            }
            return self::regenerate($key, $ttl, $callback, $jitter);
        } finally {
            self::releaseLock($lockKey, $token);
        }
    }

    /**
     * 回源并写入缓存（null → 空值标记，否则带抖动 TTL）。
     *
     * @template T
     * @param callable():T $callback
     * @return T|null
     */
    private static function regenerate(string $key, int $ttl, callable $callback, ?float $jitter): mixed
    {
        $value = $callback();

        if ($value === null) {
            cache([$key => self::NULL_SENTINEL], self::NULL_TTL);
            return null;
        }

        cache([$key => $value], self::withJitter($ttl, $jitter));
        return $value;
    }

    /**
     * 读取缓存值，区分「真未命中」与「缓存的空值」。
     *
     * @return array{exists:bool,value:mixed}
     */
    private static function readValue(string $key): array
    {
        $raw = cache($key);
        if ($raw === null) {
            return ['exists' => false, 'value' => null];
        }
        if ($raw === self::NULL_SENTINEL) {
            return ['exists' => true, 'value' => null];
        }
        return ['exists' => true, 'value' => $raw];
    }

    /**
     * 给 TTL 叠加随机抖动（防雪崩）。抖动量基于 key 长度做确定性偏移，
     * 避免依赖 Math/rand 的同时仍能打散过期时间。
     */
    private static function withJitter(int $ttl, ?float $jitter): int
    {
        $ratio = $jitter ?? self::JITTER_RATIO;
        if ($ratio <= 0 || $ttl <= 1) {
            return $ttl;
        }
        $span = (int) ceil($ttl * $ratio);
        if ($span <= 0) {
            return $ttl;
        }
        // 用随机字节生成 [0, 2*span] 的偏移，落在 [ttl-span, ttl+span]
        try {
            $offset = (ord(random_bytes(1)) % (2 * $span + 1)) - $span;
        } catch (Throwable) {
            $offset = 0;
        }
        return max(1, $ttl + $offset);
    }

    /**
     * 抢锁：SET key token NX EX ttl。失败/Redis 不可用返回 null（fail-open）。
     */
    private static function acquireLock(string $lockKey): ?string
    {
        try {
            $token = bin2hex(random_bytes(8));
            // illuminate/redis 风格：set(key, value, 'EX', ttl, 'NX')，
            // 内部转为 phpredis 的 SET key value EX ttl NX（原子「不存在才写并设过期」）
            $ok = Redis::set($lockKey, $token, 'EX', self::LOCK_TTL, 'NX');
            return $ok ? $token : null;
        } catch (Throwable) {
            // Redis 故障：返回 null，调用方会自行回源（不加锁）
            return null;
        }
    }

    /**
     * 释放锁（校验 token，避免误删他人锁）。
     */
    private static function releaseLock(string $lockKey, string $token): void
    {
        try {
            Redis::eval(self::UNLOCK_LUA, 1, $lockKey, $token);
        } catch (Throwable) {
            // 静默：锁会随 EX 自动过期
        }
    }

    /**
     * 主动失效一个 key（业务写操作后调用）。
     */
    public static function forget(string $key): void
    {
        cache()->delete($key);
    }
}

<?php

namespace app\common\support;

use support\Redis;
use Throwable;

/**
 * 基于 Redis 的固定窗口限流器
 *
 * 用一段 Lua 脚本保证「计数 + 首次设置过期」的原子性，避免高并发下
 * INCR 与 EXPIRE 之间的竞态（计数器永不过期）。
 *
 * 返回结构便于中间件写响应头（X-RateLimit-* / Retry-After）：
 *   [
 *     'allowed'   => bool,  // 是否放行
 *     'limit'     => int,   // 窗口上限
 *     'remaining' => int,   // 剩余可用次数
 *     'reset'     => int,   // 距窗口重置的秒数
 *   ]
 *
 * 容错：Redis 不可用时「放行」（fail-open），避免限流组件自身故障导致全站不可用。
 */
class RateLimiter
{
    /**
     * KEYS[1] = 计数键；ARGV[1] = 窗口秒数；ARGV[2] = 上限
     * 返回 {当前计数, 剩余 TTL}
     */
    private const LUA = <<<'LUA'
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('TTL', KEYS[1])
if ttl < 0 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
    ttl = tonumber(ARGV[1])
end
return {current, ttl}
LUA;

    /** 计数键统一前缀 */
    private const PREFIX = 'rate_limit:';

    /**
     * 命中一次并判断是否超限。
     *
     * @param string $identifier 限流标识（已含维度，如 "login:ip:1.2.3.4"）
     * @param int    $limit      窗口内最大次数
     * @param int    $window     窗口秒数
     * @return array{allowed:bool,limit:int,remaining:int,reset:int}
     */
    public static function hit(string $identifier, int $limit, int $window): array
    {
        $key = self::PREFIX . $identifier;

        try {
            /** @var array{0:int,1:int} $res */
            $res     = Redis::eval(self::LUA, 1, $key, $window, $limit);
            $current = (int) ($res[0] ?? 0);
            $ttl     = (int) ($res[1] ?? $window);
        } catch (Throwable) {
            // fail-open：限流器故障不应拖垮业务
            return ['allowed' => true, 'limit' => $limit, 'remaining' => $limit, 'reset' => $window];
        }

        $remaining = max(0, $limit - $current);

        return [
            'allowed'   => $current <= $limit,
            'limit'     => $limit,
            'remaining' => $remaining,
            'reset'     => $ttl > 0 ? $ttl : $window,
        ];
    }

    /**
     * 读取当前计数（不增加），用于「失败计数」类场景判断是否已锁定。
     */
    public static function attempts(string $identifier): int
    {
        try {
            return (int) Redis::get(self::PREFIX . $identifier);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * 累加一次计数并返回累加后的值（首次设置窗口过期），用于登录失败计数。
     */
    public static function increment(string $identifier, int $window): int
    {
        $key = self::PREFIX . $identifier;
        try {
            /** @var array{0:int,1:int} $res */
            $res = Redis::eval(self::LUA, 1, $key, $window, PHP_INT_MAX);
            return (int) ($res[0] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * 清除某标识的计数（如登录成功后清除失败计数）。
     */
    public static function clear(string $identifier): void
    {
        try {
            Redis::del(self::PREFIX . $identifier);
        } catch (Throwable) {
            // 静默忽略
        }
    }
}

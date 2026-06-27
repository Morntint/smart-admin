<?php

namespace app\common\WeChat;

use Psr\SimpleCache\CacheInterface;
use support\Cache;
use support\Config;

/**
 * 微信缓存适配器
 *
 * 将 EasyWeChat 的缓存接口适配到 webman 的缓存系统。
 *
 * 默认 TTL 选 7100s（< 微信 access_token 的 7200s）：
 * 一旦 cache 命中后 SDK 不会主动刷新，TTL 比真实有效期略短可让 SDK 在临近过期时
 * 自然回源刷新一次新 token，避免被微信端实际过期的 token 卡在 redis 里。
 */
class WeChatCache implements CacheInterface
{
    /** 默认 TTL（秒），可被 config('wechat.cache.default_ttl') 覆盖 */
    protected const DEFAULT_TTL = 7100;

    /** 缓存前缀 */
    protected string $prefix;

    public function __construct(?string $prefix = null)
    {
        $this->prefix = ($prefix ?? Config::get('wechat.cache.namespace', 'wechat')) . ':';
    }

    protected function defaultTtl(): int
    {
        return (int) Config::get('wechat.cache.default_ttl', self::DEFAULT_TTL);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefix . $key, $default);
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            $seconds = (new \DateTimeImmutable())->add($ttl)->getTimestamp() - time();
            $ttl = max(1, $seconds);
        }

        return Cache::set($this->prefix . $key, $value, $ttl ?? $this->defaultTtl());
    }

    public function delete(string $key): bool
    {
        return Cache::delete($this->prefix . $key);
    }

    /**
     * 清空缓存
     *
     * 适配器无法逐条枚举 key，返回 false 提示调用方需手动失效或重启 worker。
     */
    public function clear(): bool
    {
        return false;
    }

    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return Cache::has($this->prefix . $key);
    }
}

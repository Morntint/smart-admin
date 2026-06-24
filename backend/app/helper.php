<?php

use app\model\SysConfig;

/**
 * 应用级 helper 函数
 *
 * 仅放与业务无关的通用工具（缓存、系统配置等）。
 * 业务相关 helper（如权限判断、密码哈希）请放在 app/functions.php。
 */

// -----------------------------------------------------------------------------
// 系统配置（基于 sys_config 表 + 缓存）
// -----------------------------------------------------------------------------

if (!function_exists('sysConfig')) {
    /**
     * 获取系统配置值。
     *
     * @param string $key     配置键
     * @param mixed  $default 默认值
     */
    function sysConfig(string $key, mixed $default = null): mixed
    {
        return SysConfig::getConfig($key, $default);
    }
}

// -----------------------------------------------------------------------------
// 缓存（cache()）- 基于 illuminate/cache，支持 file/redis/array 驱动
// -----------------------------------------------------------------------------

if (!class_exists('Cache', false)) {
    /**
     * 缓存门面，统一封装 file / redis / array 驱动，
     * 内部惰性创建 Repository 实例。
     */
    class Cache
    {
        private static ?\Illuminate\Contracts\Cache\Repository $store = null;

        /**
         * 获取（首次调用时按配置惰性创建）底层缓存 Repository。
         */
        public static function store(): \Illuminate\Contracts\Cache\Repository
        {
            if (self::$store) {
                return self::$store;
            }

            $cfg       = config('cache', []);
            $driver    = $cfg['default'] ?? 'file';
            $storeCfg  = $cfg['stores'][$driver] ?? ['driver' => $driver];
            $prefix    = $cfg['prefix']   ?? '';

            $inner = match ($storeCfg['driver'] ?? $driver) {
                'array' => new \Illuminate\Cache\ArrayStore(),
                'redis' => new \Illuminate\Cache\RedisStore(
                    \support\Redis::instance()->connection($storeCfg['connection'] ?? 'default'),
                    $prefix
                ),
                default => new \Illuminate\Cache\FileStore(
                    new \Illuminate\Filesystem\Filesystem(),
                    $storeCfg['path']       ?? runtime_path('cache'),
                    $storeCfg['permission'] ?? null
                ),
            };

            return self::$store = new \Illuminate\Cache\Repository($inner);
        }

        /**
         * 读取缓存。
         */
        public static function get(string $key): mixed
        {
            return self::store()->get($key);
        }

        /**
         * 写入缓存（null ttl 表示永久）。
         */
        public static function set(string $key, mixed $value, ?int $ttl = null): bool
        {
            $ttl === null
                ? self::store()->forever($key, $value)
                : self::store()->put($key, $value, $ttl);
            return true;
        }

        public static function has(string $key): bool
        {
            return self::store()->has($key);
        }

        /**
         * 批量删除缓存键。
         */
        public static function delete(string ...$keys): bool
        {
            foreach ($keys as $k) {
                self::store()->forget($k);
            }
            return true;
        }
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存访问器（与 Laravel 风格一致）。
     *
     * - cache('key')                        → 取值
     * - cache(['k' => 'v'], 60)             → 批量写入
     * - cache()                             → 流式 API
     *
     * @param string|array<string,mixed>|null $key
     */
    function cache(string|array|null $key = null, ?int $ttl = null): mixed
    {
        if (is_string($key)) {
            return Cache::get($key);
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Cache::set((string) $k, $v, $ttl);
            }
            return true;
        }
        return new class {
            public function get(string $k): mixed   { return Cache::get($k); }
            public function set(string $k, mixed $v, ?int $ttl = null): bool { return Cache::set($k, $v, $ttl); }
            public function put(string $k, mixed $v, $ttl): bool { return Cache::set($k, $v, is_int($ttl) ? $ttl : null); }
            public function has(string $k): bool    { return Cache::has($k); }
            public function delete(string ...$keys): bool { return Cache::delete(...$keys); }
        };
    }
}

if (!function_exists('cache_remember')) {
    /**
     * 带防护的缓存「记住」：命中返回，未命中回源并写缓存。
     *
     * 内置防穿透（空值缓存）、防击穿（互斥锁回源）、防雪崩（TTL 抖动），
     * 详见 app\common\support\CacheGuard。
     *
     * 用法：
     *   $cfg = cache_remember('system_config', 86400, fn() => SysConfig::loadAll());
     *
     * @template T
     * @param string       $key
     * @param int          $ttl      正常值 TTL（秒）
     * @param callable():T $callback 回源闭包
     * @return T|null
     */
    function cache_remember(string $key, int $ttl, callable $callback): mixed
    {
        return \app\common\support\CacheGuard::remember($key, $ttl, $callback);
    }
}


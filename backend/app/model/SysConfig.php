<?php

namespace app\model;

/**
 * 系统配置模型
 *
 * 表：sys_config
 *
 * 业务约束：
 *  - key 全局唯一，是配置的访问入口
 *  - 任意写操作必须调用 clearCache() 清缓存（getConfig 走静态缓存）
 *  - type 决定 getTypedValue() 返回值的类型
 *
 * @property string               $name
 * @property string               $key
 * @property string|null          $value
 * @property string               $type
 * @property string               $group
 * @property string|null          $options
 * @property int                  $sort
 * @property string|null          $remark
 */
class SysConfig extends BaseModel
{
    /** 字符串 */
    public const TYPE_STRING  = 'string';
    /** 数字 */
    public const TYPE_NUMBER  = 'number';
    /** 布尔 */
    public const TYPE_BOOLEAN = 'boolean';
    /** JSON */
    public const TYPE_JSON    = 'json';

    /** 缓存 key（全部配置） */
    private const CACHE_KEY    = 'system_config';
    /** 缓存 TTL（24 小时） */
    private const CACHE_TTL    = 86400;

    protected $table = 'sys_config';

    protected $fillable = [
        'name',
        'key',
        'value',
        'type',
        'group',
        'options',
        'sort',
        'remark',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     * 获取配置值（带静态缓存 + 持久缓存）。
     *
     * @template T
     * @param string $key
     * @param T      $default
     * @return T|mixed
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        static $configs = null;

        if ($configs === null) {
            // cache_remember：命中直接返回；未命中时用互斥锁防止并发回源击穿，
            // 高并发下大量请求同时遇到缓存失效也只有一个查库（见 CacheGuard）。
            $configs = cache_remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $map = [];
                foreach (self::query()->get() as $item) {
                    $map[$item->key] = $item->value;
                }
                return $map;
            }) ?? [];
        }

        return $configs[$key] ?? $default;
    }

    /**
     * 获取配置值并转换为对应类型。
     */
    public static function getTypedValue(string $key, mixed $default = null): mixed
    {
        $config = self::where('key', $key)->first();
        if (!$config) {
            return $default;
        }

        return match ($config->type) {
            self::TYPE_NUMBER  => (float) $config->value,
            self::TYPE_BOOLEAN => (bool)  $config->value,
            self::TYPE_JSON    => json_decode((string) $config->value, true),
            default            => $config->value,
        };
    }

    /**
     * 清除配置缓存。
     */
    public static function clearCache(): void
    {
        cache()->delete(self::CACHE_KEY, 'sys_config_map');
    }

    /**
     * 更新配置后清除缓存。
     *
     * @param array<string,mixed> $data
     */
    public function updateData(array $data): bool
    {
        $result = parent::updateData($data);
        self::clearCache();
        return $result;
    }
}

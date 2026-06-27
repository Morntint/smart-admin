<?php

namespace app\common\WeChat;

use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use EasyWeChat\OfficialAccount\Application as OfficialAccount;
use EasyWeChat\MiniApp\Application as MiniProgram;
use EasyWeChat\OpenPlatform\Application as OpenPlatform;
use EasyWeChat\Pay\Application as Pay;
use EasyWeChat\Work\Application as Work;
use app\model\SysConfig;
use support\Cache;
use support\Config;
use support\Log;

/**
 * 微信应用工厂类
 * 统一管理 EasyWeChat 各应用实例
 */
class WeChatFactory
{
    /**
     * 应用实例缓存
     * @var array<string, OfficialAccount|MiniProgram|OpenPlatform|Pay|Work>
     */
    protected static array $instances = [];

    /**
     * 获取公众号应用
     *
     * @param string|null $appId 自定义 AppID（多公众号场景）
     * @param array $customConfig 自定义配置
     * @return OfficialAccount
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public static function officialAccount(?string $appId = null, array $customConfig = []): OfficialAccount
    {
        $key = 'official_account_' . ($appId ?: 'default');

        if (!isset(self::$instances[$key])) {
            // 优先从数据库读取配置，若无则 fallback 到配置文件
            $config = self::getWechatConfig('official_account');

            if ($appId) {
                $config['app_id'] = $appId;
            }

            $config = array_merge($config, $customConfig);
            self::validateConfig($config, ['app_id', 'secret']);

            $app = new OfficialAccount($config);
            $app->setCache(self::getCacheAdapter());

            self::$instances[$key] = $app;
        }

        return self::$instances[$key];
    }

    /**
     * 获取小程序应用
     *
     * @param string|null $appId 自定义 AppID（多小程序场景）
     * @param array $customConfig 自定义配置
     * @return MiniProgram
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public static function miniProgram(?string $appId = null, array $customConfig = []): MiniProgram
    {
        $key = 'mini_program_' . ($appId ?: 'default');

        if (!isset(self::$instances[$key])) {
            // 优先从数据库读取配置，若无则 fallback 到配置文件
            $config = self::getWechatConfig('mini_program');

            if ($appId) {
                $config['app_id'] = $appId;
            }

            $config = array_merge($config, $customConfig);
            self::validateConfig($config, ['app_id', 'secret']);

            $app = new MiniProgram($config);
            $app->setCache(self::getCacheAdapter());

            self::$instances[$key] = $app;
        }

        return self::$instances[$key];
    }

    /**
     * 获取开放平台应用
     *
     * @param array $customConfig 自定义配置
     * @return OpenPlatform
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public static function openPlatform(array $customConfig = []): OpenPlatform
    {
        $key = 'open_platform';

        if (!isset(self::$instances[$key])) {
            // 优先从数据库读取配置，若无则 fallback 到配置文件
            $config = array_merge(self::getWechatConfig('open_platform'), $customConfig);
            self::validateConfig($config, ['app_id', 'secret', 'token', 'aes_key']);

            $app = new OpenPlatform($config);
            $app->setCache(self::getCacheAdapter());

            self::$instances[$key] = $app;
        }

        return self::$instances[$key];
    }

    /**
     * 获取企业微信应用
     *
     * @param array $customConfig 自定义配置
     * @return Work
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public static function work(array $customConfig = []): Work
    {
        $key = 'work';

        if (!isset(self::$instances[$key])) {
            // 优先从数据库读取配置，若无则 fallback 到配置文件
            $config = array_merge(self::getWechatConfig('work'), $customConfig);
            self::validateConfig($config, ['corp_id', 'secret']);

            $app = new Work($config);
            $app->setCache(self::getCacheAdapter());

            self::$instances[$key] = $app;
        }

        return self::$instances[$key];
    }

    /**
     * 获取微信支付应用（EasyWeChat 6 / API v3 形态）
     *
     * @param array $customConfig 自定义配置
     * @return Pay
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public static function pay(array $customConfig = []): Pay
    {
        $key = 'pay';

        if (!isset(self::$instances[$key])) {
            $config = array_merge(self::getWechatConfig('pay'), $customConfig);
            self::validateConfig($config, ['mch_id', 'v3_secret_key', 'private_key', 'certificate']);

            $app = new Pay($config);
            $app->setCache(self::getCacheAdapter());

            self::$instances[$key] = $app;
        }

        return self::$instances[$key];
    }

    /**
     * 获取缓存适配器
     * 使用 webman/redis 作为缓存驱动
     *
     * @return WeChatCache
     */
    protected static function getCacheAdapter(): WeChatCache
    {
        return new WeChatCache();
    }

    /**
     * 数据库配置 key 映射
     * 应用类型 => [EasyWeChat配置键 => 数据库sys_config.key]
     */
    protected const CONFIG_KEY_MAP = [
        'official_account' => [
            'app_id'  => 'wechat_official_appid',
            'secret'  => 'wechat_official_secret',
            'token'   => 'wechat_official_token',
            'aes_key' => 'wechat_official_aes_key',
        ],
        'mini_program' => [
            'app_id'  => 'wechat_mini_appid',
            'secret'  => 'wechat_mini_secret',
            'token'   => 'wechat_mini_token',
            'aes_key' => 'wechat_mini_aes_key',
        ],
        'open_platform' => [
            'app_id'  => 'wechat_open_appid',
            'secret'  => 'wechat_open_secret',
            'token'   => 'wechat_open_token',
            'aes_key' => 'wechat_open_aes_key',
        ],
        'work' => [
            'corp_id'  => 'wechat_work_corp_id',
            'agent_id' => 'wechat_work_agent_id',
            'secret'   => 'wechat_work_secret',
            'token'    => 'wechat_work_token',
            'aes_key'  => 'wechat_work_aes_key',
        ],
        'pay' => [
            'app_id'         => 'wechat_pay_appid',
            'mch_id'         => 'wechat_pay_mch_id',
            'secret_key'     => 'wechat_pay_secret_key',
            'v3_secret_key'  => 'wechat_pay_v3_key',
            'private_key'    => 'wechat_pay_private_key',
            'certificate'    => 'wechat_pay_certificate',
            'notify_url'     => 'wechat_pay_notify_url',
        ],
    ];

    /**
     * 从数据库读取微信配置，若无则 fallback 到配置文件
     *
     * @param string $appType 应用类型
     * @return array
     */
    protected static function getWechatConfig(string $appType): array
    {
        // 先从配置文件获取基础配置（包含 http、response_type 等）
        $config = Config::get("wechat.{$appType}", []);

        // 从数据库读取配置项，覆盖配置文件的值
        $keyMap = self::CONFIG_KEY_MAP[$appType] ?? [];
        foreach ($keyMap as $configKey => $dbKey) {
            $value = SysConfig::getConfig($dbKey);
            if ($value !== null && $value !== '') {
                $config[$configKey] = $value;
            }
        }

        return $config;
    }

    /**
     * 验证配置
     *
     * @param array $config 配置数组
     * @param array $requiredKeys 必填键
     * @throws InvalidConfigException
     */
    protected static function validateConfig(array $config, array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || $config[$key] === '' || $config[$key] === null) {
                throw new InvalidConfigException("微信配置项 [{$key}] 不能为空，请检查系统配置或环境变量");
            }
        }
    }

    /**
     * 清除指定应用实例
     *
     * @param string|null $key 应用键，为空则清除所有
     */
    public static function clearInstance(?string $key = null): void
    {
        if ($key) {
            unset(self::$instances[$key]);
        } else {
            self::$instances = [];
        }
    }

    /**
     * 安全调用微信 API（带异常捕获和日志）
     *
     * 注意：成功分支只记录调用名称，不把响应体落入日志——
     * 微信很多接口返回 access_token / openid / session_key 等敏感字段，
     * 落明文日志会扩大泄露面。需要排查问题时请按调用链单独打开 trace。
     *
     * @param callable    $callback  实际执行的回调
     * @param string      $operation 操作名（用于日志）
     * @return array|null 失败返回 null
     */
    public static function safeCall(callable $callback, string $operation = ''): ?array
    {
        try {
            $result = $callback();

            if ($operation !== '') {
                Log::info('[WeChat] api ok', ['op' => $operation]);
            }

            return is_array($result) ? $result : null;
        } catch (\Throwable $e) {
            Log::error('[WeChat] api failed', [
                'op'      => $operation,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

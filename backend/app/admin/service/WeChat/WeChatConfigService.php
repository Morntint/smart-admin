<?php

namespace app\admin\service\WeChat;

use app\admin\service\BaseService;
use app\common\WeChat\WeChatFactory;
use app\model\SysConfig;

/**
 * 微信配置服务
 *
 * 设计要点：
 *  - 敏感字段（secret / aes_key / token / pay_key / private_key）默认在 groups() 中以
 *    "********" 占位返回，避免明文下行到前端；前端表单按 has_value 决定是否禁用编辑。
 *  - 单字段明文需通过专门的 getSecret(key) 接口、并由具备
 *    `wechat:config:view-secret` 权限的账号显式触发。
 *  - 批量更新走显式白名单，未在白名单的 key 一律忽略；空字符串视为"不更新"。
 */
class WeChatConfigService extends BaseService
{
    /** 掩码占位符 */
    public const MASK = '********';

    /** 敏感字段后缀正则（命中即在 groups() 中掩码） */
    protected const SENSITIVE_PATTERN = '/_secret$|_aes_key$|_token$|_pay_key$|_private_key$/';

    /**
     * 可更新的 wechat 配置 key 白名单（与 WeChatFactory::CONFIG_KEY_MAP 同源）
     *
     * @var string[]
     */
    public const ALLOWED_KEYS = [
        // official_account
        'wechat_official_appid',
        'wechat_official_secret',
        'wechat_official_token',
        'wechat_official_aes_key',
        // mini_program
        'wechat_mini_appid',
        'wechat_mini_secret',
        'wechat_mini_token',
        'wechat_mini_aes_key',
        // open_platform
        'wechat_open_appid',
        'wechat_open_secret',
        'wechat_open_token',
        'wechat_open_aes_key',
        // work
        'wechat_work_corp_id',
        'wechat_work_agent_id',
        'wechat_work_secret',
        'wechat_work_token',
        'wechat_work_aes_key',
        // pay (v3 形态)
        'wechat_pay_appid',
        'wechat_pay_mch_id',
        'wechat_pay_secret_key',
        'wechat_pay_v3_key',
        'wechat_pay_private_key',
        'wechat_pay_certificate',
        'wechat_pay_notify_url',
        // pay 旧字段（兼容老部署）
        'wechat_pay_key',
        'wechat_pay_cert_path',
        'wechat_pay_key_path',
    ];

    /**
     * 获取微信配置分组列表
     *
     * 返回结构（每个 item）：
     *   {
     *     id, name, key, value: '********' | 实际值, type, group, sort, remark,
     *     is_secret: bool,    // 是否敏感字段
     *     has_value: bool     // 后端是否已存值（不暴露明文，但允许 UI 区分"未配置" vs "已配置"）
     *   }
     */
    public function groups(): array
    {
        $configs = SysConfig::where('group', 'wechat')
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        $groups = [
            'official_account' => ['name' => '微信公众号',     'icon' => 'wechat',  'items' => []],
            'mini_program'     => ['name' => '微信小程序',     'icon' => 'app',     'items' => []],
            'open_platform'    => ['name' => '微信开放平台',   'icon' => 'global',  'items' => []],
            'work'             => ['name' => '企业微信',       'icon' => 'company', 'items' => []],
            'pay'              => ['name' => '微信支付',       'icon' => 'money',   'items' => []],
        ];

        foreach ($configs as $config) {
            $isSecret = $this->isSensitiveKey($config['key']);
            $hasValue = $config['value'] !== null && $config['value'] !== '';

            if ($isSecret) {
                $config['value'] = $hasValue ? self::MASK : '';
            }
            $config['is_secret'] = $isSecret;
            $config['has_value'] = $hasValue;

            $appType = $this->detectAppType($config['key']);
            if (isset($groups[$appType])) {
                $groups[$appType]['items'][] = $config;
            }
        }

        return $groups;
    }

    /**
     * 获取单个敏感字段的明文（需权限调用方校验，本方法仅做返回）
     */
    public function getSecret(string $key): ?string
    {
        if (!$this->isSensitiveKey($key) || !in_array($key, self::ALLOWED_KEYS, true)) {
            return null;
        }

        $row = SysConfig::where('key', $key)->first();
        return $row?->value;
    }

    /**
     * 批量更新微信配置
     *
     * - 仅写入白名单内的 key；
     * - 空字符串 / null 视为"不更新"（用于占位掩码场景）；
     * - 写入成功后清除持久缓存并失效相关应用实例。
     *
     * @param array<string,mixed> $data       key => value 数组
     * @param int                 $operatorId 操作人 ID
     * @return int 更新的数量
     */
    public function batchUpdate(array $data, int $operatorId): int
    {
        $count = 0;
        $touchedAppTypes = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }
            // 空值视为"不变"，避免把数据库里的真值意外清空
            if ($value === null || $value === '' || $value === self::MASK) {
                continue;
            }

            $affected = SysConfig::where('key', $key)->update([
                'value'      => (string) $value,
                'updated_by' => $operatorId,
            ]);
            if ($affected > 0) {
                $count++;
                $touchedAppTypes[$this->detectAppType($key)] = true;
            }
        }

        if ($count > 0) {
            SysConfig::clearCache();

            // 失效已实例化的微信应用，避免长连接进程内继续使用旧 secret
            foreach (array_keys($touchedAppTypes) as $appType) {
                if ($appType !== 'other') {
                    WeChatFactory::clearInstance($appType . '_default');
                }
            }
        }

        return $count;
    }

    /**
     * 根据配置键识别应用类型
     *
     * 采用前缀匹配（wechat_official_ / wechat_mini_ / wechat_open_ / wechat_work_ / wechat_pay_），
     * 避免使用 str_contains 时被字段名内其它子串误伤。
     */
    protected function detectAppType(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'wechat_official_') => 'official_account',
            str_starts_with($key, 'wechat_mini_')     => 'mini_program',
            str_starts_with($key, 'wechat_open_')     => 'open_platform',
            str_starts_with($key, 'wechat_work_')     => 'work',
            str_starts_with($key, 'wechat_pay_')      => 'pay',
            default                                   => 'other',
        };
    }

    protected function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match(self::SENSITIVE_PATTERN, $key);
    }
}

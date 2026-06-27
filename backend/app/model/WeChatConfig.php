<?php

namespace app\model;

/**
 * 微信配置模型
 */
class WeChatConfig extends BaseModel
{
    protected $table = 'wechat_config';

    protected $fillable = [
        'name',
        'app_type',
        'app_id',
        'secret',
        'token',
        'aes_key',
        'mch_id',
        'pay_key',
        'cert_path',
        'key_path',
        'extra',
        'is_default',
        'status',
    ];

    protected $hidden = [
        'secret',
        'pay_key',
    ];

    protected $casts = [
        'extra' => 'json',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    /**
     * 获取默认配置
     */
    public static function getDefault(string $appType = 'official_account'): ?self
    {
        return self::where('app_type', $appType)
            ->where('is_default', 1)
            ->where('status', 1)
            ->first();
    }

    /**
     * 获取所有启用的配置
     */
    public static function getActiveByType(string $appType): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('app_type', $appType)
            ->where('status', 1)
            ->orderBy('is_default', 'desc')
            ->get();
    }

    /**
     * 转换为 EasyWeChat 配置格式
     */
    public function toEasyWechatConfig(): array
    {
        $config = [
            'app_id' => $this->app_id,
            'secret' => $this->secret,
            'token' => $this->token,
            'aes_key' => $this->aes_key,
            'response_type' => 'array',
        ];

        // 企业微信特殊处理
        if ($this->app_type === 'work') {
            $config['corp_id'] = $this->app_id;
            unset($config['app_id']);
            if ($this->extra['agent_id'] ?? null) {
                $config['agent_id'] = $this->extra['agent_id'];
            }
        }

        // 微信支付特殊处理
        if ($this->app_type === 'pay') {
            $config['mch_id'] = $this->mch_id;
            $config['key'] = $this->pay_key;
            $config['cert_path'] = $this->cert_path;
            $config['key_path'] = $this->key_path;
        }

        // 合并扩展配置
        if ($this->extra) {
            $config = array_merge($config, $this->extra);
        }

        return $config;
    }
}

<?php

namespace app\model;

/**
 * 微信消息模板模型
 */
class WeChatTemplate extends BaseModel
{
    protected $table = 'wechat_template';

    protected $fillable = [
        'app_type',
        'template_id',
        'title',
        'primary_industry',
        'deputy_industry',
        'content',
        'example',
        'params',
        'status',
    ];

    protected $casts = [
        'params' => 'json',
        'status' => 'boolean',
    ];

    /**
     * 根据模板ID获取
     */
    public static function findByTemplateId(string $templateId, string $appType = 'official_account'): ?self
    {
        return self::where('template_id', $templateId)
            ->where('app_type', $appType)
            ->where('status', 1)
            ->first();
    }
}

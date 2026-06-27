<?php

namespace app\model;

/**
 * 微信素材模型
 */
class WeChatMaterial extends BaseModel
{
    protected $table = 'wechat_material';

    protected $fillable = [
        'app_type',
        'media_id',
        'type',
        'title',
        'description',
        'url',
        'local_path',
        'extra',
        'sync_time',
    ];

    protected $casts = [
        'extra' => 'json',
        'sync_time' => 'datetime',
    ];

    /**
     * 根据媒体ID获取
     */
    public static function findByMediaId(string $mediaId, string $appType = 'official_account'): ?self
    {
        return self::where('media_id', $mediaId)
            ->where('app_type', $appType)
            ->first();
    }
}

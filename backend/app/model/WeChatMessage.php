<?php

namespace app\model;

/**
 * 微信消息记录模型
 */
class WeChatMessage extends BaseModel
{
    protected $table = 'wechat_message';

    protected $fillable = [
        'msg_id',
        'app_type',
        'msg_type',
        'event',
        'from_user',
        'to_user',
        'content',
        'receive_time',
        'send_status',
        'send_time',
        'send_result',
    ];

    protected $casts = [
        'receive_time' => 'datetime',
        'send_time' => 'datetime',
    ];

    /**
     * 消息类型作用域
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('msg_type', $type);
    }

    /**
     * 用户消息作用域
     */
    public function scopeByUser($query, string $openid)
    {
        return $query->where('from_user', $openid);
    }
}

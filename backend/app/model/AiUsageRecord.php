<?php

namespace app\model;

/**
 * AI 用量统计表
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $agent_id
 * @property string      $model_name
 * @property int         $prompt_tokens
 * @property int         $completion_tokens
 * @property int         $total_tokens
 * @property float       $cost
 * @property string|null $endpoint
 * @property int         $duration
 * @property int         $status
 * @property string|null $error_msg
 */
class AiUsageRecord extends BaseModel
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'ai_usage_record';

    protected $fillable = [
        'user_id', 'agent_id', 'model_name', 'prompt_tokens',
        'completion_tokens', 'total_tokens', 'cost', 'endpoint',
        'duration', 'status', 'error_msg',
    ];

    protected $casts = [
        'user_id'           => 'integer',
        'agent_id'          => 'integer',
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens'      => 'integer',
        'cost'              => 'float',
        'duration'          => 'integer',
        'status'            => 'integer',
    ];

    public $timestamps = false;
}

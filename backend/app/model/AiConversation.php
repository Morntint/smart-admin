<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI 对话会话表
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $agent_id
 * @property string      $title
 * @property int         $round_count
 * @property int         $total_tokens
 * @property float       $total_cost
 * @property int         $status
 * @property array|null  $selected_tool_ids 用户选择的工具 ID 列表（JSON 数组）
 */
class AiConversation extends BaseModel
{
    protected $table = 'ai_conversation';

    protected $fillable = [
        'user_id', 'agent_id', 'title',
        'round_count', 'total_tokens', 'total_cost', 'status',
    ];

    protected $casts = [
        'user_id'           => 'integer',
        'agent_id'          => 'integer',
        'round_count'       => 'integer',
        'total_tokens'      => 'integer',
        'total_cost'        => 'float',
        'status'            => 'integer',
        'selected_tool_ids' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class, 'conversation_id');
    }
}

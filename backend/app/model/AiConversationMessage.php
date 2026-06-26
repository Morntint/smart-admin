<?php

namespace app\model;

/**
 * AI 对话消息表
 *
 * @property int        $id
 * @property int        $conversation_id
 * @property int        $round_index
 * @property string     $role      user/assistant/system/tool
 * @property string     $content
 * @property array|null $tool_calls
 * @property string|null $tool_call_id  工具调用ID（tool角色用）
 * @property string|null $name          工具名称（tool角色用）
 * @property array|null $token_usage
 * @property float      $cost
 * @property int        $duration
 * @property string|null $model_name
 */
class AiConversationMessage extends BaseModel
{
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM    = 'system';
    public const ROLE_TOOL      = 'tool';

    protected $table = 'ai_conversation_message';

    protected $fillable = [
        'conversation_id', 'round_index', 'role', 'content',
        'tool_calls', 'tool_call_id', 'name',
        'token_usage', 'cost', 'duration', 'model_name',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'round_index'     => 'integer',
        'tool_calls'      => 'json',
        'token_usage'     => 'json',
        'cost'            => 'float',
        'duration'        => 'integer',
    ];
}

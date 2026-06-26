<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Agent 与工具的关联表（多对多）
 *
 * @property int $id
 * @property int $agent_id
 * @property int $tool_id
 * @property array|null $config  针对此 Agent 的工具配置覆盖
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read AiAgent $agent
 * @property-read AiTool  $tool
 */
class AiAgentToolRelation extends Pivot
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'ai_agent_tool_relation';

    protected $fillable = [
        'agent_id', 'tool_id', 'config',
    ];

    protected $casts = [
        'config' => 'json',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(AiTool::class, 'tool_id');
    }
}

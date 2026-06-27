<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * AI 工具库表
 * 全局工具定义，可被多个 Agent 绑定使用
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code
 * @property string|null $description
 * @property string      $tool_type
 * @property array|null  $parameters_schema
 * @property string|null $handler
 * @property array|null  $config
 * @property int         $status
 * @property int         $sort
 * @property int|null    $created_by
 * @property int|null    $updated_by
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<AiAgent> $agents
 */
class AiTool extends BaseModel
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'ai_tool';

    protected $fillable = [
        'name', 'code', 'description', 'tool_type',
        'parameters_schema', 'handler', 'config', 'status', 'sort',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'parameters_schema' => 'json',
        'config'            => 'json',
        'status'            => 'integer',
        'sort'              => 'integer',
    ];

    /**
     * 绑定此工具的所有 Agent
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            AiAgent::class,
            'ai_agent_tool_relation',
            'tool_id',
            'agent_id'
        )->withTimestamps();
    }
}

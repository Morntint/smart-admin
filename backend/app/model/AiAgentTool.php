<?php

namespace app\model;

/**
 * Agent 工具绑定表
 *
 * @property int         $id
 * @property int         $agent_id
 * @property string      $name
 * @property string      $code
 * @property string|null $description
 * @property string      $tool_type
 * @property array|null  $parameters_schema
 * @property string|null $handler
 * @property array|null  $config
 * @property int         $status
 * @property int         $sort
 */
class AiAgentTool extends BaseModel
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'ai_agent_tool';

    protected $fillable = [
        'agent_id', 'name', 'code', 'description', 'tool_type',
        'parameters_schema', 'handler', 'config', 'status', 'sort',
    ];

    protected $casts = [
        'agent_id'          => 'integer',
        'parameters_schema' => 'json',
        'config'            => 'json',
        'status'            => 'integer',
        'sort'              => 'integer',
    ];
}

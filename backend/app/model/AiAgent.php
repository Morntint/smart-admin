<?php

namespace app\model;

use app\model\AiTool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI Agent 定义表
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code
 * @property string|null $icon
 * @property string|null $description
 * @property int         $model_id
 * @property string|null $system_prompt
 * @property string|null $welcome_message
 * @property array|null  $suggested_questions
 * @property int         $max_history_rounds
 * @property float|null  $temperature
 * @property int|null    $max_tokens
 * @property array|null  $knowledge_base_ids
 * @property int         $is_public
 * @property int         $is_streaming
 * @property int         $status
 * @property int         $sort
 *
 * @property-read AiModel $model
 * @property-read \Illuminate\Database\Eloquent\Collection<AiTool> $tools
 */
class AiAgent extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ai_agent';

    protected $fillable = [
        'name', 'code', 'icon', 'description', 'model_id',
        'system_prompt', 'welcome_message', 'suggested_questions',
        'max_history_rounds', 'temperature', 'max_tokens',
        'knowledge_base_ids', 'is_public', 'is_streaming',
        'status', 'sort', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'model_id'           => 'integer',
        'max_history_rounds' => 'integer',
        'temperature'        => 'float',
        'max_tokens'         => 'integer',
        'suggested_questions' => 'json',
        'knowledge_base_ids' => 'json',
        'is_public'          => 'integer',
        'is_streaming'       => 'integer',
        'status'             => 'integer',
        'sort'               => 'integer',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    /**
     * Agent 绑定的工具（多对多）
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(
            AiTool::class,
            'ai_agent_tool_relation',
            'agent_id',
            'tool_id'
        )->withTimestamps();
    }
}

<?php

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI 提示词模板表
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code
 * @property string      $category
 * @property string|null $description
 * @property string      $content
 * @property array|null  $variables
 * @property int         $is_system
 * @property int         $status
 * @property int         $sort
 */
class AiPromptTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ai_prompt_template';

    protected $fillable = [
        'name', 'code', 'category', 'description', 'content',
        'variables', 'is_system', 'status', 'sort', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'variables' => 'json',
        'is_system' => 'integer',
        'status'    => 'integer',
        'sort'      => 'integer',
    ];
}

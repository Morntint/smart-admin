<?php

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI 模型供应商表
 *
 * @property int         $id
 * @property string      $name
 * @property string      $provider
 * @property string      $model_name
 * @property string|null $base_url
 * @property string      $api_key
 * @property int         $max_tokens
 * @property float       $temperature
 * @property float       $top_p
 * @property int         $context_window
 * @property int         $supports_vision
 * @property int         $supports_function_calling
 * @property int         $supports_streaming
 * @property int         $status
 * @property int         $sort
 * @property string|null $remark
 */
class AiModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ai_model';

    protected $fillable = [
        'name', 'provider', 'model_name', 'base_url', 'api_key',
        'max_tokens', 'temperature', 'top_p', 'context_window',
        'supports_vision', 'supports_function_calling', 'supports_streaming',
        'status', 'sort', 'remark', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'max_tokens'                => 'integer',
        'temperature'               => 'float',
        'top_p'                     => 'float',
        'context_window'            => 'integer',
        'supports_vision'           => 'integer',
        'supports_function_calling' => 'integer',
        'supports_streaming'        => 'integer',
        'status'                    => 'integer',
        'sort'                      => 'integer',
    ];
}

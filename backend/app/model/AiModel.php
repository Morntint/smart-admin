<?php

namespace app\model;

use app\common\support\Crypto;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI 模型供应商表
 *
 * @property int         $id
 * @property string      $name
 * @property string      $provider
 * @property string      $model_name
 * @property string|null $base_url
 * @property string      $api_key  // 业务侧总是拿到明文；底层落库时透明加密
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

    /**
     * api_key 透明加解密：写入时加密（带 v1: 前缀），读出时自动解密。
     *
     * 已有的明文历史数据由 {@see Crypto::decrypt()} 兜底原样返回，便于灰度迁移；
     * 任何 `save()` 都会把它转成加密格式。
     */
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : Crypto::decrypt((string) $value),
            set: fn ($value) => $value === null ? null : Crypto::encrypt((string) $value),
        );
    }
}

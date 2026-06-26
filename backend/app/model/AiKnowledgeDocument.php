<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 知识库文档表
 *
 * @property int         $id
 * @property int         $kb_id
 * @property string      $title
 * @property string      $file_type
 * @property string|null $file_url
 * @property string|null $content
 * @property int         $char_count
 * @property int         $chunk_count
 * @property int         $status  0=待处理, 1=处理中, 2=已完成, 3=失败
 * @property string|null $error_msg
 */
class AiKnowledgeDocument extends BaseModel
{
    use SoftDeletes;

    public const STATUS_PENDING    = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_COMPLETED  = 2;
    public const STATUS_FAILED     = 3;

    protected $table = 'ai_knowledge_document';

    protected $fillable = [
        'kb_id', 'title', 'file_type', 'file_url', 'content',
        'char_count', 'chunk_count', 'status', 'error_msg', 'created_by',
    ];

    protected $casts = [
        'kb_id'       => 'integer',
        'char_count'  => 'integer',
        'chunk_count' => 'integer',
        'status'      => 'integer',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'kb_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiDocumentChunk::class, 'document_id');
    }
}

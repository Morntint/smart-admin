<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI 知识库表
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string      $embedding_model
 * @property int         $embedding_dimension
 * @property int         $chunk_size
 * @property int         $chunk_overlap
 * @property int         $top_k
 * @property float       $similarity_threshold
 * @property int         $document_count
 * @property int         $status
 * @property int         $sort
 */
class AiKnowledgeBase extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ai_knowledge_base';

    protected $fillable = [
        'name', 'description', 'embedding_model', 'embedding_dimension',
        'chunk_size', 'chunk_overlap', 'top_k', 'similarity_threshold',
        'document_count', 'status', 'sort', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'embedding_dimension'  => 'integer',
        'chunk_size'           => 'integer',
        'chunk_overlap'        => 'integer',
        'top_k'                => 'integer',
        'similarity_threshold' => 'float',
        'document_count'       => 'integer',
        'status'               => 'integer',
        'sort'                 => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(AiKnowledgeDocument::class, 'kb_id');
    }
}

<?php

namespace app\model;

/**
 * 文档向量分块表
 *
 * @property int        $id
 * @property int        $document_id
 * @property int        $kb_id
 * @property int        $chunk_index
 * @property string     $content
 * @property int        $char_count
 * @property array|null $embedding
 */
class AiDocumentChunk extends BaseModel
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'ai_document_chunk';

    protected $fillable = [
        'document_id', 'kb_id', 'chunk_index', 'content', 'char_count', 'embedding',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'kb_id'       => 'integer',
        'chunk_index' => 'integer',
        'char_count'  => 'integer',
        'embedding'   => 'json',
    ];

    public $timestamps = false;
}

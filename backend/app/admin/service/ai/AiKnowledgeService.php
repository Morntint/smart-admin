<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\common\exception\BusinessException;
use app\model\AiKnowledgeBase;
use app\model\AiKnowledgeDocument;
use app\model\AiDocumentChunk;
use support\Request;

/**
 * AI 知识库管理服务
 */
class AiKnowledgeService extends BaseService
{
    protected string $modelClass = AiKnowledgeBase::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        $query = AiKnowledgeBase::query()->withCount('documents');

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'description']);
        $this->applyFilters($query, ['status' => $request->get('status')]);

        return $this->paginate($query, $request);
    }

    /**
     * 详情
     */
    public function detail(int $id): AiKnowledgeBase
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($id, ['documents']);
        return $kb;
    }

    /**
     * 创建
     */
    public function create(array $data, int $userId): AiKnowledgeBase
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        return AiKnowledgeBase::createData($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data, int $userId): bool
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($id);
        $data['updated_by'] = $userId;
        return $kb->updateData($data);
    }

    /**
     * 删除（级联删除文档和分块）
     */
    public function delete(int $id): void
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($id);
        $this->transaction(function () use ($kb) {
            $docIds = AiKnowledgeDocument::where('kb_id', $kb->id)->pluck('id');
            if ($docIds->isNotEmpty()) {
                AiDocumentChunk::whereIn('document_id', $docIds)->delete();
                AiKnowledgeDocument::where('kb_id', $kb->id)->delete();
            }
            $kb->delete();
        });
    }

    // === 文档管理 ===

    /**
     * 文档分页列表
     */
    public function documentPageList(int $kbId, Request $request): array
    {
        $query = AiKnowledgeDocument::where('kb_id', $kbId);

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['title']);
        $this->applyFilters($query, ['status' => $request->get('status')]);

        return $this->paginate($query, $request);
    }

    /**
     * 上传文档（文本内容）
     */
    public function uploadDocument(int $kbId, array $data, int $userId): AiKnowledgeDocument
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);

        $doc = AiKnowledgeDocument::createData([
            'kb_id'      => $kbId,
            'title'      => $data['title'],
            'file_type'  => $data['file_type'] ?? 'txt',
            'file_url'   => $data['file_url'] ?? null,
            'content'    => $data['content'] ?? '',
            'char_count' => mb_strlen($data['content'] ?? ''),
            'status'     => AiKnowledgeDocument::STATUS_PENDING,
            'created_by' => $userId,
        ]);

        // 异步处理分块和向量化（简化版：同步处理）
        $this->processDocument($doc, $kb);

        return $doc;
    }

    /**
     * 上传文件文档
     */
    public function uploadFileDocument(int $kbId, \Webman\Http\UploadFile $file, array $data, int $userId): AiKnowledgeDocument
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);

        // 获取文件扩展名
        $ext = strtolower($file->getUploadExtension());
        $allowedExts = ['txt', 'md', 'docx', 'pdf'];

        if (!in_array($ext, $allowedExts)) {
            throw new BusinessException('不支持的文件格式，仅支持 txt, md, docx, pdf');
        }

        // 限制文件大小（10MB）
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new BusinessException('文件大小不能超过 10MB');
        }

        // 解析文件内容
        $content = $this->parseFileContent($file, $ext);

        // 保存文件（可选）
        $fileName = md5(uniqid()) . '.' . $ext;
        $filePath = runtime_path('uploads/knowledge/' . $fileName);
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        $file->move($filePath);

        $doc = AiKnowledgeDocument::createData([
            'kb_id'      => $kbId,
            'title'      => $data['title'] ?? $file->getUploadName(),
            'file_type'  => $ext,
            'file_url'   => $fileName,
            'content'    => $content,
            'char_count' => mb_strlen($content),
            'status'     => AiKnowledgeDocument::STATUS_PENDING,
            'created_by' => $userId,
        ]);

        // 处理分块和向量化
        $this->processDocument($doc, $kb);

        return $doc;
    }

    /**
     * 解析文件内容
     */
    private function parseFileContent(\Webman\Http\UploadFile $file, string $ext): string
    {
        switch ($ext) {
            case 'txt':
            case 'md':
                return file_get_contents($file->getPathname());

            case 'docx':
                return $this->parseDocx($file->getPathname());

            case 'pdf':
                return $this->parsePdf($file->getPathname());

            default:
                throw new BusinessException('不支持的文件格式');
        }
    }

    /**
     * 解析 DOCX 文件内容
     */
    private function parseDocx(string $filePath): string
    {
        // 简单的 DOCX 解析（生产环境建议使用专门的库如 PhpWord）
        $content = '';
        $zip = zip_open($filePath);
        if (is_resource($zip)) {
            while ($zipEntry = zip_read($zip)) {
                if (zip_entry_name($zipEntry) === 'word/document.xml') {
                    if (zip_entry_open($zip, $zipEntry, 'r')) {
                        $xml = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
                        zip_entry_close($zipEntry);
                        // 提取文本内容
                        $content = strip_tags(str_replace(['</w:p>', '</w:t>'], "\n", $xml));
                        $content = preg_replace('/\s+/', ' ', $content);
                    }
                    break;
                }
            }
            zip_close($zip);
        }
        return trim($content);
    }

    /**
     * 解析 PDF 文件内容
     */
    private function parsePdf(string $filePath): string
    {
        // 简化版 PDF 解析（生产环境建议使用专门的库如 Smalot/PdfParser）
        // 这里返回空字符串，实际项目中需要集成 PDF 解析库
        return 'PDF content parsing requires additional library';
    }

    /**
     * 删除文档
     */
    public function deleteDocument(int $id): void
    {
        $doc = AiKnowledgeDocument::findOrFail($id);
        AiDocumentChunk::where('document_id', $id)->delete();
        $doc->delete();

        // 更新知识库文档计数
        $count = AiKnowledgeDocument::where('kb_id', $doc->kb_id)->count();
        AiKnowledgeBase::where('id', $doc->kb_id)->update(['document_count' => $count]);
    }

    /**
     * 处理文档分块（简化版文字分块）
     */
    public function processDocument(AiKnowledgeDocument $doc, AiKnowledgeBase $kb): void
    {
        try {
            $doc->status = AiKnowledgeDocument::STATUS_PROCESSING;
            $doc->save();

            $chunks = $this->splitText($doc->content, $kb->chunk_size, $kb->chunk_overlap);

            // 批量插入分块
            $chunkData = [];
            foreach ($chunks as $i => $chunk) {
                $chunkData[] = [
                    'document_id' => $doc->id,
                    'kb_id'       => $kb->id,
                    'chunk_index' => $i + 1,
                    'content'     => $chunk,
                    'char_count'  => mb_strlen($chunk),
                ];
            }

            if (!empty($chunkData)) {
                AiDocumentChunk::insert($chunkData);
            }

            $doc->status      = AiKnowledgeDocument::STATUS_COMPLETED;
            $doc->chunk_count = count($chunkData);
            $doc->save();

            // 更新知识库文档计数
            $count = AiKnowledgeDocument::where('kb_id', $kb->id)
                ->where('status', AiKnowledgeDocument::STATUS_COMPLETED)
                ->count();
            $kb->document_count = $count;
            $kb->save();
        } catch (\Throwable $e) {
            $doc->status    = AiKnowledgeDocument::STATUS_FAILED;
            $doc->error_msg = $e->getMessage();
            $doc->save();
        }
    }

    /**
     * RAG 检索：从知识库中检索相关上下文
     *
     * @param int    $kbId    知识库 ID
     * @param string $query   查询文本
     * @param int    $topK    返回条数
     * @return array<string> 相关文本块
     */
    public function searchChunks(int $kbId, string $query, int $topK = 5): array
    {
        // 获取知识库配置
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);

        // 简化版：关键词匹配（生产环境应使用向量相似度）
        $keywords = $this->extractKeywords($query);

        $chunks = AiDocumentChunk::where('kb_id', $kbId)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('content', 'like', '%' . $kw . '%');
                }
            })
            ->limit(max($topK, $kb->top_k))
            ->get(['content', 'document_id']);

        return $chunks->pluck('content')->toArray();
    }

    // === 私有方法 ===

    /**
     * 文本分块
     */
    private function splitText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $len    = mb_strlen($text);

        if ($len <= $chunkSize) {
            return [$text];
        }

        $start = 0;
        while ($start < $len) {
            $end     = min($start + $chunkSize, $len);
            $chunks[] = mb_substr($text, $start, $end - $start);
            $start    = $end - $overlap;
            if ($start >= $len) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * 简单关键词提取
     */
    private function extractKeywords(string $text): array
    {
        // 简单的分词（生产环境建议使用 jieba-php 等分词库）
        $text = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($text));
        // 过滤短词
        $words = array_filter($words, fn($w) => mb_strlen($w) >= 2);
        // 去重
        return array_values(array_unique($words));
    }
}

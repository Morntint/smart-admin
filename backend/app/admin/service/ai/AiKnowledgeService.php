<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\admin\service\PermissionService;
use app\common\exception\BusinessException;
use app\model\AiKnowledgeBase;
use app\model\AiKnowledgeDocument;
use app\model\AiDocumentChunk;
use support\Log;
use support\Request;
use Webman\RedisQueue\Redis as RedisQueue;

/**
 * AI 知识库管理服务
 */
class AiKnowledgeService extends BaseService
{
    protected string $modelClass = AiKnowledgeBase::class;

    /** redis-queue 队列名：知识库文档分块/向量化 */
    public const QUEUE_PROCESS_DOC = 'ai_knowledge_process_document';

    /**
     * 分页列表
     *
     * 对象级权限：非超管仅能看到自己创建的知识库；与功能权限 `ai:knowledge:list` 叠加，
     * 防止持有功能权限的普通用户横向访问他人 KB。
     */
    public function pageList(Request $request, int $userId): array
    {
        $query = AiKnowledgeBase::query()->withCount('documents');

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'description']);
        $this->applyFilters($query, ['status' => $request->get('status')]);

        if (!PermissionService::getInstance()->isSuperAdmin($userId)) {
            $query->where('created_by', $userId);
        }

        return $this->paginate($query, $request);
    }

    /**
     * 详情
     */
    public function detail(int $id, int $userId): AiKnowledgeBase
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($id, ['documents']);
        $this->assertKbAccess($kb, $userId);
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
        $this->assertKbAccess($kb, $userId);
        $data['updated_by'] = $userId;
        return $kb->updateData($data);
    }

    /**
     * 删除（级联删除文档和分块）
     */
    public function delete(int $id, int $userId): void
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($id);
        $this->assertKbAccess($kb, $userId);
        $this->transaction(function () use ($kb) {
            $docIds = AiKnowledgeDocument::where('kb_id', $kb->id)->pluck('id');
            if ($docIds->isNotEmpty()) {
                AiDocumentChunk::whereIn('document_id', $docIds)->delete();
                AiKnowledgeDocument::where('kb_id', $kb->id)->delete();
            }
            $kb->delete();
        });
    }

    /**
     * 校验当前用户对该 KB 的访问权限：超管 / 创建者本人。
     *
     * 与上层 `RequiresPermission` 注解（功能权限）叠加形成两层防护：
     *  - 注解控制"能不能调这个接口"；
     *  - 这里控制"能不能访问这个对象"。
     */
    private function assertKbAccess(AiKnowledgeBase $kb, int $userId): void
    {
        if (PermissionService::getInstance()->isSuperAdmin($userId)) {
            return;
        }
        if ((int) $kb->created_by !== $userId) {
            throw new BusinessException('无权访问该知识库');
        }
    }

    /**
     * 公开版的对象级访问校验，供外部（如 controller 在做 reprocess 这类越过 service 主接口的调用）使用。
     */
    public function assertKbAccessPublic(AiKnowledgeBase $kb, int $userId): void
    {
        $this->assertKbAccess($kb, $userId);
    }

    // === 文档管理 ===

    /**
     * 文档分页列表
     */
    public function documentPageList(int $kbId, Request $request, int $userId): array
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);
        $this->assertKbAccess($kb, $userId);

        $query = AiKnowledgeDocument::where('kb_id', $kbId);

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['title']);
        $this->applyFilters($query, ['status' => $request->get('status')]);

        return $this->paginate($query, $request);
    }

    /**
     * 上传文档（文本内容）。
     *
     * 仅落 doc 行 + 投递异步任务；分块/向量化由 {@see \app\queue\redis\KnowledgeProcessConsumer}
     * 在后台进程中执行，避免 10MB+ 大文件把 HTTP 请求挂住分钟级、并阻塞 worker。
     */
    public function uploadDocument(int $kbId, array $data, int $userId): AiKnowledgeDocument
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);
        $this->assertKbAccess($kb, $userId);

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

        $this->dispatchProcessDocument($doc->id, $kb->id);

        return $doc;
    }

    /**
     * 上传文件文档
     */
    public function uploadFileDocument(int $kbId, \Webman\Http\UploadFile $file, array $data, int $userId): AiKnowledgeDocument
    {
        /** @var AiKnowledgeBase $kb */
        $kb = $this->findOrFail($kbId);
        $this->assertKbAccess($kb, $userId);

        // 获取文件扩展名
        $ext = strtolower($file->getUploadExtension());
        // pdf 暂未集成解析库，先不接受上传；前端在拿到 400 时会显式提示
        $allowedExts = ['txt', 'md', 'docx'];

        if (!in_array($ext, $allowedExts)) {
            throw new BusinessException('不支持的文件格式，仅支持 txt, md, docx');
        }

        // 限制文件大小（10MB）
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new BusinessException('文件大小不能超过 10MB');
        }

        // 解析文件内容
        $content = $this->parseFileContent($file, $ext);

        // 保存文件（文件名走 random_bytes 避免 md5(uniqid()) 在高并发下的碰撞）
        $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
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

        // 投递异步分块任务（参见 uploadDocument 注释）
        $this->dispatchProcessDocument($doc->id, $kb->id);

        return $doc;
    }

    /**
     * 投递文档分块任务到 redis-queue。
     *
     * 投递失败时退回同步处理，保证旧调用方至少能得到结果（仅记 warning）。
     */
    private function dispatchProcessDocument(int $docId, int $kbId): void
    {
        try {
            RedisQueue::send(self::QUEUE_PROCESS_DOC, ['doc_id' => $docId, 'kb_id' => $kbId]);
        } catch (\Throwable $e) {
            Log::warning('知识库分块任务入队失败，退回同步处理', [
                'doc_id' => $docId,
                'error'  => $e->getMessage(),
            ]);
            // 兜底同步处理，避免文档永远停留在 PENDING
            $doc = AiKnowledgeDocument::find($docId);
            $kb  = AiKnowledgeBase::find($kbId);
            if ($doc && $kb) {
                $this->processDocument($doc, $kb);
            }
        }
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
     * 解析 DOCX 文件内容。
     *
     * 旧实现使用 PHP 7 的 zip_open/zip_read 函数族，这些 API 在 PHP 8.0 起被弃用、
     * 8.2 起在部分场景直接 fatal。改用内置 ZipArchive 重新提取 word/document.xml
     * 并按 <w:t> 标签拼出纯文本（同时根据段落/换行节生成换行）。
     */
    private function parseDocx(string $filePath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new BusinessException('服务器未安装 ZipArchive 扩展，无法解析 DOCX');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($filePath);
        if ($opened !== true) {
            throw new BusinessException('DOCX 文件打开失败：' . (string) $opened);
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new BusinessException('DOCX 缺少 word/document.xml');
            }
        } finally {
            $zip->close();
        }

        // 把段落 / 换行 / 制表符标记成换行符或空白，避免文本黏连
        $xml = preg_replace('/<w:tab[^>]*\/>/u', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br[^>]*\/>/u', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p[^>]*>/u', "\n", $xml) ?? $xml;

        // 提取所有 <w:t>...</w:t> 内文本
        $text = '';
        if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/su', $xml, $m)) {
            $text = implode('', array_map(static fn ($s) => html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]));
        }

        // 兜底：上一步没匹配到也至少 strip_tags
        if ($text === '') {
            $text = strip_tags($xml);
        }

        // 规整空白：保留单换行作为段落分隔，去除连续空白
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * 解析 PDF 文件内容。
     *
     * 当前未集成 PDF 解析库（如 smalot/pdfparser），直接抛业务异常让上传调用方
     * 把文档状态置为 FAILED；不再返回硬编码占位文本，避免 RAG 检索到假内容。
     */
    private function parsePdf(string $filePath): string
    {
        throw new BusinessException('PDF 解析尚未启用，请先安装 smalot/pdfparser 后再启用');
    }

    /**
     * 删除文档（含 chunks 与 kb 计数的事务一致性）。
     */
    public function deleteDocument(int $id, int $userId): void
    {
        $doc = AiKnowledgeDocument::findOrFail($id);
        $kb  = AiKnowledgeBase::findOrFail($doc->kb_id);
        $this->assertKbAccess($kb, $userId);

        $this->transaction(function () use ($doc): void {
            AiDocumentChunk::where('document_id', $doc->id)->delete();
            $doc->delete();
            // kb 计数用 count() + update —— 在事务内取值不会被并发删除干扰
            $count = AiKnowledgeDocument::where('kb_id', $doc->kb_id)
                ->where('status', AiKnowledgeDocument::STATUS_COMPLETED)
                ->count();
            AiKnowledgeBase::where('id', $doc->kb_id)->update(['document_count' => $count]);
        });
    }

    /**
     * 处理文档分块（事务 + 幂等）。
     *
     *  - 进入处理前先标记 PROCESSING；
     *  - 删除已存在的 chunks（重试时不残留半截）；
     *  - 批量 insert 新 chunks；
     *  - 更新 doc 状态 + kb 计数。
     *
     * 任一步失败：事务回滚，状态置 FAILED + 错误消息落库（不进事务），让重试可见。
     */
    public function processDocument(AiKnowledgeDocument $doc, AiKnowledgeBase $kb): void
    {
        try {
            $this->transaction(function () use ($doc, $kb): void {
                // 1. 标记处理中
                $doc->status = AiKnowledgeDocument::STATUS_PROCESSING;
                $doc->error_msg = null;
                $doc->save();

                // 2. 清理可能残留的旧 chunks（重处理场景）
                AiDocumentChunk::where('document_id', $doc->id)->delete();

                // 3. 重新切分并批量入库
                $chunks = $this->splitText($doc->content, $kb->chunk_size, $kb->chunk_overlap);
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

                // 4. 更新文档状态
                $doc->status      = AiKnowledgeDocument::STATUS_COMPLETED;
                $doc->chunk_count = count($chunkData);
                $doc->save();

                // 5. 更新知识库文档计数（在事务内一致快照）
                $count = AiKnowledgeDocument::where('kb_id', $kb->id)
                    ->where('status', AiKnowledgeDocument::STATUS_COMPLETED)
                    ->count();
                AiKnowledgeBase::where('id', $kb->id)->update(['document_count' => $count]);
            });
        } catch (\Throwable $e) {
            // 失败状态落库（不要进事务，避免被回滚）
            AiKnowledgeDocument::where('id', $doc->id)->update([
                'status'    => AiKnowledgeDocument::STATUS_FAILED,
                'error_msg' => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::error('知识库文档处理失败', [
                'doc_id' => $doc->id,
                'kb_id'  => $kb->id,
                'error'  => $e->getMessage(),
            ]);
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

        // 简化版：关键词匹配（生产环境应使用向量相似度）。
        // 关键词必须经 safe_like 过滤掉 %/_，否则攻击者可以让 AI 把 "%" 当关键词发到查询，
        // 退化成 LIKE '%%%' 把整张 ai_document_chunk 拉回内存。
        $keywords = $this->extractKeywords($query);
        if (empty($keywords)) {
            return [];
        }

        $chunks = AiDocumentChunk::where('kb_id', $kbId)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('content', 'like', safe_like_pattern($kw));
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
        // 去重 + 上限，避免拼出几十个 OR 子句
        return array_slice(array_values(array_unique($words)), 0, 8);
    }
}

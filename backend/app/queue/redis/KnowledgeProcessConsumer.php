<?php

namespace app\queue\redis;

use app\admin\service\ai\AiKnowledgeService;
use app\model\AiKnowledgeBase;
use app\model\AiKnowledgeDocument;
use support\Log;
use Webman\RedisQueue\Consumer;

/**
 * 知识库文档分块/向量化消费者
 *
 * 由 webman/redis-queue 的 consumer 进程自动调度。
 *
 * 流程：
 *  1. 从队列消息取 doc_id / kb_id；
 *  2. 找到对应的 doc + kb 记录；任一不存在 → 静默丢弃（防消息过期）；
 *  3. 调用 AiKnowledgeService::processDocument()（已自带事务 + 幂等）。
 *
 * 失败：抛异常 → redis-queue 按 redis.php 的重试策略自动重试；多次失败后进入失败队列。
 */
class KnowledgeProcessConsumer implements Consumer
{
    /** 订阅的队列名，需与投递端 RedisQueue::send() 的 queue 一致 */
    public string $queue = AiKnowledgeService::QUEUE_PROCESS_DOC;

    /** 连接名 */
    public string $connection = 'default';

    /**
     * 消费一条消息。
     *
     * @param array{doc_id:int,kb_id:int} $data
     */
    public function consume($data): void
    {
        $docId = (int) ($data['doc_id'] ?? 0);
        $kbId  = (int) ($data['kb_id']  ?? 0);
        if ($docId <= 0 || $kbId <= 0) {
            Log::warning('KnowledgeProcessConsumer: 非法消息', ['data' => $data]);
            return;
        }

        $doc = AiKnowledgeDocument::find($docId);
        $kb  = AiKnowledgeBase::find($kbId);
        if (!$doc || !$kb) {
            // 文档或知识库已被删除 → 直接丢弃，无需重试
            Log::info('KnowledgeProcessConsumer: 文档或知识库已不存在，跳过', [
                'doc_id' => $docId,
                'kb_id'  => $kbId,
            ]);
            return;
        }

        AiKnowledgeService::getInstance()->processDocument($doc, $kb);
    }
}

<?php

namespace Tests\Unit;

use app\admin\service\ai\AiConversationService;
use app\admin\service\ai\AiGateway;
use app\common\exception\BusinessException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * AiConversationService::sendMessage() 单测
 *
 * 设计：
 *  - tests/bootstrap.php 已经把 Eloquent 切到 SQLite（临时文件）
 *  - 这里建表 + 灌种子，用 FakeAiGateway 拦截 chat()，不依赖真实 DB / LLM
 *  - 用 Capsule query builder 做断言，验证所有 DB 副作用
 */
class AiChatSendTest extends TestCase
{
    private static string $dbFile;

    public static function setUpBeforeClass(): void
    {
        $conn = self::conn();
        self::migrate($conn);

        // 注册清理
        register_shutdown_function(function () {
            // 数据库文件由 bootstrap 拥有；测试结束后不删，方便排查
        });
    }

    public function setUp(): void
    {
        $conn = self::conn();
        // 清空数据
        foreach (['ai_conversation_message', 'ai_conversation', 'ai_usage_record', 'ai_agent_tool', 'ai_agent', 'ai_model'] as $t) {
            $conn->statement("DELETE FROM {$t}");
        }
        FakeAiGateway::reset();
        AiGateway::setFactory(function (array $model) {
            return new FakeAiGateway($model);
        });
    }

    public static function tearDownAfterClass(): void
    {
        AiGateway::setFactory(null);
    }

    // ==================== 测试用例 ====================

    public function testSendMessagePersistsAllArtifacts(): void
    {
        [$userId, $agentId, $modelId] = $this->seedFixtures();

        FakeAiGateway::$nextResponse = [
            'content' => '这是 AI 的回复。',
            'usage'   => [
                'prompt_tokens'     => 100,
                'completion_tokens' => 50,
                'total_tokens'      => 150,
            ],
            'model'   => 'deepseek-chat',
        ];

        $convId = $this->createConversation($userId, $agentId, '新对话');

        $service = new AiConversationService();
        $result  = $service->sendMessage([
            'conversation_id' => $convId,
            'content'         => '你好',
        ], $userId);

        // ① 返回结构
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertSame(150, $result['usage']['total_tokens']);
        $this->assertSame('这是 AI 的回复。', $result['message']['content']);
        $this->assertSame('assistant', $result['message']['role']);

        // ② 数据库落库：2 条消息
        $msgs = $this->queryAllAssoc(
            "SELECT role, content, round_index, token_usage FROM ai_conversation_message WHERE conversation_id = ? ORDER BY id",
            [$convId]
        );
        $this->assertCount(2, $msgs, '应有 2 条消息（user + assistant）');
        $this->assertSame('user', $msgs[0]['role']);
        $this->assertSame('你好', $msgs[0]['content']);
        $this->assertSame('assistant', $msgs[1]['role']);
        $usage1 = json_decode($msgs[1]['token_usage'], true);
        $this->assertSame(150, $usage1['total_tokens']);

        // ③ 用量记录
        $usages = $this->queryAllAssoc(
            "SELECT agent_id, model_name, prompt_tokens, completion_tokens, total_tokens, cost, endpoint, status FROM ai_usage_record WHERE user_id = ?",
            [$userId]
        );
        $this->assertCount(1, $usages, '应有 1 条用量记录');
        $usage = $usages[0];
        $this->assertSame($modelId, (int) $usage['agent_id']);
        $this->assertSame(100, (int) $usage['prompt_tokens']);
        $this->assertSame(50, (int) $usage['completion_tokens']);
        $this->assertSame(150, (int) $usage['total_tokens']);
        $this->assertSame('chat', $usage['endpoint']);
        $this->assertSame(1, (int) $usage['status']);
        $this->assertGreaterThan(0.0, (float) $usage['cost'], '费用应 > 0');

        // ④ 会话统计
        $conv = $this->queryOne(
            "SELECT round_count, total_tokens FROM ai_conversation WHERE id = ?",
            [$convId]
        );
        $this->assertSame(1, (int) $conv['round_count']);
        $this->assertSame(150, (int) $conv['total_tokens']);
    }

    public function testSendMessageAccumulatesRoundCount(): void
    {
        [$userId, $agentId] = $this->seedFixtures();
        $convId = $this->createConversation($userId, $agentId, 't');

        FakeAiGateway::$nextResponse = $this->fakeResponse();

        $service = new AiConversationService();
        $service->sendMessage(['conversation_id' => $convId, 'content' => 'Q1'], $userId);
        $service->sendMessage(['conversation_id' => $convId, 'content' => 'Q2'], $userId);

        $conv = $this->queryOne(
            "SELECT round_count, total_tokens FROM ai_conversation WHERE id = ?",
            [$convId]
        );
        $this->assertSame(2, (int) $conv['round_count']);
        $this->assertSame(300, (int) $conv['total_tokens']);
        $this->assertCount(2, $this->queryAllAssoc("SELECT id FROM ai_usage_record WHERE user_id = ?", [$userId]));
    }

    public function testSendMessageThrowsOnGatewayFailure(): void
    {
        [$userId, $agentId] = $this->seedFixtures();
        $convId = $this->createConversation($userId, $agentId, 't');

        FakeAiGateway::$nextException = new BusinessException('AI 调用失败: HTTP 401');

        $service = new AiConversationService();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/HTTP 401/');
        try {
            $service->sendMessage([
                'conversation_id' => $convId,
                'content'         => 'Q',
            ], $userId);
        } finally {
            $this->assertSame(0, count($this->queryAllAssoc(
                "SELECT id FROM ai_conversation_message WHERE conversation_id = ?",
                [$convId]
            )), '失败时不应写入消息');
            $this->assertSame(0, count($this->queryAllAssoc(
                "SELECT id FROM ai_usage_record WHERE user_id = ?",
                [$userId]
            )), '失败时不应写入用量');
        }
    }

    public function testSendMessageThrowsOnMissingConversation(): void
    {
        [$userId] = $this->seedFixtures();

        $service = new AiConversationService();
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('会话不存在');
        $service->sendMessage([
            'conversation_id' => 999999,
            'content'         => 'hello',
        ], $userId);
    }

    public function testSendMessageCreatesConversationWhenAgentIdProvided(): void
    {
        [$userId, $agentId] = $this->seedFixtures();
        FakeAiGateway::$nextResponse = $this->fakeResponse();

        $service = new AiConversationService();
        $result  = $service->sendMessage([
            'agent_id' => $agentId,
            'content'  => '首条消息',
        ], $userId);

        $this->assertNotEmpty($result['message']);
        $msgId = (int) $result['message']['id'];
        $msg   = $this->queryOne("SELECT conversation_id FROM ai_conversation_message WHERE id = ?", [$msgId]);
        $conv  = $this->queryOne("SELECT user_id, agent_id FROM ai_conversation WHERE id = ?", [$msg['conversation_id']]);

        $this->assertSame($agentId, (int) $conv['agent_id']);
        $this->assertSame($userId, (int) $conv['user_id']);
    }

    public function testFakeGatewayIsUsed(): void
    {
        [$userId, $agentId] = $this->seedFixtures();
        $convId = $this->createConversation($userId, $agentId, 't');

        FakeAiGateway::$nextResponse = [
            'content' => 'INTERCEPTED',
            'usage'   => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            'model'   => 'fake',
        ];

        $service = new AiConversationService();
        $result  = $service->sendMessage(['conversation_id' => $convId, 'content' => 'X'], $userId);

        $this->assertSame('INTERCEPTED', $result['message']['content']);
        $this->assertSame(2, $result['usage']['total_tokens']);
        $this->assertSame(1, FakeAiGateway::$callCount);
    }

    // ==================== 工具方法 ====================

    private static function conn(): Connection
    {
        return Model::getConnectionResolver()->connection();
    }

    private function seedFixtures(): array
    {
        $modelId = $this->insert('ai_model', [
            'name'                      => 'DeepSeek V3',
            'provider'                  => 'deepseek',
            'model_name'                => 'deepseek-chat',
            'base_url'                  => 'https://api.deepseek.com/v1',
            'api_key'                   => 'sk-fake-test',
            'max_tokens'                => 4096,
            'temperature'               => 0.7,
            'top_p'                     => 1.0,
            'context_window'            => 128000,
            'supports_vision'           => 0,
            'supports_function_calling' => 1,
            'supports_streaming'        => 1,
            'status'                    => 1,
            'sort'                      => 0,
        ]);

        $agentId = $this->insert('ai_agent', [
            'name'              => '测试 Agent',
            'code'              => 'test-agent-' . uniqid(),
            'icon'              => 'cpu',
            'description'       => '用于单测',
            'model_id'          => $modelId,
            'system_prompt'     => '你是一个测试助手。',
            'welcome_message'   => '你好',
            'max_history_rounds'=> 10,
            'is_public'         => 1,
            'is_streaming'      => 1,
            'status'            => 1,
            'sort'              => 0,
        ]);

        return [1, $agentId, $modelId];
    }

    private function createConversation(int $userId, int $agentId, string $title): int
    {
        return $this->insert('ai_conversation', [
            'user_id'      => $userId,
            'agent_id'     => $agentId,
            'title'        => $title,
            'round_count'  => 0,
            'total_tokens' => 0,
            'total_cost'   => 0.0,
            'status'       => 1,
        ]);
    }

    private function fakeResponse(): array
    {
        return [
            'content' => 'fake response',
            'usage'   => [
                'prompt_tokens'     => 100,
                'completion_tokens' => 50,
                'total_tokens'      => 150,
            ],
            'model'   => 'deepseek-chat',
        ];
    }

    private function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $conn = self::conn();
        $conn->insert($sql, $data);
        return (int) $conn->getPdo()->lastInsertId();
    }

    private function queryAll(string $sql, array $params = []): array
    {
        return self::conn()->select($sql, $params);
    }

    private function queryOne(string $sql, array $params = []): ?array
    {
        $rows = self::conn()->select($sql, $params);
        return empty($rows) ? null : (array) $rows[0];
    }

    private function queryAllAssoc(string $sql, array $params = []): array
    {
        $rows = self::conn()->select($sql, $params);
        return array_map(fn($r) => (array) $r, $rows);
    }

    private static function migrate(Connection $c): void
    {
        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_model (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                provider VARCHAR(50) NOT NULL,
                model_name VARCHAR(100) NOT NULL,
                base_url VARCHAR(255),
                api_key VARCHAR(255) NOT NULL,
                max_tokens INT DEFAULT 4096,
                temperature REAL DEFAULT 0.7,
                top_p REAL DEFAULT 1.0,
                context_window INT DEFAULT 128000,
                supports_vision INT DEFAULT 0,
                supports_function_calling INT DEFAULT 0,
                supports_streaming INT DEFAULT 1,
                status INT DEFAULT 1,
                sort INT DEFAULT 0,
                remark TEXT,
                created_by INT,
                updated_by INT,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME
            )
        SQL);

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_agent (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(100) NOT NULL UNIQUE,
                icon VARCHAR(50),
                description TEXT,
                model_id INT NOT NULL,
                system_prompt TEXT,
                welcome_message TEXT,
                suggested_questions TEXT,
                max_history_rounds INT DEFAULT 10,
                temperature REAL,
                max_tokens INT,
                knowledge_base_ids TEXT,
                is_public INT DEFAULT 0,
                is_streaming INT DEFAULT 1,
                status INT DEFAULT 1,
                sort INT DEFAULT 0,
                created_by INT,
                updated_by INT,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME
            )
        SQL);

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_agent_tool (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_id INT NOT NULL,
                tool_code VARCHAR(100),
                tool_name VARCHAR(200),
                tool_config TEXT,
                sort INT DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME
            )
        SQL);

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_conversation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                agent_id INT NOT NULL,
                title VARCHAR(255),
                round_count INT DEFAULT 0,
                total_tokens INT DEFAULT 0,
                total_cost REAL DEFAULT 0,
                status INT DEFAULT 1,
                created_at DATETIME,
                updated_at DATETIME
            )
        SQL);

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_conversation_message (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INT NOT NULL,
                round_index INT NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT,
                tool_calls TEXT,
                token_usage TEXT,
                cost REAL DEFAULT 0,
                duration INT DEFAULT 0,
                model_name VARCHAR(100),
                created_at DATETIME,
                updated_at DATETIME
            )
        SQL);

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_usage_record (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                agent_id INT,
                model_name VARCHAR(100) NOT NULL,
                prompt_tokens INT DEFAULT 0,
                completion_tokens INT DEFAULT 0,
                total_tokens INT DEFAULT 0,
                cost REAL DEFAULT 0,
                endpoint VARCHAR(50),
                duration INT DEFAULT 0,
                status INT DEFAULT 1,
                error_msg TEXT,
                created_at DATETIME
            )
        SQL);
    }
}

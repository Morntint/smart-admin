<?php

namespace Tests\Unit;

use app\admin\service\ai\tool\RegisteredTool;
use app\admin\service\ai\tool\ToolGovernance;
use app\admin\service\ai\tool\ToolRegistry;
use app\model\AiTool;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * ToolGovernance 单测。
 *
 * 覆盖治理层关键路径：
 *   ✓ 未注册的 function 工具 → EXECUTION_ERROR 而不是反射调用
 *   ✓ DB 工具不存在 → TOOL_NOT_FOUND
 *   ✓ DB 工具状态禁用 → TOOL_DISABLED
 *   ✓ schema 校验失败 → INVALID_ARGS
 *   ✓ 成功路径返回 result（不再透露 truncated/sample 等元字段）
 *   ✓ 超大返回结果被截断（带 __truncated__ 标记）
 *   ✓ 执行体抛异常 → EXECUTION_ERROR + 错误消息透传
 *
 * 不依赖 Redis：限流逻辑由 RateLimiter::hit() 兜底（Redis 不可用时 fail-open 放行），
 * 因此用例不会因为 CI 没 Redis 而失败。
 */
class ToolGovernanceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        self::conn()->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ai_tool (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                tool_type VARCHAR(20) DEFAULT 'function',
                parameters_schema TEXT,
                handler VARCHAR(255),
                config TEXT,
                status INT DEFAULT 1,
                sort INT DEFAULT 0,
                created_by INT,
                updated_by INT,
                created_at DATETIME,
                updated_at DATETIME
            )
        SQL);
    }

    protected function setUp(): void
    {
        self::conn()->statement('DELETE FROM ai_tool');
        ToolRegistry::getInstance()->reset();
    }

    public function testToolNotFound(): void
    {
        $result = ToolGovernance::getInstance()->invoke('nonexistent', [], ['user_id' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('TOOL_NOT_FOUND', $result['code']);
    }

    public function testToolDisabled(): void
    {
        $this->seedTool([
            'code' => 'disabled_tool',
            'tool_type' => 'function',
            'status' => 0,
        ]);

        $result = ToolGovernance::getInstance()->invoke('disabled_tool', [], ['user_id' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('TOOL_DISABLED', $result['code']);
    }

    public function testFunctionToolMustBeRegistered(): void
    {
        // DB 里有这个工具，但代码侧没注册 → 必须拒绝（H-2 根因）
        $this->seedTool([
            'code' => 'evil_tool',
            'tool_type' => 'function',
            'handler' => 'app\\evil\\Class@method', // 这个字段不应被任何代码用来反射
            'status' => 1,
        ]);

        $result = ToolGovernance::getInstance()->invoke('evil_tool', [], ['user_id' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('EXECUTION_ERROR', $result['code']);
        $this->assertStringContainsString('未在代码侧注册', $result['error']);
    }

    public function testSchemaValidationFails(): void
    {
        $this->seedTool([
            'code' => 'schema_tool',
            'tool_type' => 'function',
            'parameters_schema' => json_encode([
                'type' => 'object',
                'properties' => ['n' => ['type' => 'integer', 'minimum' => 1]],
                'required' => ['n'],
            ]),
            'status' => 1,
        ]);

        // 即便没注册 callable，也应该先在 schema 校验阶段就失败
        $result = ToolGovernance::getInstance()->invoke('schema_tool', [], ['user_id' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_ARGS', $result['code']);
    }

    public function testSuccessPath(): void
    {
        $this->seedTool([
            'code' => 'echo_tool',
            'tool_type' => 'function',
            'parameters_schema' => json_encode([
                'type' => 'object',
                'properties' => ['msg' => ['type' => 'string']],
            ]),
            'status' => 1,
        ]);

        ToolRegistry::getInstance()->registerCallable(
            code: 'echo_tool',
            callable: function (array $args, array $context): array {
                return ['echo' => $args['msg'] ?? '', 'user_id' => $context['user_id'] ?? 0];
            }
        );

        $result = ToolGovernance::getInstance()->invoke(
            'echo_tool',
            ['msg' => 'hello'],
            ['user_id' => 42]
        );

        $this->assertTrue($result['success']);
        $this->assertSame('hello', $result['result']['echo']);
        $this->assertSame(42, $result['result']['user_id']);
    }

    public function testResultTruncatedWhenOversize(): void
    {
        $this->seedTool([
            'code' => 'big_tool',
            'tool_type' => 'function',
            'status' => 1,
        ]);

        ToolRegistry::getInstance()->registerCallable(
            code: 'big_tool',
            callable: fn () => ['payload' => str_repeat('x', 20000)],
            maxResultChars: 1024
        );

        $result = ToolGovernance::getInstance()->invoke('big_tool', [], ['user_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']['__truncated__'] ?? false);
        $this->assertGreaterThan(20000, $result['result']['size']);
        $this->assertLessThanOrEqual(1024, mb_strlen($result['result']['sample']));
    }

    public function testExecutionErrorWrapped(): void
    {
        $this->seedTool([
            'code' => 'throwing_tool',
            'tool_type' => 'function',
            'status' => 1,
        ]);

        ToolRegistry::getInstance()->registerCallable(
            code: 'throwing_tool',
            callable: function () {
                throw new \RuntimeException('boom');
            }
        );

        $result = ToolGovernance::getInstance()->invoke('throwing_tool', [], ['user_id' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('EXECUTION_ERROR', $result['code']);
        $this->assertStringContainsString('boom', $result['error']);
    }

    public function testRegistryOverrideAllowed(): void
    {
        // 同名 register 二次：后者覆盖前者，便于测试 / 插件覆盖
        $registry = ToolRegistry::getInstance();
        $registry->registerCallable(code: 't', callable: fn() => 'A');
        $registry->registerCallable(code: 't', callable: fn() => 'B');

        $this->assertSame('B', $registry->get('t')->invoke([], []));
    }

    // ─────────── 工具方法 ───────────

    private function seedTool(array $data): void
    {
        $data = array_merge([
            'name' => $data['code'] ?? 'tool',
            'description' => '',
            'parameters_schema' => null,
            'handler' => null,
            'config' => null,
            'sort' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data);

        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO ai_tool (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        self::conn()->insert($sql, $data);
    }

    private static function conn(): Connection
    {
        return Model::getConnectionResolver()->connection();
    }
}

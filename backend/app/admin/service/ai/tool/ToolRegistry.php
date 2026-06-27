<?php

namespace app\admin\service\ai\tool;

/**
 * AI 工具注册表（function 类工具的代码侧注册中心）。
 *
 * 核心约束：DB 中的 `ai_tool.handler` 字符串**不再决定执行体**，仅作为兼容字段；
 * 真正的可执行 callable 必须事先在代码里通过 {@see self::register()} 注册，
 * 调用时 {@see ToolGovernance} 按 `code` 在本注册表里查找。
 *
 * 这样做的安全收益：
 *  - 拥有 `ai:tool:create` 权限的人无法通过修改 DB 注入任意类（H-2 的根因）；
 *  - 工具集就是"代码白名单"，code review + git history 即可审计；
 *  - 注册时附带 schema / 限流 / 截断阈值，治理面板化。
 *
 * 注册时机：进程启动一次（由 bootstrap 链路或常驻服务启动钩子触发），
 * 运行时也支持热注册（如插件机制），但不应在请求生命周期里频繁注册。
 *
 * 并发安全：注册集合用单例 + 顺序写入，读多写少；webman 多进程模型下每个 worker
 * 都会在启动期完成注册，不依赖跨进程共享状态。
 */
final class ToolRegistry
{
    private static ?self $instance = null;

    /** @var array<string,RegisteredTool> 按 code 索引 */
    private array $tools = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 注册一个工具。重复 code 后注册的覆盖前者（便于测试 / 插件覆盖）。
     */
    public function register(RegisteredTool $tool): void
    {
        $this->tools[$tool->code] = $tool;
    }

    /**
     * 便捷注册：直接传 callable + 元数据，省去手写 RegisteredTool 实例。
     *
     * @param array<string,mixed> $parametersSchema
     */
    public function registerCallable(
        string $code,
        \Closure $callable,
        string $description = '',
        array $parametersSchema = ['type' => 'object', 'properties' => []],
        ?int $rateLimit = null,
        ?int $maxResultChars = null,
    ): void {
        $this->register(new RegisteredTool(
            code: $code,
            callable: $callable,
            description: $description,
            parametersSchema: $parametersSchema,
            rateLimit: $rateLimit,
            maxResultChars: $maxResultChars,
        ));
    }

    public function get(string $code): ?RegisteredTool
    {
        return $this->tools[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->tools[$code]);
    }

    /**
     * 列出所有已注册工具的 code（供管理面板 / 启动期 self-check 用）。
     *
     * @return string[]
     */
    public function codes(): array
    {
        return array_keys($this->tools);
    }

    /**
     * 全量 dump（仅供测试 / 调试）。
     *
     * @return array<string,RegisteredTool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * 清空注册表（仅供测试）。
     */
    public function reset(): void
    {
        $this->tools = [];
    }
}

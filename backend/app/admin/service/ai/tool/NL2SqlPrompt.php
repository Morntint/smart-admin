<?php

namespace app\admin\service\ai\tool;

use app\admin\service\ai\DateParser;

/**
 * 把 NL2SQL 白名单 + 相对日期说明折叠成 system prompt 片段。
 *
 * 仅当 Agent 启用了 query_database 工具时由 AiConversationService::buildMessages() 注入。
 * 表 / 字段越多，描述就越长 —— 必要时可在 config/ai.php 中 nl2sql 配置里做更细的精简。
 */
class NL2SqlPrompt
{
    public const TOOL_CODE = 'query_database';

    /**
     * 检查 Agent 工具列表里是否包含 query_database。
     *
     * @param array<int,array<string,mixed>> $agentTools AiAgentService::getAgentConfig() 返回的 tools 列表
     */
    public static function isEnabled(array $agentTools): bool
    {
        foreach ($agentTools as $tool) {
            if (($tool['code'] ?? null) === self::TOOL_CODE && (int) ($tool['status'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成提示词片段（含表结构 + 相对日期约定）。
     */
    public static function build(): string
    {
        $tables = (array) config('ai.nl2sql.tables', []);
        if ($tables === []) {
            return '';
        }

        $lines = [];
        $lines[] = '';
        $lines[] = '## 数据查询工具（query_database）';
        $lines[] = '当用户需要查询数据库中的业务数据时，调用 `query_database` 工具，参数为结构化 DSL，禁止凭空编造表名/字段名。可查询的表与字段如下：';
        $lines[] = '';

        foreach ($tables as $table => $conf) {
            $tableDesc = (string) ($conf['description'] ?? '');
            $lines[] = "### `{$table}` —— {$tableDesc}";
            $lines[] = '| 字段 | 类型 | 说明 |';
            $lines[] = '| --- | --- | --- |';
            foreach ((array) ($conf['fields'] ?? []) as $field => $meta) {
                $type = (string) ($meta['type'] ?? 'string');
                $desc = (string) ($meta['description'] ?? '');
                if (!empty($meta['enum']) && is_array($meta['enum'])) {
                    $desc .= '（取值：' . implode(' / ', $meta['enum']) . '）';
                }
                $lines[] = "| `{$field}` | {$type} | {$desc} |";
            }
            $lines[] = '';
        }

        $lines[] = '调用约定：';
        $lines[] = '- `table` 必填，必须是上面列出的表名之一；';
        $lines[] = '- `filters` 是数组，每项 `{field, op, value}`；op 支持 `=`/`!=`/`>`/`>=`/`<`/`<=`/`like`/`in`/`between`/`is_null`/`is_not_null`；';
        $lines[] = '- `like` 无需自带 %，后端会自动包裹模糊匹配；';
        $lines[] = '- 需要总数/分组时使用 `aggregations` 与 `group_by`（聚合时省略 `fields`）；';
        $lines[] = '- 默认每次最多返回 50 行，最大 200 行，请用 `limit` 控制；';
        $lines[] = '- 任何不在白名单内的表 / 字段 / 操作符都会被后端拒绝，请严格使用上述清单。';
        $lines[] = '';
        $lines[] = DateParser::getRelativeDateDescription();

        return implode("\n", $lines);
    }
}

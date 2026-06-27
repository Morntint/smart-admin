<?php

namespace app\bootstrap;

use app\admin\service\ai\tool\ToolRegistry;
use app\admin\service\ai\tool\NL2SqlTool;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * AI 工具注册引导。
 *
 * webman 每个 worker 启动时调用一次 {@see self::start()}：
 *  - 把项目内置的 AI 工具注册到 {@see ToolRegistry}；
 *  - 工具的可执行体由代码声明，DB 仅保存 code 与 schema —— 拥有 ai:tool:create
 *    权限的人无法通过 handler 字段注入任意类（H-2 根因）。
 *
 * 当前内置工具：
 *  - query_database：通用 NL2SQL 工具，使用结构化 DSL，按 config/ai.php 中 nl2sql
 *    的表/字段白名单执行查询；可以覆盖之前的「按表写一个工具」模式。
 */
class AiToolBootstrap implements Bootstrap
{
    /**
     * @param Worker|null $worker
     */
    public static function start($worker): void
    {
        $registry = ToolRegistry::getInstance();

        $allowedTables = array_keys((array) config('ai.nl2sql.tables', []));
        $operators     = (array) config('ai.nl2sql.operators', []);
        $aggregations  = (array) config('ai.nl2sql.aggregations', []);
        $maxLimit      = (int) config('ai.nl2sql.limits.max_limit', 200);

        $registry->registerCallable(
            code: 'query_database',
            callable: function (array $args, array $context): mixed {
                return (new NL2SqlTool())->execute($args, $context);
            },
            description: '使用结构化 DSL 查询业务数据库；表 / 字段 / 操作符全部白名单限制，禁止裸 SQL。',
            parametersSchema: [
                'type'       => 'object',
                'required'   => ['table'],
                'properties' => [
                    'table' => [
                        'type'        => 'string',
                        'description' => '要查询的表名（必须是白名单内的表）',
                        'enum'        => $allowedTables,
                    ],
                    'fields' => [
                        'type'        => 'array',
                        'description' => '要返回的字段，省略则返回该表所有可见字段；与 aggregations 互斥',
                        'items'       => ['type' => 'string'],
                    ],
                    'filters' => [
                        'type'        => 'array',
                        'description' => '过滤条件列表（AND 关系），最多 10 条',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['field', 'op'],
                            'properties' => [
                                'field' => ['type' => 'string'],
                                'op'    => ['type' => 'string', 'enum' => $operators],
                                'value' => [], // 任意类型，由后端按字段类型校验
                            ],
                        ],
                    ],
                    'order_by' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['field'],
                            'properties' => [
                                'field'     => ['type' => 'string'],
                                'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                            ],
                        ],
                    ],
                    'limit'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => $maxLimit],
                    'offset' => ['type' => 'integer', 'minimum' => 0],
                    'aggregations' => [
                        'type'        => 'array',
                        'description' => '聚合查询（count / sum / avg / max / min）；指定后建议同时给 group_by',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['fn'],
                            'properties' => [
                                'fn'    => ['type' => 'string', 'enum' => $aggregations],
                                'field' => ['type' => 'string'],
                                'alias' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'group_by' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
            rateLimit:      20,
            maxResultChars: 12288,
        );
    }
}

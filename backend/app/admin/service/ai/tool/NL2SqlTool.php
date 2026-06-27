<?php

namespace app\admin\service\ai\tool;

use app\admin\service\ai\DateParser;
use support\Db;

/**
 * NL2SQL 工具：LLM 用结构化 DSL 描述查询，后端按白名单拼安全 SQL。
 *
 * 入参约定（与 AiToolBootstrap 里登记的 JSON Schema 一致）：
 * {
 *   "table":        "sys_operation_log",
 *   "fields":       ["id","username","created_at"],            // 可选；省略=全字段（白名单内）
 *   "filters":      [{"field":"status","op":"=","value":0}, ...],
 *   "order_by":     [{"field":"id","direction":"desc"}],       // 可选；省略=表默认
 *   "limit":        20,                                        // 可选；省略=default_limit；上限 max_limit
 *   "offset":       0,
 *   "aggregations": [{"fn":"count","field":"*","alias":"total"}],  // 与 fields 互斥（统计场景）
 *   "group_by":     ["module"]                                  // 仅当 aggregations 存在时有效
 * }
 *
 * 安全规则：
 *  - 表 / 字段 / op / 聚合 全部白名单 in_array 校验
 *  - 字段值按声明类型强转（int / number / string / datetime）
 *  - datetime 支持相对日期（today / last_7_days / ...）由 DateParser 转换
 *  - like 只接受裸字符串，后端固定包 %v%；in / between 自带值数量上限
 *  - 所有 value 走 Query Builder 参数绑定，不参与字符串拼接
 *  - LIMIT 强制注入；超过 max_limit 截断
 */
class NL2SqlTool
{
    /**
     * 工具入口。失败抛 \InvalidArgumentException，由 ToolGovernance 转成结构化错误回给 LLM。
     *
     * @return array{table:string, total:int, returned:int, list:array}
     */
    public function execute(array $args, array $context = []): array
    {
        $config  = (array) config('ai.nl2sql', []);
        $tables  = (array) ($config['tables']  ?? []);
        $limits  = (array) ($config['limits']  ?? []);
        $allowedOps  = (array) ($config['operators']    ?? []);
        $allowedAggs = (array) ($config['aggregations'] ?? []);

        // 1. 表白名单
        $table = (string) ($args['table'] ?? '');
        if (!isset($tables[$table])) {
            throw new \InvalidArgumentException("表不在白名单中: {$table}");
        }
        $tableConf = $tables[$table];
        $fieldsConf = (array) ($tableConf['fields'] ?? []);

        // 2. 聚合 / 普通列两种模式互斥
        $hasAgg = !empty($args['aggregations']);

        $query = Db::table($table);

        // 3. SELECT 列表
        if ($hasAgg) {
            $this->applyAggregations($query, $args, $fieldsConf, $allowedAggs);
            $this->applyGroupBy($query, $args['group_by'] ?? [], $fieldsConf);
        } else {
            $fields = $this->resolveSelectFields($args['fields'] ?? null, $fieldsConf, (int) ($limits['max_fields'] ?? 20));
            $query->select($fields);
        }

        // 4. WHERE
        $maxFilters = (int) ($limits['max_filters'] ?? 10);
        $filters = $args['filters'] ?? [];
        if (!is_array($filters)) {
            throw new \InvalidArgumentException('filters 必须为数组');
        }
        if (count($filters) > $maxFilters) {
            throw new \InvalidArgumentException("filters 条数过多（最多 {$maxFilters}）");
        }
        $maxInValues = (int) ($limits['max_in_values'] ?? 50);
        foreach ($filters as $i => $filter) {
            $this->applyFilter($query, $filter, $fieldsConf, $allowedOps, $maxInValues, $i);
        }

        // 5. ORDER BY
        $orderBy = $args['order_by'] ?? [];
        if (empty($orderBy) && !$hasAgg) {
            // 仅普通列模式应用默认排序；聚合查询默认不排序
            $default = $tableConf['order_by_default'] ?? null;
            if (is_array($default) && isset($default['field'])) {
                $orderBy = [$default];
            }
        }
        if (!is_array($orderBy)) {
            throw new \InvalidArgumentException('order_by 必须为数组');
        }
        foreach ($orderBy as $clause) {
            if (!is_array($clause)) {
                throw new \InvalidArgumentException('order_by 项必须为对象');
            }
            $field = (string) ($clause['field'] ?? '');
            $dir   = strtolower((string) ($clause['direction'] ?? 'asc'));
            if (!isset($fieldsConf[$field]) && $field !== 'count' /* 聚合别名兜底 */) {
                throw new \InvalidArgumentException("order_by 字段不在白名单中: {$field}");
            }
            if (!in_array($dir, ['asc', 'desc'], true)) {
                throw new \InvalidArgumentException("order_by direction 必须是 asc/desc: {$dir}");
            }
            $query->orderBy($field, $dir);
        }

        // 6. LIMIT / OFFSET（强制注入）
        $maxLimit     = (int) ($limits['max_limit']     ?? 200);
        $defaultLimit = (int) ($limits['default_limit'] ?? 50);
        $limit = (int) ($args['limit'] ?? $defaultLimit);
        if ($limit <= 0) {
            $limit = $defaultLimit;
        }
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $query->limit($limit)->offset($offset);

        // 7. 执行
        $rows = $query->get()->map(fn ($row) => (array) $row)->all();

        // 8. total：聚合模式下没必要再 count 一次；普通列模式做一次同条件 count
        $total = $hasAgg
            ? count($rows)
            : (int) Db::table($table)
                ->when(true, function ($q) use ($filters, $fieldsConf, $allowedOps, $maxInValues) {
                    foreach ($filters as $i => $filter) {
                        $this->applyFilter($q, $filter, $fieldsConf, $allowedOps, $maxInValues, $i);
                    }
                })
                ->count();

        return [
            'table'    => $table,
            'total'    => $total,
            'returned' => count($rows),
            'list'     => $rows,
        ];
    }

    /**
     * 解析 SELECT 字段列表：未提供时返回所有白名单字段；提供时校验每一个。
     *
     * @return string[]
     */
    private function resolveSelectFields(mixed $fields, array $fieldsConf, int $maxFields): array
    {
        if ($fields === null) {
            return array_keys($fieldsConf);
        }
        if (!is_array($fields) || $fields === []) {
            return array_keys($fieldsConf);
        }
        if (count($fields) > $maxFields) {
            throw new \InvalidArgumentException("fields 数量过多（最多 {$maxFields}）");
        }
        $out = [];
        foreach ($fields as $f) {
            if (!is_string($f) || !isset($fieldsConf[$f])) {
                throw new \InvalidArgumentException('字段不在白名单中: ' . (is_string($f) ? $f : '<non-string>'));
            }
            $out[] = $f;
        }
        return $out;
    }

    /**
     * 应用单个过滤条件。所有值走参数绑定，op 白名单。
     */
    private function applyFilter($query, mixed $filter, array $fieldsConf, array $allowedOps, int $maxInValues, int $index): void
    {
        if (!is_array($filter)) {
            throw new \InvalidArgumentException("filters[{$index}] 必须为对象");
        }
        $field = (string) ($filter['field'] ?? '');
        $op    = strtolower((string) ($filter['op'] ?? '='));
        if (!isset($fieldsConf[$field])) {
            throw new \InvalidArgumentException("filters[{$index}].field 不在白名单中: {$field}");
        }
        if (!in_array($op, $allowedOps, true)) {
            throw new \InvalidArgumentException("filters[{$index}].op 不被允许: {$op}");
        }
        $type = $fieldsConf[$field]['type'] ?? 'string';
        $raw  = $filter['value'] ?? null;

        switch ($op) {
            case 'is_null':
                $query->whereNull($field);
                return;
            case 'is_not_null':
                $query->whereNotNull($field);
                return;
            case 'in':
                if (!is_array($raw)) {
                    throw new \InvalidArgumentException("filters[{$index}].value 必须为数组（op=in）");
                }
                if (count($raw) > $maxInValues) {
                    throw new \InvalidArgumentException("in 值数量过多（最多 {$maxInValues}）");
                }
                $values = array_map(fn ($v) => $this->castValue($v, $type, $field), $raw);
                $query->whereIn($field, $values);
                return;
            case 'between':
                if (!is_array($raw) || count($raw) !== 2) {
                    throw new \InvalidArgumentException("filters[{$index}].value 必须为长度 2 数组（op=between）");
                }
                $a = $this->castValue($raw[0], $type, $field);
                $b = $this->castValue($raw[1], $type, $field);
                $query->whereBetween($field, [$a, $b]);
                return;
            case 'like':
                if (!is_string($raw) || $raw === '') {
                    throw new \InvalidArgumentException("filters[{$index}].value 必须为非空字符串（op=like）");
                }
                // 主动包通配符；用户不能自带 %，避免被刻意构造
                $query->where($field, 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $raw) . '%');
                return;
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $value = $this->castValue($raw, $type, $field);
                $query->where($field, $op, $value);
                return;
        }
    }

    /**
     * 按字段声明的 type 把 LLM 给的值强转，拒绝不可解析的输入。
     */
    private function castValue(mixed $raw, string $type, string $field): mixed
    {
        return match ($type) {
            'int'      => $this->castInt($raw, $field),
            'number'   => $this->castNumber($raw, $field),
            'datetime' => $this->castDateTime($raw, $field),
            // string 默认
            default    => is_scalar($raw) ? (string) $raw : throw new \InvalidArgumentException("字段 {$field} 需要标量值"),
        };
    }

    private function castInt(mixed $raw, string $field): int
    {
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }
        throw new \InvalidArgumentException("字段 {$field} 需要整数值，收到: " . json_encode($raw, JSON_UNESCAPED_UNICODE));
    }

    private function castNumber(mixed $raw, string $field): float|int
    {
        if (is_int($raw) || is_float($raw)) {
            return $raw;
        }
        if (is_string($raw) && is_numeric($raw)) {
            return $raw + 0;
        }
        throw new \InvalidArgumentException("字段 {$field} 需要数字值，收到: " . json_encode($raw, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 日期值：支持 DateParser 的相对日期（today/last_7_days/...）和绝对 Y-m-d / Y-m-d H:i:s。
     * 返回 'Y-m-d H:i:s' 字符串（以 0 时分秒兜底），符合 datetime 列的常规 WHERE 用法。
     */
    private function castDateTime(mixed $raw, string $field): string
    {
        if (!is_string($raw) || $raw === '') {
            throw new \InvalidArgumentException("字段 {$field} 需要日期字符串");
        }
        // 已是 Y-m-d H:i:s
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return $raw;
        }
        $parsed = DateParser::parse($raw, 'start');
        if ($parsed === '') {
            throw new \InvalidArgumentException("字段 {$field} 日期解析失败: {$raw}");
        }
        return $parsed . ' 00:00:00';
    }

    /**
     * 聚合：select count(*), sum(field), ...
     */
    private function applyAggregations($query, array $args, array $fieldsConf, array $allowedAggs): void
    {
        $aggs = $args['aggregations'] ?? [];
        if (!is_array($aggs) || $aggs === []) {
            throw new \InvalidArgumentException('aggregations 必须为非空数组');
        }
        $selects = [];

        // group_by 字段也要 SELECT 出来，否则结果里看不到分组维度
        foreach ((array) ($args['group_by'] ?? []) as $g) {
            if (!is_string($g) || !isset($fieldsConf[$g])) {
                throw new \InvalidArgumentException("group_by 字段不在白名单中: " . (is_string($g) ? $g : '<non-string>'));
            }
            $selects[] = $g;
        }

        foreach ($aggs as $i => $agg) {
            if (!is_array($agg)) {
                throw new \InvalidArgumentException("aggregations[{$i}] 必须为对象");
            }
            $fn    = strtolower((string) ($agg['fn'] ?? ''));
            $field = (string) ($agg['field'] ?? '*');
            $alias = (string) ($agg['alias'] ?? "{$fn}_{$i}");
            if (!in_array($fn, $allowedAggs, true)) {
                throw new \InvalidArgumentException("aggregations[{$i}].fn 不被允许: {$fn}");
            }
            // 别名只允许字母数字下划线，防注入
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}$/', $alias)) {
                throw new \InvalidArgumentException("aggregations[{$i}].alias 不合法: {$alias}");
            }
            if ($fn === 'count' && $field === '*') {
                $selects[] = Db::raw("COUNT(*) AS {$alias}");
                continue;
            }
            if (!isset($fieldsConf[$field])) {
                throw new \InvalidArgumentException("aggregations[{$i}].field 不在白名单中: {$field}");
            }
            $type = $fieldsConf[$field]['type'] ?? 'string';
            if ($fn !== 'count' && !in_array($type, ['int', 'number'], true)) {
                throw new \InvalidArgumentException("聚合 {$fn} 仅支持 int/number 字段，{$field} 类型为 {$type}");
            }
            // 字段名经白名单 in_array 校验，此处可安全拼入
            $selects[] = Db::raw(strtoupper($fn) . "({$field}) AS {$alias}");
        }
        $query->select($selects);
    }

    private function applyGroupBy($query, mixed $groupBy, array $fieldsConf): void
    {
        if (empty($groupBy)) {
            return;
        }
        if (!is_array($groupBy)) {
            throw new \InvalidArgumentException('group_by 必须为数组');
        }
        foreach ($groupBy as $g) {
            if (!is_string($g) || !isset($fieldsConf[$g])) {
                throw new \InvalidArgumentException("group_by 字段不在白名单中: " . (is_string($g) ? $g : '<non-string>'));
            }
            $query->groupBy($g);
        }
    }
}

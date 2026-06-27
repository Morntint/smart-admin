<?php

namespace app\admin\service\ai\tool;

/**
 * 最小可用的 JSON Schema 校验器。
 *
 * 不引入 composer 依赖（如 justinrainbow/json-schema），覆盖项目里 AI 工具
 * `parameters_schema` 实际用到的子集：
 *   - type：object / array / string / integer / number / boolean / null（联合类型用数组）
 *   - object：properties / required / additionalProperties=false 时拒绝未声明字段
 *   - array：items / minItems / maxItems
 *   - string：enum / minLength / maxLength / pattern
 *   - integer / number：enum / minimum / maximum
 *
 * 严格度：失败抛 {@see InvalidArgumentException}，消息里带 `path`（JSON-pointer 风格）
 * 便于 AI 直接读懂错误并改正参数。
 *
 * 用法：
 *   JsonSchemaValidator::validate($args, $tool->parameters_schema);
 */
final class JsonSchemaValidator
{
    /**
     * @param mixed                $value
     * @param array<string,mixed>  $schema
     * @param string               $path 当前节点路径，外部调用一般为 ''
     * @throws \InvalidArgumentException
     */
    public static function validate(mixed $value, array $schema, string $path = ''): void
    {
        if ($schema === []) {
            return; // 没声明 schema 等同放行
        }

        $type = $schema['type'] ?? null;
        if ($type !== null) {
            self::assertType($value, $type, $path);
        }

        // enum 适用于任何类型
        if (isset($schema['enum']) && is_array($schema['enum']) && !self::inEnum($value, $schema['enum'])) {
            throw self::fail($path, '取值不在 enum 列表内');
        }

        if (is_array($value) && self::isAssoc($value)) {
            self::validateObject($value, $schema, $path);
        } elseif (is_array($value)) {
            self::validateArray($value, $schema, $path);
        } elseif (is_string($value)) {
            self::validateString($value, $schema, $path);
        } elseif (is_int($value) || is_float($value)) {
            self::validateNumber($value, $schema, $path);
        }
    }

    /**
     * @param array<string,mixed> $value
     * @param array<string,mixed> $schema
     */
    private static function validateObject(array $value, array $schema, string $path): void
    {
        $properties = $schema['properties'] ?? [];
        $required   = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $field) {
                if (!array_key_exists($field, $value)) {
                    throw self::fail($path === '' ? "/{$field}" : "{$path}/{$field}", '字段必填但未提供');
                }
            }
        }

        // additionalProperties=false 时拒绝未声明字段
        if (($schema['additionalProperties'] ?? null) === false && is_array($properties)) {
            $allowed = array_keys($properties);
            foreach (array_keys($value) as $k) {
                if (!in_array($k, $allowed, true)) {
                    throw self::fail("{$path}/{$k}", '字段未在 schema 中声明');
                }
            }
        }

        if (is_array($properties)) {
            foreach ($properties as $field => $subSchema) {
                if (!array_key_exists($field, $value) || !is_array($subSchema)) {
                    continue;
                }
                self::validate($value[$field], $subSchema, "{$path}/{$field}");
            }
        }
    }

    /**
     * @param array<int,mixed>   $value
     * @param array<string,mixed> $schema
     */
    private static function validateArray(array $value, array $schema, string $path): void
    {
        if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
            throw self::fail($path, '数组元素少于 minItems=' . $schema['minItems']);
        }
        if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
            throw self::fail($path, '数组元素多于 maxItems=' . $schema['maxItems']);
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $i => $item) {
                self::validate($item, $schema['items'], "{$path}/{$i}");
            }
        }
    }

    /**
     * @param array<string,mixed> $schema
     */
    private static function validateString(string $value, array $schema, string $path): void
    {
        if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
            throw self::fail($path, '字符串长度小于 minLength=' . $schema['minLength']);
        }
        if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
            throw self::fail($path, '字符串长度大于 maxLength=' . $schema['maxLength']);
        }
        if (isset($schema['pattern']) && is_string($schema['pattern'])) {
            $delim = '#';
            $regex = $delim . str_replace($delim, '\\' . $delim, $schema['pattern']) . $delim . 'u';
            if (@preg_match($regex, $value) !== 1) {
                throw self::fail($path, '字符串不匹配 pattern');
            }
        }
    }

    /**
     * @param int|float           $value
     * @param array<string,mixed> $schema
     */
    private static function validateNumber(int|float $value, array $schema, string $path): void
    {
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            throw self::fail($path, '数值小于 minimum=' . $schema['minimum']);
        }
        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            throw self::fail($path, '数值大于 maximum=' . $schema['maximum']);
        }
    }

    /**
     * 类型断言。type 可以是字符串或数组（联合类型）。
     */
    private static function assertType(mixed $value, mixed $type, string $path): void
    {
        $types = is_array($type) ? $type : [$type];
        foreach ($types as $t) {
            if (self::matchesType($value, (string) $t)) {
                return;
            }
        }
        throw self::fail($path, '类型不匹配，期望 ' . implode('|', array_map('strval', $types)));
    }

    private static function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object'  => is_array($value) && self::isAssoc($value),
            'array'   => is_array($value) && !self::isAssoc($value),
            'string'  => is_string($value),
            'integer' => is_int($value),
            'number'  => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null'    => $value === null,
            default   => true, // 未知 type 放行，保持向后兼容
        };
    }

    /**
     * 判断是否为关联数组（用于区分 JSON object vs array）。
     * 空数组按 object 处理 —— 与 PHP 的 `json_decode('{}', true)` 一致。
     *
     * @param array<int|string,mixed> $arr
     */
    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param array<int,mixed> $enum
     */
    private static function inEnum(mixed $value, array $enum): bool
    {
        foreach ($enum as $allowed) {
            if ($value === $allowed) {
                return true;
            }
            // schema 里 enum 经常是字符串，但 LLM 可能把数字传过来；做一次弱比较的兜底
            if (is_scalar($value) && is_scalar($allowed) && (string) $value === (string) $allowed) {
                return true;
            }
        }
        return false;
    }

    private static function fail(string $path, string $reason): \InvalidArgumentException
    {
        $where = $path === '' ? '(根)' : $path;
        return new \InvalidArgumentException("参数校验失败 {$where}: {$reason}");
    }
}

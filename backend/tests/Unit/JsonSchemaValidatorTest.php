<?php

namespace Tests\Unit;

use app\admin\service\ai\tool\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * JsonSchemaValidator 单测。
 *
 * 覆盖项目 ai_tool.parameters_schema 实际用到的子集：
 *   - type / properties / required / additionalProperties
 *   - enum
 *   - integer minimum/maximum
 *   - string maxLength / pattern
 *   - array items / maxItems
 */
class JsonSchemaValidatorTest extends TestCase
{
    public function testRequiredField(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['page' => ['type' => 'integer']],
            'required' => ['page'],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/字段必填/');
        JsonSchemaValidator::validate([], $schema);
    }

    public function testEnum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'method' => ['type' => 'string', 'enum' => ['GET','POST']],
            ],
        ];

        // 合法
        JsonSchemaValidator::validate(['method' => 'POST'], $schema);

        // 非法
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/enum/');
        JsonSchemaValidator::validate(['method' => 'DELETE'], $schema);
    }

    public function testIntegerRange(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];

        JsonSchemaValidator::validate(['limit' => 20], $schema);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maximum/');
        JsonSchemaValidator::validate(['limit' => 1000], $schema);
    }

    public function testStringMaxLength(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'keyword' => ['type' => 'string', 'maxLength' => 5],
            ],
        ];

        JsonSchemaValidator::validate(['keyword' => 'hi'], $schema);

        $this->expectException(\InvalidArgumentException::class);
        JsonSchemaValidator::validate(['keyword' => 'too long string'], $schema);
    }

    public function testAdditionalPropertiesFalse(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'string']],
            'additionalProperties' => false,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/未在 schema 中声明/');
        JsonSchemaValidator::validate(['a' => 'x', 'b' => 'y'], $schema);
    }

    public function testTypeMismatch(): void
    {
        $schema = ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/类型不匹配/');
        JsonSchemaValidator::validate(['n' => 'not-int'], $schema);
    }

    public function testEnumScalarCoercion(): void
    {
        // LLM 把 "1" 当字符串传过来，但 schema 里 enum 是 ['0','1'] 字符串；应通过
        $schema = [
            'type' => 'object',
            'properties' => ['status' => ['type' => 'string', 'enum' => ['0','1']]],
        ];

        // schema 严格要求字符串类型，传 int 应失败
        $this->expectException(\InvalidArgumentException::class);
        JsonSchemaValidator::validate(['status' => 1], $schema);
    }

    public function testEmptySchemaAllowsAnything(): void
    {
        // 空 schema = 放行（保持向后兼容）
        JsonSchemaValidator::validate(['anything' => 123], []);
        $this->assertTrue(true);
    }

    public function testNestedObject(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'filter' => [
                    'type' => 'object',
                    'properties' => ['min' => ['type' => 'integer', 'minimum' => 0]],
                    'required' => ['min'],
                ],
            ],
            'required' => ['filter'],
        ];

        JsonSchemaValidator::validate(['filter' => ['min' => 5]], $schema);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#/filter/min#');
        JsonSchemaValidator::validate(['filter' => ['min' => -1]], $schema);
    }

    public function testArrayItems(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'maxItems' => 3,
                ],
            ],
        ];

        JsonSchemaValidator::validate(['ids' => [1,2,3]], $schema);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maxItems/');
        JsonSchemaValidator::validate(['ids' => [1,2,3,4]], $schema);
    }
}

<?php

namespace app\admin\service\ai\tool;

/**
 * 注册到 {@see ToolRegistry} 的工具值对象。
 *
 * 这一层是为了把"工具元数据 + 可执行体"绑定在一起：
 *  - code           供 LLM tool_calls 引用
 *  - callable       业务实现；签名约定 `fn(array $args, array $context): mixed`
 *  - description    给 LLM 看的语义说明（也允许从 DB 同步）
 *  - parametersSchema  入参 JSON Schema（{@see JsonSchemaValidator} 强校验）
 *  - rateLimit      可选每用户/每分钟调用上限（null = 不限）
 *  - maxResultChars 可选返回结果的最大字符长度（null = 走全局默认）
 */
final class RegisteredTool
{
    /**
     * @param string                 $code
     * @param \Closure               $callable     fn(array $args, array $context): mixed
     * @param string                 $description
     * @param array<string,mixed>    $parametersSchema
     * @param int|null               $rateLimit
     * @param int|null               $maxResultChars
     */
    public function __construct(
        public readonly string $code,
        public readonly \Closure $callable,
        public readonly string $description = '',
        public readonly array $parametersSchema = ['type' => 'object', 'properties' => []],
        public readonly ?int $rateLimit = null,
        public readonly ?int $maxResultChars = null,
    ) {
    }

    /**
     * 调用工具。
     *
     * @param array<string,mixed> $args
     * @param array<string,mixed> $context
     * @return mixed
     */
    public function invoke(array $args, array $context): mixed
    {
        return ($this->callable)($args, $context);
    }
}

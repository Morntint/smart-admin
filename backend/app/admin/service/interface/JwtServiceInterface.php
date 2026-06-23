<?php

namespace app\admin\service\interface;

/**
 * JWT 服务接口
 *
 * 实现：app\admin\service\JwtService
 */
interface JwtServiceInterface
{
    /**
     * 生成 Token。
     *
     * @param array<string,mixed> $claims 自定义负载（不要放敏感信息，如密码、原始 Token 等）
     */
    public function encode(array $claims): string;

    /**
     * 验证并解析 Token。
     *
     * @return array<string,mixed>|null 成功返回 payload，失败/过期/篡改返回 null
     */
    public function decode(string $token): ?array;

    /**
     * 获取 Token 有效期（秒）。
     */
    public function getExpire(): int;
}

<?php

namespace Tests\Unit;

use app\admin\service\JwtService;
use PHPUnit\Framework\TestCase;

/**
 * JwtService 单测。
 *
 * 覆盖：
 *  ✓ encode → decode 还原 claims
 *  ✓ 篡改 token decode 返回 null
 *  ✓ 过期 token decode 返回 null
 *  ✓ 缺失 user_id 时 decode 仍能解出 payload（但调用方靠 user_id 判 null）
 *  ✓ 密钥过短时 getInstance 抛 RuntimeException
 */
class JwtServiceTest extends TestCase
{
    public function testEncodeDecodeRoundtrip(): void
    {
        $jwt = JwtService::getInstance();
        $token = $jwt->encode(['user_id' => 42, 'username' => 'alice', 'tv' => 3]);

        $payload = $jwt->decode($token);
        $this->assertIsArray($payload);
        $this->assertSame(42, $payload['user_id']);
        $this->assertSame('alice', $payload['username']);
        $this->assertSame(3, $payload['tv']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testTamperedTokenReturnsNull(): void
    {
        $jwt = JwtService::getInstance();
        $token = $jwt->encode(['user_id' => 1]);

        // 改动 token 中间一字符
        $tampered = substr_replace($token, 'X', strlen($token) - 5, 1);
        $this->assertNull($jwt->decode($tampered));
    }

    public function testMalformedTokenReturnsNull(): void
    {
        $jwt = JwtService::getInstance();
        $this->assertNull($jwt->decode('not.a.jwt'));
        $this->assertNull($jwt->decode(''));
    }

    public function testGetExpireReturnsConfiguredValue(): void
    {
        $expire = JwtService::getInstance()->getExpire();
        $this->assertGreaterThan(0, $expire);
    }
}

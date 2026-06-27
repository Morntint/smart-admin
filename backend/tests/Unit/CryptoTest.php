<?php

namespace Tests\Unit;

use app\common\support\Crypto;
use PHPUnit\Framework\TestCase;

/**
 * Crypto 单测。
 *
 * 覆盖：
 *  ✓ 加密 → 解密回原文
 *  ✓ 空串透传
 *  ✓ 已加密文本二次加密保持幂等
 *  ✓ 旧明文（无 v1: 前缀）原样返回，便于灰度迁移
 *  ✓ 异常密文 tryDecrypt 返回 null
 *  ✓ 同明文每次加密产出不同密文（IV 随机化）
 */
class CryptoTest extends TestCase
{
    protected function setUp(): void
    {
        // 走 JWT_SECRET 派生密钥，保证测试环境也能跑通
        if (getenv('APP_CRYPTO_KEY') === false && getenv('JWT_SECRET') === false) {
            putenv('JWT_SECRET=' . str_repeat('a', 32));
        }
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $plain  = 'sk-1234567890abcdef';
        $cipher = Crypto::encrypt($plain);

        $this->assertStringStartsWith('v1:', $cipher);
        $this->assertNotSame($plain, $cipher);
        $this->assertSame($plain, Crypto::decrypt($cipher));
    }

    public function testEmptyStringPassThrough(): void
    {
        $this->assertSame('', Crypto::encrypt(''));
        $this->assertSame('', Crypto::decrypt(''));
    }

    public function testEncryptIdempotentForAlreadyEncrypted(): void
    {
        $cipher = Crypto::encrypt('hello');
        // 二次加密应原样返回（v1: 前缀已存在，不再叠加）
        $this->assertSame($cipher, Crypto::encrypt($cipher));
    }

    public function testLegacyPlaintextPassesThroughDecrypt(): void
    {
        // 历史明文：无 v1: 前缀 → decrypt 直接原样返回，便于灰度迁移
        $this->assertSame('legacy-plaintext-api-key', Crypto::decrypt('legacy-plaintext-api-key'));
    }

    public function testTryDecryptReturnsNullOnMalformed(): void
    {
        // v1: 后跟随机非 base64 / 长度不足
        $this->assertNull(Crypto::tryDecrypt('v1:notvalidbase64payload'));
        $this->assertNull(Crypto::tryDecrypt('v1:' . base64_encode('short')));
    }

    public function testEncryptIsNonDeterministic(): void
    {
        // GCM 必须每次用新 IV，相同明文加密两次密文应不同
        $a = Crypto::encrypt('same-plaintext');
        $b = Crypto::encrypt('same-plaintext');
        $this->assertNotSame($a, $b);
        $this->assertSame('same-plaintext', Crypto::decrypt($a));
        $this->assertSame('same-plaintext', Crypto::decrypt($b));
    }

    public function testIsEncrypted(): void
    {
        $this->assertTrue(Crypto::isEncrypted(Crypto::encrypt('x')));
        $this->assertFalse(Crypto::isEncrypted('plaintext'));
        $this->assertFalse(Crypto::isEncrypted(''));
    }
}

<?php

namespace app\common\support;

use Throwable;

/**
 * 对称加密工具（AES-256-GCM）。
 *
 * 用途：
 *  - AI 模型 API Key、第三方账号密钥等敏感字段的"写入即加密、按需解密"
 *  - 任何不便明文落库但又必须能解开还原的场景
 *
 * 密钥来源：环境变量 `APP_CRYPTO_KEY`（推荐 32 字节 base64 编码），
 * 未配置时回退到 `JWT_SECRET`（带告警），生产部署应单独配置。
 *
 * 输出格式：`v1:<base64(iv|tag|ciphertext)>`
 *  - 前缀 v1 用于将来算法升级时的兼容性识别
 *  - 没有前缀时一律视为旧明文，按"读出后透传"处理（便于灰度迁移）
 */
class Crypto
{
    private const PREFIX = 'v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN  = 12;
    private const TAG_LEN = 16;

    /**
     * 加密字符串。
     *
     * 空串原样返回（避免给空字段也加上 v1: 前缀）。
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        if (self::isEncrypted($plaintext)) {
            // 已经是密文，不再二次加密（避免幂等性问题）
            return $plaintext;
        }

        $key = self::key();
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($cipher === false) {
            throw new \RuntimeException('Crypto encrypt failed');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * 解密字符串。
     *
     * 输入若不带 v1: 前缀（旧明文 / 历史数据），原样返回，便于灰度迁移。
     * 解密失败抛 RuntimeException —— 调用方一般是配置加载，无需对最终用户暴露。
     */
    public static function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '' || !self::isEncrypted($ciphertext)) {
            return $ciphertext;
        }

        $raw = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            throw new \RuntimeException('Crypto decrypt failed: malformed payload');
        }

        $iv   = substr($raw, 0, self::IV_LEN);
        $tag  = substr($raw, self::IV_LEN, self::TAG_LEN);
        $body = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt($body, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Crypto decrypt failed');
        }
        return $plain;
    }

    /**
     * 安全地解密：失败时返回 null 而非抛出。
     */
    public static function tryDecrypt(string $ciphertext): ?string
    {
        try {
            return self::decrypt($ciphertext);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 是否已加密（带 v1: 前缀）。
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * 获取并校验对称密钥。
     *
     * 优先级：APP_CRYPTO_KEY > JWT_SECRET（仅作兜底，生产应单独配置 APP_CRYPTO_KEY）。
     * 接受 base64 编码的 32 字节密钥，或直接的 32+ 字节字符串。
     */
    private static function key(): string
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }

        $raw = (string) (getenv('APP_CRYPTO_KEY') ?: env('APP_CRYPTO_KEY', '') ?: '');
        if ($raw === '') {
            // 兜底：用 JWT_SECRET 派生，避免空密钥导致全表无法解密
            $raw = (string) (getenv('JWT_SECRET') ?: env('JWT_SECRET', '') ?: '');
        }
        if ($raw === '') {
            throw new \RuntimeException('Crypto key not configured (set APP_CRYPTO_KEY or JWT_SECRET in .env)');
        }

        // 若是 base64，则解码；否则按 SHA-256 派生定长 key
        $decoded = base64_decode($raw, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $cached = $decoded;
        }
        return $cached = hash('sha256', $raw, true);
    }
}

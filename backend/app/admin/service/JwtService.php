<?php

namespace app\admin\service;

use app\admin\service\interface\JwtServiceInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/**
 * JWT 服务（HS256）
 *
 * 配置：
 *  - jwt.secret：签名密钥，至少 32 字符（生产环境建议 64+）
 *  - jwt.expire：Token 有效期，单位秒（默认 7200）
 *  - jwt.issuer：签发者标识（默认 admin）
 *
 * 使用：
 *   $token   = JwtService::getInstance()->encode(['user_id' => 1]);
 *   $payload = JwtService::getInstance()->decode($token); // 失败返回 null
 */
class JwtService implements JwtServiceInterface
{
    /** 默认 Token 有效期（秒） */
    private const DEFAULT_EXPIRE = 7200;

    /** 密钥最小长度 */
    private const MIN_SECRET_LENGTH = 32;

    /** 默认签发者标识 */
    private const DEFAULT_ISSUER = 'admin';

    /** 签名算法 */
    private const ALGO = 'HS256';

    private static ?self $instance = null;

    private string $secret;
    private int $expire;
    private string $issuer;

    private function __construct()
    {
        $cfg          = config('jwt', []);
        $this->secret = (string) ($cfg['secret'] ?? '');
        $this->expire = (int)    ($cfg['expire'] ?? self::DEFAULT_EXPIRE);
        $this->issuer = (string) ($cfg['issuer'] ?? self::DEFAULT_ISSUER);

        if (strlen($this->secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException(sprintf(
                'JWT secret too short, expected at least %d chars (current: %d). Please set JWT_SECRET in .env',
                self::MIN_SECRET_LENGTH,
                strlen($this->secret)
            ));
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 生成 Token。
     *
     * @param array<string,mixed> $claims 自定义负载（user_id 等）
     */
    public function encode(array $claims): string
    {
        $now = time();
        return JWT::encode(array_merge([
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->expire,
        ], $claims), $this->secret, self::ALGO);
    }

    /**
     * 验证并解析 Token。失败返回 null（过期 / 篡改 / 格式不正确）。
     *
     * @return array<string,mixed>|null
     */
    public function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGO));
            return (array) $decoded;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 获取 Token 有效期（秒）。
     */
    public function getExpire(): int
    {
        return $this->expire;
    }
}

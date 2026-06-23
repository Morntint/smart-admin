<?php

/**
 * JWT 配置
 *
 * 安全提示：
 *  - secret 必须 ≥ 32 字符，生产环境务必通过环境变量覆盖
 *  - 不要提交真实密钥到版本库
 *
 * 生成密钥示例：openssl rand -hex 32
 */
return [
    /** 签名密钥（HS256） */
    'secret' => env('JWT_SECRET', 'change-me-in-production-please-set-a-strong-key'),

    /** Token 有效期（秒），默认 2 小时 */
    'expire' => (int) env('JWT_EXPIRE', 7200),

    /** 签发者标识，写入 payload.iss 字段 */
    'issuer' => env('JWT_ISSUER', 'admin'),
];

<?php

namespace app\common\support;

use support\Context;

/**
 * 请求链路上下文
 *
 * 在请求入口（TraceMiddleware）生成或透传 X-Request-Id，存入协程/请求上下文，
 * 供日志 Processor、异常 Handler、业务代码在任意位置读取，实现"一个 id 串全链路"。
 *
 * 为什么用 support\Context 而非中间件实例字段：
 *  - Context 由框架按请求（协程驱动下按协程）隔离，进程模式与协程模式下都安全；
 *  - 不会出现单例中间件实例字段在协程并发下串号的问题。
 *
 * 用法：
 *   TraceContext::id();                 // 读取当前请求的 request_id（无则返回空串）
 *   support\Log::info('msg');           // 日志会自动带上 request_id（见 config/log.php processor）
 */
class TraceContext
{
    /** Context 中存储 request_id 的键 */
    private const KEY = 'trace.request_id';

    /** request_id 长度（hex 字符数） */
    private const ID_BYTES = 16;

    /**
     * 写入当前请求的 request_id。
     */
    public static function set(string $requestId): void
    {
        Context::set(self::KEY, $requestId);
    }

    /**
     * 读取当前请求的 request_id；不存在返回空串。
     */
    public static function id(): string
    {
        return (string) (Context::get(self::KEY) ?? '');
    }

    /**
     * 生成一个新的 request_id（32 位十六进制）。
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(self::ID_BYTES));
    }
}

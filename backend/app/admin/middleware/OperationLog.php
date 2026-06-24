<?php

namespace app\admin\middleware;

use app\common\ResponseCode;
use app\model\SysOperationLog;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Webman\RedisQueue\Redis as RedisQueue;

/**
 * 操作日志中间件
 *
 * 自动记录写操作（POST/PUT/PATCH/DELETE）到 sys_operation_log 表，
 * 抓取请求参数、响应内容、耗时与异常信息，避免在控制器中散落手动写入。
 *
 * 性能：日志数据在请求上下文内组装后投递到 redis-queue（O(1)），由 consumer 进程
 *      落库（app/queue/redis/OperationLogConsumer），请求链路不等待数据库写入，避免高并发下卡顿。
 *
 * 使用：在 config/middleware.php 的 admin 链中追加本类即可。
 *
 * 安全：
 *  - 自动过滤敏感字段（password / *_password / *_token）
 *  - 解析失败一律静默忽略，不影响主业务链路
 */
class OperationLog implements MiddlewareInterface
{
    /** 写操作 HTTP 方法 */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** redis-queue 队列名，需与 OperationLogConsumer::$queue 一致 */
    private const QUEUE = 'operation_log';

    /** 不记录的请求路径前缀（登录、退出、刷新 token、文件上传、公开接口、日志） */
    private const SKIP_PATTERNS = [
        '/admin/login',
        '/admin/logout',
        '/admin/refresh',
        '/admin/captcha',
        '/admin/file',
        '/admin/public/',
        '/admin/log/',
    ];

    /** 敏感字段：写日志前清除 */
    private const SENSITIVE_FIELDS = [
        'password',
        'old_password',
        'new_password',
        'confirm_password',
        'token',
        'access_token',
        'refresh_token',
        'captcha',
        'captcha_key',
    ];

    public function process(Request $request, callable $next): Response
    {
        if (!$this->shouldLog($request)) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = null;
        $thrown   = null;
        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        // 入队（O(1)）：使用 try/catch 防止队列异常影响主业务
        try {
            $this->enqueueLog($request, $response, $startTime, $thrown);
        } catch (Throwable $e) {
            // 不阻断主流程，但落到 webman 日志，便于排查
            Log::warning('OperationLog middleware enqueue failed: ' . $e->getMessage(), [
                'exception' => $e,
                'path'      => $request->path(),
                'method'    => $request->method(),
            ]);
        }

        if ($thrown !== null) {
            throw $thrown;
        }

        return $response;
    }

    /**
     * 判断当前请求是否需要记录。
     */
    private function shouldLog(Request $request): bool
    {
        if (!in_array($request->method(), self::WRITE_METHODS, true)) {
            return false;
        }

        $path = '/' . ltrim($request->path(), '/');
        if (!str_starts_with($path, '/admin/')) {
            return false;
        }

        foreach (self::SKIP_PATTERNS as $pattern) {
            if ($path === $pattern || str_starts_with($path, $pattern)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 组装日志数据并推入队列（不直接写库）。
     *
     * @param Response|null  $response  上游响应；为 null 表示业务链路抛了异常
     * @param Throwable|null $exception 业务异常（若有）
     */
    private function enqueueLog(Request $request, ?Response $response, float $startTime, ?Throwable $exception = null): void
    {
        $body = $response ? $this->decodeResponse($response) : [];

        if ($exception !== null) {
            $status   = false;
            $errorMsg = $exception->getMessage() !== '' ? $exception->getMessage() : get_class($exception);
        } else {
            $status   = (int) ($body['code'] ?? 0) === ResponseCode::SUCCESS->value;
            $errorMsg = $status ? null : ($body['msg'] ?? '未知错误');
        }

        $param = array_merge($request->post() ?: [], $request->get() ?: []);
        foreach (self::SENSITIVE_FIELDS as $field) {
            unset($param[$field]);
        }

        // 在请求上下文内组装整行（含 ip/user_agent/created_at），消费进程脱离请求也能直接落库
        $row = SysOperationLog::buildRow(
            method:   $request->method(),
            url:      '/' . ltrim($request->path(), '/'),
            userId:   $request->admin_user_id ?? null,
            username: $request->admin_user['username'] ?? null,
            module:   $this->extractModule($request->path()),
            param:    $param ?: null,
            result:   $body  ?: null,
            status:   $status,
            errorMsg: $errorMsg,
            duration: (int) round((microtime(true) - $startTime) * 1000),
        );

        RedisQueue::send(self::QUEUE, $row);
    }

    /**
     * 解析响应体为数组（仅 JSON 可解析时）。
     *
     * @return array<string,mixed>
     */
    private function decodeResponse(Response $response): array
    {
        $raw = (string) $response->rawBody();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * 从路径中提取模块名（admin/user → user）。
     */
    private function extractModule(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path)));
        return $parts[1] ?? ($parts[0] ?? '');
    }
}

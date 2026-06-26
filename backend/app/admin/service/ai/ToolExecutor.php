<?php

namespace app\admin\service\ai;

use app\common\exception\BusinessException;
use app\model\AiTool;
use support\Log;

/**
 * AI 工具执行器
 *
 * 负责根据工具定义调用对应的处理器
 * 支持三种工具类型：
 * - function: 调用本地类方法 (handler = "ClassName@method")
 * - api: 调用外部 HTTP API (handler = URL)
 * - plugin: 调用插件（预留）
 */
class ToolExecutor
{
    /**
     * 执行工具调用
     *
     * @param AiTool $tool 工具定义
     * @param array  $args 调用参数
     * @param array  $context 上下文信息（如用户ID、会话ID等）
     * @return mixed 执行结果
     */
    public static function execute(AiTool $tool, array $args, array $context = []): mixed
    {
        $toolType = $tool->tool_type ?? 'function';
        $handler  = $tool->handler;
        $config   = $tool->config ?? [];

        if (empty($handler)) {
            throw new BusinessException("工具 {$tool->code} 未配置处理器");
        }

        Log::info('执行工具调用', [
            'tool'  => $tool->code,
            'type'  => $toolType,
            'args'  => $args,
            'context' => $context,
        ]);

        try {
            $result = match ($toolType) {
                'function' => self::callFunction($handler, $args, $config, $context),
                'api'      => self::callApi($handler, $args, $config, $context),
                'plugin'   => self::callPlugin($handler, $args, $config, $context),
                default    => throw new BusinessException("不支持的工具类型: {$toolType}"),
            };

            Log::debug('工具执行成功', [
                'tool' => $tool->code,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('工具执行失败', [
                'tool'    => $tool->code,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            // 返回结构化错误信息，便于 AI 理解
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool' => $tool->code,
                'message' => "工具 {$tool->name} 执行失败: " . $e->getMessage(),
            ];
        }
    }

    /**
     * 调用本地函数/类方法
     *
     * handler 格式:
     * - "App\Services\WeatherService@getCurrent"  类方法
     * - "time"                                    全局函数
     */
    private static function callFunction(string $handler, array $args, array $config, array $context): mixed
    {
        if (str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (!class_exists($class)) {
                throw new BusinessException("类不存在: {$class}");
            }
            $instance = new $class();
            if (!method_exists($instance, $method)) {
                throw new BusinessException("方法不存在: {$class}::{$method}");
            }
            return $instance->$method($args, $config, $context);
        }

        if (function_exists($handler)) {
            return $handler($args, $config, $context);
        }

        throw new BusinessException("函数不存在: {$handler}");
    }

    /**
     * 调用外部 API
     */
    private static function callApi(string $url, array $args, array $config, array $context): mixed
    {
        $method   = $config['method'] ?? 'POST';
        $headers  = $config['headers'] ?? [];
        $timeout  = $config['timeout'] ?? 30;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
        } elseif (strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($args);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new BusinessException("API 请求失败: {$error}");
        }

        if ($httpCode >= 400) {
            throw new BusinessException("API 返回错误: HTTP {$httpCode}");
        }

        $decoded = json_decode($response, true);
        return $decoded ?? $response;
    }

    /**
     * 调用插件（预留）
     */
    private static function callPlugin(string $handler, array $args, array $config, array $context): mixed
    {
        throw new BusinessException("插件类型工具暂未实现");
    }
}

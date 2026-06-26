<?php

namespace app\admin\service\ai\tools;

use app\admin\service\ai\DateParser;
use app\model\SysLoginLog;
use app\model\SysOperationLog;

/**
 * 系统日志查询工具
 *
 * 允许 AI Agent 查询操作日志（sys_operation_log）和登录日志（sys_login_log）。
 * 在 AiTool 中配置 handler 为: "app\admin\service\ai\tools\QueryLogTool@queryOperationLogs"
 * 或 "app\admin\service\ai\tools\QueryLogTool@queryLoginLogs"
 */
class QueryLogTool
{
    private const MAX_LIMIT = 50;

    /**
     * 查询操作日志
     *
     * @param array $args     参数
     * @param array $config   工具配置
     * @param array $context  上下文
     * @return array
     */
    public function queryOperationLogs(array $args, array $config, array $context): array
    {
        $query = SysOperationLog::query();

        $keyword = trim($args['keyword'] ?? '');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('url', 'like', "%{$keyword}%");
            });
        }

        $module = trim($args['module'] ?? '');
        if ($module !== '') {
            $query->where('module', $module);
        }

        $method = trim($args['method'] ?? '');
        if ($method !== '') {
            $query->where('method', $method);
        }

        if (isset($args['status']) && $args['status'] !== '') {
            $query->where('status', (int) $args['status']);
        }

        // 使用智能日期解析器，支持相对日期（如 today, this_week）
        $startDate = DateParser::parse($args['start_date'] ?? '', 'start');
        $endDate   = DateParser::parse($args['end_date'] ?? '', 'end');

        if ($startDate !== '') {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== '') {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $page  = max(1, (int) ($args['page'] ?? 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($args['limit'] ?? 15)));

        $total = (clone $query)->count();
        $list  = $query->orderBy('id', 'desc')
                       ->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->get()
                       ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'list'  => $list,
        ];
    }

    /**
     * 查询登录日志
     *
     * @param array $args     参数
     * @param array $config   工具配置
     * @param array $context  上下文
     * @return array
     */
    public function queryLoginLogs(array $args, array $config, array $context): array
    {
        $query = SysLoginLog::query();

        $keyword = trim($args['keyword'] ?? '');
        if ($keyword !== '') {
            $query->where('username', 'like', "%{$keyword}%");
        }

        if (isset($args['status']) && $args['status'] !== '') {
            $query->where('status', (int) $args['status']);
        }

        $loginType = trim($args['login_type'] ?? '');
        if ($loginType !== '') {
            $query->where('login_type', (int) $loginType);
        }

        // 使用智能日期解析器，支持相对日期（如 today, this_week）
        $startDate = DateParser::parse($args['start_date'] ?? '', 'start');
        $endDate   = DateParser::parse($args['end_date'] ?? '', 'end');

        if ($startDate !== '') {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== '') {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $page  = max(1, (int) ($args['page'] ?? 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($args['limit'] ?? 15)));

        $total = (clone $query)->count();
        $list  = $query->orderBy('id', 'desc')
                       ->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->get()
                       ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'list'  => $list,
        ];
    }
}

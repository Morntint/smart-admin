<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysLoginLog;
use app\model\SysOperationLog;
use support\Request;

/**
 * 日志业务服务
 *
 * 统一管理操作日志（sys_operation_log）与登录日志（sys_login_log）。
 *
 * 日志写入：
 *  - 操作日志由 OperationLog 中间件自动写入（写操作捕获）
 *  - 登录日志由 LoginService 写入
 *
 * 本服务只负责：列表查询 / 统计 / 详情 / 删除 / 清理。
 */
class LogService extends BaseService
{
    /** 默认登录日志保留天数（清理用） */
    private const DEFAULT_LOGIN_RETAIN_DAYS = 90;

    /** 默认操作日志保留天数（清理用） */
    private const DEFAULT_OPERATION_RETAIN_DAYS = 30;

    /** Service 默认绑定操作日志模型 */
    protected string $modelClass = SysOperationLog::class;

    // -------------------------------------------------------------------------
    // 操作日志
    // -------------------------------------------------------------------------

    /**
     * 操作日志分页列表。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function operationPageList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'module' => $request->get('module', ''),
            'method' => $request->get('method', ''),
            'status' => $request->get('status', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['username', 'url']
        );
        $this->applyDateRange(
            $query,
            'created_at',
            (string) $request->get('start_date', ''),
            (string) $request->get('end_date', '')
        );
        return $this->paginate($query, $request);
    }

    /**
     * 操作日志统计（最近 N 天的每日量 + 累计量 + 当日量）。
     *
     * @return array{statistics:array<int,array{date:string,count:int}>,total:int,today:int}
     */
    public function operationStatistics(int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $rows      = SysOperationLog::where('created_at', '>=', $startDate . ' 00:00:00')
                                    ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
                                    ->groupBy('date')
                                    ->pluck('cnt', 'date');

        $statistics = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date         = date('Y-m-d', strtotime("-{$i} days"));
            $statistics[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
        }

        return [
            'statistics' => $statistics,
            'total'      => SysOperationLog::count(),
            'today'      => (int) ($rows[date('Y-m-d')] ?? 0),
        ];
    }

    /**
     * 操作日志详情。
     */
    public function operationDetail(int $id): SysOperationLog
    {
        /** @var SysOperationLog $log */
        $log = $this->findOrFail($id, [], '日志不存在');
        $log->formatted_param = $log->getFormattedParam();
        return $log;
    }

    /**
     * 删除单条操作日志。
     */
    public function operationDelete(int $id): void
    {
        $this->findOrFail($id, [], '日志不存在')->delete();
    }

    /**
     * 批量删除操作日志。
     *
     * @param int[] $ids
     */
    public function operationBatchDelete(array $ids): int
    {
        if ($ids === []) {
            throw BusinessException::badRequest('请选择要删除的日志');
        }
        return SysOperationLog::whereIn('id', $ids)->delete();
    }

    /**
     * 清理 N 天前的操作日志。
     */
    public function operationClear(int $days = self::DEFAULT_OPERATION_RETAIN_DAYS): int
    {
        $deadline = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return SysOperationLog::where('created_at', '<', $deadline)->delete();
    }

    // -------------------------------------------------------------------------
    // 登录日志
    // -------------------------------------------------------------------------

    /**
     * 登录日志分页列表。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function loginPageList(Request $request): array
    {
        $query = SysLoginLog::query();
        $this->applyFilters($query, filters: [
            'status'     => $request->get('status', ''),
            'login_type' => $request->get('login_type', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['username']
        );
        $this->applyDateRange(
            $query,
            'created_at',
            (string) $request->get('start_date', ''),
            (string) $request->get('end_date', '')
        );
        return $this->paginate($query, $request);
    }

    /**
     * 登录日志统计（最近 N 天 + 当日 + 在线人数）。
     *
     * @return array<string,mixed>
     */
    public function loginStatistics(int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $today     = date('Y-m-d');

        $rows = SysLoginLog::where('created_at', '>=', $startDate . ' 00:00:00')
                           ->selectRaw('DATE(created_at) as date, login_type, status, COUNT(*) as cnt')
                           ->groupByRaw('DATE(created_at), login_type, status')
                           ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->date][$row->login_type][$row->status] = (int) $row->cnt;
        }

        $statistics = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date         = date('Y-m-d', strtotime("-{$i} days"));
            $d            = $indexed[$date] ?? [];
            $statistics[] = [
                'date'   => $date,
                'login'  => $d[SysLoginLog::TYPE_LOGIN][SysLoginLog::STATUS_SUCCESS] ?? 0,
                'logout' => array_sum($d[SysLoginLog::TYPE_LOGOUT] ?? []),
                'fail'   => $d[SysLoginLog::TYPE_LOGIN][SysLoginLog::STATUS_FAIL]    ?? 0,
            ];
        }

        $todayData = $indexed[$today] ?? [];
        return [
            'statistics' => $statistics,
            'today'      => [
                'login' => $todayData[SysLoginLog::TYPE_LOGIN][SysLoginLog::STATUS_SUCCESS] ?? 0,
                'fail'  => $todayData[SysLoginLog::TYPE_LOGIN][SysLoginLog::STATUS_FAIL]    ?? 0,
            ],
            'online' => SysLoginLog::whereDate('created_at', $today)
                                   ->where('login_type', SysLoginLog::TYPE_LOGIN)
                                   ->where('status', SysLoginLog::STATUS_SUCCESS)
                                   ->distinct('user_id')
                                   ->count('user_id'),
        ];
    }

    /**
     * 登录日志详情。
     */
    public function loginDetail(int $id): SysLoginLog
    {
        return $this->findOrFailLoginLog($id);
    }

    /**
     * 删除单条登录日志。
     */
    public function loginDelete(int $id): void
    {
        $this->findOrFailLoginLog($id)->delete();
    }

    /**
     * 批量删除登录日志。
     *
     * @param int[] $ids
     */
    public function loginBatchDelete(array $ids): int
    {
        if ($ids === []) {
            throw BusinessException::badRequest('请选择要删除的日志');
        }
        return SysLoginLog::whereIn('id', $ids)->delete();
    }

    /**
     * 清理 N 天前的登录日志。
     */
    public function loginClear(int $days = self::DEFAULT_LOGIN_RETAIN_DAYS): int
    {
        $deadline = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return SysLoginLog::where('created_at', '<', $deadline)->delete();
    }

    /**
     * 登录日志找不到时抛业务异常。
     */
    private function findOrFailLoginLog(int $id): SysLoginLog
    {
        $model = SysLoginLog::find($id);
        if (!$model) {
            throw BusinessException::notFound('日志不存在');
        }
        return $model;
    }
}

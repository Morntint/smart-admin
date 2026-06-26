<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\model\AiUsageRecord;
use Illuminate\Support\Facades\DB;
use support\Request;

/**
 * AI 用量统计服务
 */
class AiUsageService extends BaseService
{
    protected string $modelClass = AiUsageRecord::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        $query = AiUsageRecord::query();

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['model_name', 'endpoint']);
        $this->applyFilters($query, [
            'user_id'   => $request->get('user_id'),
            'agent_id'  => $request->get('agent_id'),
            'model_name' => $request->get('model_name'),
            'status'    => $request->get('status'),
        ]);

        $startDate = (string) $request->get('start_date', '');
        $endDate   = (string) $request->get('end_date', '');
        if ($startDate !== '' && $endDate !== '') {
            $this->applyDateRange($query, 'created_at', $startDate, $endDate);
        }

        return $this->paginate($query, $request);
    }

    /**
     * 汇总统计
     */
    public function summary(Request $request): array
    {
        $startDate = (string) $request->get('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate   = (string) $request->get('end_date', date('Y-m-d'));

        $query = AiUsageRecord::query()
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59');

        // 总量统计
        $totals = (clone $query)->selectRaw('
            COUNT(*) as total_calls,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(SUM(cost), 0) as total_cost,
            COALESCE(AVG(duration), 0) as avg_duration
        ')->first();

        // 按模型统计
        $byModel = (clone $query)->selectRaw('
            model_name,
            COUNT(*) as calls,
            COALESCE(SUM(total_tokens), 0) as tokens,
            COALESCE(SUM(cost), 0) as cost
        ')->groupBy('model_name')->orderByDesc('calls')->get();

        // 按日期趋势
        $trend = (clone $query)->selectRaw('
            DATE(created_at) as date,
            COUNT(*) as calls,
            COALESCE(SUM(total_tokens), 0) as tokens,
            COALESCE(SUM(cost), 0) as cost
        ')->groupBy('date')->orderBy('date')->get();

        return [
            'total_calls'  => (int) ($totals->total_calls ?? 0),
            'total_tokens' => (int) ($totals->total_tokens ?? 0),
            'total_cost'   => round((float) ($totals->total_cost ?? 0), 4),
            'avg_duration' => round((float) ($totals->avg_duration ?? 0), 0),
            'by_model'     => $byModel->toArray(),
            'trend'        => $trend->toArray(),
        ];
    }
}

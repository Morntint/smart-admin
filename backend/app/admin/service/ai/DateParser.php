<?php

namespace app\admin\service\ai;

/**
 * AI 工具日期解析器
 *
 * 支持相对日期格式，让 AI 可以直接使用 "today", "this_week" 等
 * 避免 AI 因为缺少实时时间感知而生成错误日期
 */
class DateParser
{
    /**
     * 允许的年份下界相对偏移：currentYear - LOWER_BOUND_YEARS_BACK_DEFAULT。
     *
     * 旧实现硬编码 `< 2024`：随时间推移会把越来越多合法历史日期吞掉。
     * 现在改为相对偏移：5 年回溯窗口在业务日志场景下完全够用，
     * 且这是个从未需要 per-env 调整的参数 —— 直接以常量提供单源真相，
     * 不再走 config('ai.date_parser.*') 这层间接。
     */
    private const LOWER_BOUND_YEARS_BACK_DEFAULT = 5;

    /**
     * 解析日期参数，支持相对日期
     *
     * @param string|null $date 日期字符串，支持：
     *   - 绝对日期：2026-06-26
     *   - 相对日期：today, yesterday, this_week, this_month, last_7_days, last_30_days
     * @param string $type 'start' 或 'end'，用于确定相对日期的范围
     * @return string 解析后的 Y-m-d 格式日期
     */
    public static function parse(?string $date, string $type = 'start'): string
    {
        if (empty($date)) {
            return '';
        }

        $currentYear = (int) date('Y');
        $minYear     = $currentYear - self::LOWER_BOUND_YEARS_BACK_DEFAULT;

        // 如果已经是 Y-m-d 格式，直接返回（但要做有效性检查）
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $year = (int) substr($date, 0, 4);
            if ($year < $minYear || $year > $currentYear + 1) {
                // 年份不合理（早于下界 / 超过未来 1 年），保守使用今天
                return date('Y-m-d');
            }
            // 检查日期是否在未来
            if ($date > date('Y-m-d')) {
                return date('Y-m-d');
            }
            return $date;
        }

        // 解析相对日期
        $today = date('Y-m-d');

        switch (strtolower(trim($date))) {
            case 'today':
                return $today;

            case 'yesterday':
                return date('Y-m-d', strtotime('-1 day'));

            case 'this_week':
                // 本周一到本周日
                if ($type === 'start') {
                    return date('Y-m-d', strtotime('monday this week'));
                }
                return date('Y-m-d', strtotime('sunday this week'));

            case 'this_month':
                // 本月第一天到本月最后一天
                if ($type === 'start') {
                    return date('Y-m-01');
                }
                return date('Y-m-t');

            case 'last_7_days':
            case 'last7days':
                if ($type === 'start') {
                    return date('Y-m-d', strtotime('-6 days'));
                }
                return $today;

            case 'last_30_days':
            case 'last30days':
                if ($type === 'start') {
                    return date('Y-m-d', strtotime('-29 days'));
                }
                return $today;

            case 'last_week':
                if ($type === 'start') {
                    return date('Y-m-d', strtotime('monday last week'));
                }
                return date('Y-m-d', strtotime('sunday last week'));

            case 'last_month':
                if ($type === 'start') {
                    return date('Y-m-01', strtotime('-1 month'));
                }
                return date('Y-m-t', strtotime('-1 month'));

            default:
                // 尝试用 strtotime 解析
                $timestamp = strtotime($date);
                if ($timestamp !== false) {
                    $parsed = date('Y-m-d', $timestamp);
                    $year = (int) substr($parsed, 0, 4);
                    if ($year >= $minYear && $year <= $currentYear + 1) {
                        return $parsed;
                    }
                }
                // 解析失败，使用今天
                return $today;
        }
    }

    /**
     * 获取推荐的相对日期选项（用于工具参数 schema）
     */
    public static function getRelativeDateOptions(): array
    {
        return [
            'today',
            'yesterday',
            'this_week',
            'this_month',
            'last_7_days',
            'last_30_days',
            'last_week',
            'last_month',
        ];
    }

    /**
     * 获取相对日期说明（用于提示词）
     */
    public static function getRelativeDateDescription(): string
    {
        return <<<EOD
日期参数支持以下格式：
1. 绝对日期：YYYY-MM-DD（如 2026-06-26）
2. 相对日期（推荐使用，避免日期错误）：
   - today: 今天
   - yesterday: 昨天
   - this_week: 本周（周一到周日）
   - this_month: 本月（第一天到最后一天）
   - last_7_days: 最近7天（含今天）
   - last_30_days: 最近30天（含今天）
   - last_week: 上周
   - last_month: 上月
EOD;
    }
}

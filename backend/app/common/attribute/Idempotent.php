<?php

namespace app\common\attribute;

use Attribute;

/**
 * 幂等/防重复提交注解
 *
 * 标注在写操作控制器方法上，由 Idempotent 中间件在窗口期内拦截重复请求：
 *  - 同一用户 + 同一接口 + 相同参数指纹，在 window 秒内只允许执行一次；
 *  - 重复请求直接返回 409，不会重复落库（防误触双击、网络重发、重复下单）。
 *
 * 幂等键来源（优先级）：
 *  1. 请求头 Idempotency-Key（客户端显式提供，最精确）；
 *  2. 否则由「用户ID + 控制器::方法 + 请求参数指纹」自动生成。
 *
 * 用法：
 *   #[Idempotent]                 // 默认 5 秒窗口，按参数指纹去重
 *   #[Idempotent(window: 10)]     // 自定义窗口
 *   #[Idempotent(useParams: false)] // 不计入参数，仅「用户+接口」级别去重（更激进）
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Idempotent
{
    /**
     * @param int    $window    幂等窗口（秒）
     * @param bool   $useParams 是否把请求参数纳入指纹（false 时同接口任意参数都视为重复）
     * @param string $message   命中重复时的提示语
     */
    public function __construct(
        public readonly int $window = 5,
        public readonly bool $useParams = true,
        public readonly string $message = '请勿重复提交',
    ) {
    }
}

<?php

namespace app\common\attribute;

use Attribute;

/**
 * 接口限流注解
 *
 * 标注在控制器方法上，声明该接口在「时间窗口」内允许的最大请求次数。
 * 由 RateLimit 中间件通过反射读取并基于 Redis 滑动/固定窗口计数：
 *  - 未标注本注解的方法默认不限流；
 *  - 超过阈值返回 429，并带 Retry-After 响应头。
 *
 * 限流维度（key）：
 *  - by='ip'    ：按客户端 IP（默认，适合登录/验证码等未登录接口）
 *  - by='user'  ：按登录用户 ID（适合已登录的写接口；未登录时回退到 IP）
 *  - by='global'：全局共享一个计数器（适合保护下游的全局开关）
 *
 * 用法：
 *   #[RateLimit(limit: 60, window: 60)]                 // 每 60 秒最多 60 次（按 IP）
 *   #[RateLimit(limit: 5,  window: 60, by: 'user')]     // 每个用户每分钟最多 5 次
 *   #[RateLimit(limit: 10, window: 1,  key: 'login')]   // 自定义业务名（同名共享计数）
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit
{
    /**
     * @param int    $limit  窗口内允许的最大请求次数
     * @param int    $window 时间窗口（秒）
     * @param string $by     限流维度：ip|user|global
     * @param string $key    自定义业务标识（默认空，用「控制器::方法」区分）
     */
    public function __construct(
        public readonly int $limit = 60,
        public readonly int $window = 60,
        public readonly string $by = 'ip',
        public readonly string $key = '',
    ) {
    }
}

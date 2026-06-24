<?php

namespace app\admin\middleware;

use app\common\attribute\RateLimit as RateLimitAttribute;
use app\common\exception\TooManyRequestsException;
use app\common\support\RateLimiter;
use ReflectionAttribute;
use ReflectionMethod;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 接口限流中间件
 *
 * 读取控制器方法上的 #[RateLimit] 注解，按维度（ip/user/global）做 Redis 固定窗口限流：
 *  - 未标注注解的方法默认不限流，零开销放行；
 *  - 超过阈值抛 TooManyRequestsException（429），由全局 Handler 渲染；
 *  - 所有受限接口在响应头返回 X-RateLimit-Limit / X-RateLimit-Remaining / X-RateLimit-Reset。
 *
 * 注册：置于 admin 链中 AuthMiddleware 之后——这样 by='user' 能拿到 admin_user_id；
 *      登录/验证码这类未登录接口用 by='ip' 即可。
 *
 * 注解读取做了进程内 static 缓存（与 AuthMiddleware 一致），反射只在首次命中时发生。
 */
class RateLimit implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $rule = $this->resolveRule($request);
        if ($rule === null) {
            return $handler($request);
        }

        $identifier = $this->buildIdentifier($request, $rule);
        $result     = RateLimiter::hit($identifier, $rule->limit, $rule->window);

        if (!$result['allowed']) {
            throw new TooManyRequestsException(
                '请求过于频繁，请稍后再试',
                $result['reset']
            );
        }

        $response = $handler($request);

        // 回写限流头，便于前端/网关感知配额
        $response->withHeaders([
            'X-RateLimit-Limit'     => (string) $result['limit'],
            'X-RateLimit-Remaining' => (string) $result['remaining'],
            'X-RateLimit-Reset'     => (string) $result['reset'],
        ]);

        return $response;
    }

    /**
     * 构建限流标识：业务名 + 维度 + 维度值。
     */
    private function buildIdentifier(Request $request, RateLimitAttribute $rule): string
    {
        $scope = $rule->key !== ''
            ? $rule->key
            : ($request->controller ?? '') . '::' . ($request->action ?? '');

        $dimension = match ($rule->by) {
            'user'   => 'user:' . ((int) ($request->admin_user_id ?? 0) ?: 'guest:' . $request->getRealIp()),
            'global' => 'global',
            default  => 'ip:' . $request->getRealIp(),
        };

        return $scope . '|' . $dimension;
    }

    /**
     * 读取控制器方法上的 #[RateLimit] 注解（带进程内 static 缓存）。
     *
     * @return RateLimitAttribute|null null 表示该方法不限流
     */
    private function resolveRule(Request $request): ?RateLimitAttribute
    {
        $controller = (string) ($request->controller ?? '');
        $action     = (string) ($request->action ?? '');

        if ($controller === '' || $action === '' || !class_exists($controller) || !method_exists($controller, $action)) {
            return null;
        }

        static $cache = [];
        $key = "{$controller}::{$action}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $attributes = (new ReflectionMethod($controller, $action))
            ->getAttributes(RateLimitAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

        return $cache[$key] = $attributes === []
            ? null
            : $attributes[0]->newInstance();
    }
}

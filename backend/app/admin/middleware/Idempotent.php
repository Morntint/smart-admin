<?php

namespace app\admin\middleware;

use app\common\attribute\Idempotent as IdempotentAttribute;
use app\common\exception\BusinessException;
use app\common\ResponseCode;
use ReflectionAttribute;
use ReflectionMethod;
use support\Redis;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 幂等/防重复提交中间件
 *
 * 读取控制器方法上的 #[Idempotent] 注解，对写操作做窗口期去重：
 *  - 用 Redis SET NX EX 抢占幂等键，抢到才放行，抢不到说明窗口内已有相同请求 → 409；
 *  - 幂等键 = 前缀 + 用户ID + 控制器::方法 + (可选)参数指纹，或客户端 Idempotency-Key 头；
 *  - 键在窗口期内**不主动释放**（防止快速连点/重发），到期自动失效。
 *
 * 注册：admin 链中置于 AuthMiddleware 之后（需要 admin_user_id）、OperationLog 之前
 *      （重复请求不该被记入操作日志）。
 *
 * 容错：Redis 不可用时 fail-open 放行，幂等组件自身故障不阻断业务。
 *
 * 注意：仅对标注了注解的写接口生效；GET 等读接口即便误标也会因无副作用而无碍。
 */
class Idempotent implements MiddlewareInterface
{
    /** 幂等键前缀 */
    private const PREFIX = 'idempotent:';

    public function process(Request $request, callable $handler): Response
    {
        $rule = $this->resolveRule($request);
        if ($rule === null) {
            return $handler($request);
        }

        $key      = self::PREFIX . $this->buildFingerprint($request, $rule);
        $acquired = $this->acquire($key, $rule->window);

        // acquire 返回 null 表示 Redis 故障 → fail-open 放行
        if ($acquired === false) {
            throw new BusinessException($rule->message, ResponseCode::CONFLICT);
        }

        return $handler($request);
    }

    /**
     * 抢占幂等键。
     *
     * @return bool|null true=抢到（放行）；false=已存在（重复）；null=Redis 故障（放行）
     */
    private function acquire(string $key, int $window): ?bool
    {
        try {
            // SET key 1 EX window NX：不存在才写入并设过期，原子操作
            $ok = Redis::set($key, '1', 'EX', max(1, $window), 'NX');
            return (bool) $ok;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 构建幂等指纹：优先用客户端 Idempotency-Key 头，否则按 用户+接口(+参数) 生成。
     */
    private function buildFingerprint(Request $request, IdempotentAttribute $rule): string
    {
        $userId = (int) ($request->admin_user_id ?? 0);
        $scope  = ($request->controller ?? '') . '::' . ($request->action ?? '');

        $clientKey = trim((string) $request->header('idempotency-key', ''));
        if ($clientKey !== '') {
            return $userId . ':' . $scope . ':' . md5($clientKey);
        }

        $paramHash = '';
        if ($rule->useParams) {
            $params = array_merge($request->post() ?: [], $request->get() ?: []);
            ksort($params);
            $paramHash = md5((string) json_encode($params, JSON_UNESCAPED_UNICODE));
        }

        return $userId . ':' . $scope . ':' . $paramHash;
    }

    /**
     * 读取控制器方法上的 #[Idempotent] 注解（带进程内 static 缓存）。
     *
     * @return IdempotentAttribute|null null 表示该方法不做幂等
     */
    private function resolveRule(Request $request): ?IdempotentAttribute
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
            ->getAttributes(IdempotentAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

        return $cache[$key] = $attributes === []
            ? null
            : $attributes[0]->newInstance();
    }
}

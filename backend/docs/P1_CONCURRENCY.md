# P1 并发优化说明（缓存防护 / 幂等 / 协程前置）

> 对应 `docs/HIGH_CONCURRENCY_ENTERPRISE.md` 的 P1。
> 协程实际切换见 `docs/COROUTINE.md`。本文记录 2026-06-24 落地的实现与用法。

---

## 一、缓存防护（CacheGuard / cache_remember）

### 做了什么

把"读缓存 → 未命中 → 回源 → 写缓存"封装为带三层防护的 `cache_remember()`：

| 问题 | 防护手段 |
|---|---|
| **穿透**（查不存在的数据，每次打 DB） | 回源为 `null` 时写入空值标记 `__CACHE_NULL__`（短 TTL 30s） |
| **击穿**（热点 key 失效瞬间并发打满 DB） | Redis 互斥锁，仅放一个请求回源，其余轮询等待新值 |
| **雪崩**（大量 key 同时过期） | 写入正常值时 TTL 叠加 ±10% 随机抖动 |

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/support/CacheGuard.php` | 防护核心：remember / 互斥锁 / 抖动 / 空值标记 |
| `app/helper.php` | `cache_remember()` 全局帮助函数 |
| `app/model/SysConfig.php` | 已接入：系统配置回源用 `cache_remember` |

### 用法

```php
// 命中返回；未命中时单请求回源 + 写缓存（含三层防护）
$cfg = cache_remember('system_config', 86400, fn() => SysConfig::loadAll());

// null 也会被短缓存，防止恶意 id 反复穿透
$user = cache_remember("user_profile_{$id}", 300, fn() => SysUser::find($id));
```

### 关键设计

- **fail-open**：Redis（互斥锁）不可用时，调用方自行回源，不阻塞、不报错。
- **double-check**：抢到锁后再读一次缓存，避免等锁期间已被回填还重复查库。
- **锁自动过期**：锁 TTL 10s，持锁进程崩溃也不会死锁；释放用 Lua 校验 token 防误删。
- **值存取复用 `cache()`**：file / redis / array 驱动通用；锁依赖 Redis。

> 写操作后记得失效缓存：`CacheGuard::forget($key)` 或 `cache()->delete($key)`
> （系统配置已有 `SysConfig::clearCache()`）。

---

## 二、幂等 / 防重复提交（Idempotent）

### 做了什么

注解式写操作去重，防止双击、网络重发、重复下单。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/attribute/Idempotent.php` | 注解 `#[Idempotent(window, useParams, message)]` |
| `app/admin/middleware/Idempotent.php` | Redis SET NX EX 抢占幂等键，重复请求返回 409 |
| `config/middleware.php` | 注册于 admin 链 RateLimit 之后、OperationLog 之前 |
| `app/admin/controller/UserController.php` | 创建用户接口已标注示例 |

### 用法

```php
use app\common\attribute\Idempotent;

#[Post('/user')]
#[Idempotent(window: 5)]                 // 5 秒内相同请求只执行一次
public function store(Request $request): Response { ... }

#[Idempotent(window: 10, useParams: false)]  // 同接口任意参数都视为重复（更激进）
```

### 幂等键来源（优先级）

1. 请求头 `Idempotency-Key`（客户端显式提供，最精确，推荐前端为关键写操作生成 UUID）；
2. 否则自动生成：`用户ID : 控制器::方法 : 参数指纹(md5)`。

### 行为

- 抢到键 → 放行；窗口期内再来相同请求 → 抢不到 → 返回 `{"code":409,"msg":"请勿重复提交"}`。
- 键在窗口期内**不主动释放**（到期自动失效），确保快速连点被拦住。
- 置于 OperationLog 之前：被拦的重复请求不写操作日志。
- **fail-open**：Redis 故障时放行。

---

## 三、协程安全前置改造

### 做了什么

为后续切换协程驱动（见 `docs/COROUTINE.md`）扫清最大障碍：单例串号。

### AuthMiddleware 无状态化

**改造前**：用实例字段 `currentRequest / currentUser / isSuperAdmin` 缓存请求态，
靠"每次 process 入口重置"在多进程下保平安——但**协程并发共享同一实例会串号**。

**改造后**：
- 删除全部三个实例字段；
- 用户信息作为局部变量在方法间用参数传递；
- 超管判断、权限校验改为接收 `int $userId` 参数；
- 仅保留的 `static $cache` 存"控制器::方法 → 权限标识"这一**不可变只读映射**，协程安全。

### 协程安全红线（新增代码必须遵守）

1. 单例 Service / 中间件**禁止用实例字段存请求级可变数据**，用参数或 `support\Context`；
2. **禁止阻塞函数**（`sleep`、`curl_exec`、远程 `file_get_contents` 等）；
3. 静态变量只缓存不可变数据；
4. 请求态隔离用 `support\Context`（参考 `TraceContext`）。

---

## 四、中间件执行顺序（最终）

```
Trace → Cors → [admin] AuthMiddleware → RateLimit → Idempotent → OperationLog → Controller
```

---

## 五、自测要点

| 场景 | 预期 |
|---|---|
| 5 秒内连点两次"创建用户" | 第二次返回 409「请勿重复提交」 |
| 带不同 `Idempotency-Key` 的两次请求 | 均放行（视为不同操作） |
| 系统配置缓存失效瞬间并发读 | 仅一次查库（互斥锁），其余读到回填值 |
| 缓存查不存在的数据 | 空值被短缓存，30s 内不再查库 |
| Redis 宕机 | 限流/幂等/缓存锁均 fail-open，业务不中断 |
| 切到协程驱动后并发压测 | 用户身份不串号、连接池数稳定（见 COROUTINE.md） |

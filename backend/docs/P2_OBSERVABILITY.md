# P2 可观测性与并发控制说明（分布式锁 / 慢查询 / Metrics）

> 对应 `docs/HIGH_CONCURRENCY_ENTERPRISE.md` 的 P2。记录 2026-06-24 落地的实现与用法。

---

## 一、分布式锁（Lock）

### 做了什么

基于 Redis 的业务互斥锁，用 `SET NX PX` 原子抢锁、Lua 校验 token 释放（防误删）。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/support/Lock.php` | acquire / acquireBlocking / release / withLock |

### 用法

```php
use app\common\support\Lock;

// 推荐：自动获取与释放
$ran = Lock::withLock('cron:cleanup', 30, function () {
    // 临界区：多实例部署时只有一个执行
});
if ($ran === false) {
    // 未获得锁，已被其它实例占用
}

// 手动管理
$token = Lock::acquire('order:pay:' . $orderId, 10);
if ($token === null) {
    throw BusinessException::conflict('操作进行中，请稍后');
}
try {
    // ...
} finally {
    Lock::release('order:pay:' . $orderId, $token);
}

// 阻塞等待（最多等 3 秒）
$token = Lock::acquireBlocking('resource', 10, 3.0);
```

### 关键设计

- **fail-safe**：与 CacheGuard 的缓存锁相反，业务互斥锁在 Redis 故障时**抛异常**而非放行
  （静默放行会破坏互斥语义，比如重复扣库存）。`release` 不抛（锁会自动过期兜底）。
- **防误删**：每次 acquire 生成随机 token，release 用 Lua 校验 token 一致才删，
  避免 A 的锁过期后误删 B 的锁。
- **自动过期**：`PX` 毫秒级 TTL，持锁进程崩溃也不会死锁。

### 典型场景

- 定时任务防重入（多实例只跑一个）
- 库存扣减 / 余额变动等并发写临界区
- 任意"同一时刻只允许一个执行者"的业务

---

## 二、慢查询监控（SlowQueryLogger）

### 做了什么

每个 Worker 启动时注册 `DB::listen`，对执行耗时超阈值的 SQL 写慢查询日志（带 request_id）。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/bootstrap/SlowQueryLogger.php` | Webman\Bootstrap 实现，注册查询监听 |
| `config/bootstrap.php` | 注册引导类 |
| `.env.example` | `SLOW_QUERY_MS` 阈值 |

### 配置

```ini
SLOW_QUERY_MS=200   # 超过 200ms 记录；设 0 关闭
```

### 日志样例

写入 `runtime/logs/webman.log`（warning 级），含 request_id 可与链路串联：

```
[2026-06-24 10:00:00] default.WARNING: Slow query {"connection":"mysql","time_ms":356.21,
"threshold_ms":200,"sql":"select * from sys_user where ...","bindings":[1],"request_id":"ab12..."}
```

### 设计要点

- 非 worker 上下文（CLI/console）自动跳过，避免命令行报错。
- SQL 超 2000 字符截断；绑定参数做标量安全转换（对象/资源转类型名），防 json 失败。
- 注册失败仅告警，不阻断 Worker 启动。

---

## 三、Metrics 监控（/metrics）

### 做了什么

暴露 Prometheus 文本格式的 `/metrics` 端点，跨 Worker 聚合关键指标。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/support/MetricsCollector.php` | Redis 聚合计数 + Prometheus 渲染 |
| `app/middleware/Metrics.php` | 全局中间件，请求计时与上报 |
| `app/controller/MetricsController.php` | `/metrics` 端点 + 令牌鉴权 |
| `config/route.php` | 注册 `GET /metrics` |
| `config/middleware.php` | Metrics 注册于全局链（Trace 之后） |

### 为什么用 Redis 聚合

workerman 多进程，各 Worker 内存独立。指标写入 Redis Hash 累积，
单次 `/metrics` 抓取才能反映**全局**而非单个 Worker 的数据。

### 暴露的指标

| 指标 | 类型 | 说明 |
|---|---|---|
| `http_requests_total{method,status}` | counter | 按方法 + 状态码段（2xx/4xx/5xx）计数 |
| `http_request_duration_ms_bucket{le}` | histogram | 请求耗时直方图（5~5000ms 桶 + +Inf） |
| `http_request_duration_ms_sum / _count` | histogram | 耗时总和与总数（可算均值、P95 估算） |
| `worker_memory_bytes{worker}` | gauge | 各 Worker 常驻内存 |
| `queue_pending_jobs{queue}` | gauge | redis-queue 等待队列堆积（best-effort） |

### 鉴权与抓取

```ini
METRICS_TOKEN=your-secret-token   # 为空则不鉴权（仅限内网/开发）
```

Prometheus 抓取配置：

```yaml
scrape_configs:
  - job_name: webman-admin
    metrics_path: /metrics
    authorization:
      credentials: your-secret-token   # 对应 METRICS_TOKEN
    static_configs:
      - targets: ['127.0.0.1:8787']
```

手动查看：

```bash
curl -H "Authorization: Bearer your-secret-token" http://127.0.0.1:8787/metrics
```

### 设计要点

- **全程 fail-open**：Redis 故障时采集静默跳过、渲染输出注释行，绝不影响业务或抓取。
- `/metrics` 自身不计入指标，避免抓取动作污染数据。
- 直方图为累积桶（Prometheus histogram 规范），可用 `histogram_quantile()` 估算 P95/P99。

---

## 四、中间件执行顺序（最终）

```
Trace → Metrics → Cors → [admin] AuthMiddleware → RateLimit → Idempotent → OperationLog → Controller
```

---

## 五、自测要点

| 场景 | 预期 |
|---|---|
| 并发调 `Lock::withLock` 同名锁 | 仅一个执行回调，其余返回 false |
| Redis 宕机时 `Lock::acquire` | 抛 RuntimeException（fail-safe） |
| 执行一条 >200ms 的查询 | webman.log 出现 `Slow query` 且带 request_id |
| 请求若干接口后访问 /metrics | 输出 `http_requests_total` 等指标 |
| 未带 token 访问 /metrics（已设 METRICS_TOKEN） | 401 |
| Redis 宕机时访问 /metrics | 返回注释行，不报 500 |

---

## 六、后续可接（P2 剩余 / P3）

- **告警**：基于 Metrics 配 Prometheus AlertManager 规则（5xx 比例、P99、队列堆积）。
- **Grafana 看板**：导入 http 指标做 QPS/延迟/错误率面板。
- **测试与 CI**：PHPUnit + PHPStan + GitHub Actions（本轮按需跳过）。

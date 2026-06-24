# 高并发与企业级优化路线

> 本文档基于当前代码现状（webman 2.x + workerman + Eloquent + Redis + redis-queue）梳理，
> 区分**已具备能力**与**待补缺口**，并按优先级给出可落地的实施方案。
> 每条建议尽量贴合 webman 生态，避免引入不必要的重型依赖。

---

## 一、现状盘点（已具备的能力）

| 能力 | 现状 | 位置 |
|---|---|---|
| 常驻内存框架 | workerman 多进程，Worker = CPU×4 | `config/process.php` |
| 分层架构 | Controller / Service / Model / Validator 职责清晰 | `docs/ARCHITECTURE.md` |
| 鉴权 | JWT(HS256) + RBAC 注解鉴权 | `AuthMiddleware`、`JwtService` |
| 数据权限 | data_scope 1~5 设计（**未在查询层自动注入**） | `DataScopeService` |
| 连接池 | DB 连接池参数化（协程驱动下生效） | `config/database.php` |
| 异步化 | 操作日志走 redis-queue 异步落库，带重试/失败队列 | `app/queue/redis/OperationLogConsumer` |
| 缓存 | Redis 缓存 + 明确的 Key 命名与 TTL 约定 | `docs/ARCHITECTURE.md` |
| 统一响应/异常 | BusinessException 体系 + 统一响应格式 | `app/exception/Handler` |
| 容器化 | Dockerfile（opcache+JIT）+ docker-compose + HEALTHCHECK | `Dockerfile` |
| 优雅停止 | STOPSIGNAL SIGTERM | `Dockerfile` |

**结论**：基础骨架已是生产级。下面的缺口主要集中在
*流量防护、状态一致性、可观测性、质量保障、水平扩展* 五个方向。

---

## 二、缺口与优先级总览

| 优先级 | 主题 | 缺口 | 价值 |
|---|---|---|---|
| 🔴 P0 | 接口限流 | 无任何限流/防刷 | 抗突发、防爆破登录 |
| 🔴 P0 | JWT 失效控制 | 无登出黑名单 / 无刷新机制 | 安全合规、踢人下线 |
| 🔴 P0 | 链路追踪 | 无 trace_id / request_id 贯穿日志 | 排障定位 |
| 🟠 P1 | 协程驱动 | `event_loop` 空，连接池未真正生效 | 单进程并发能力 |
| 🟠 P1 | 防重复提交/幂等 | 无 | 防止重复下单类问题 |
| 🟠 P1 | 缓存防护 | 无击穿/穿透/雪崩防护 | 缓存层稳定性 |
| 🟠 P1 | 数据权限落地 | 仅有设计，未在查询层注入 | 越权风险 |
| 🟡 P2 | 可观测性 | 无 metrics / 慢查询监控 | 容量规划、SLA |
| 🟡 P2 | 测试与 CI | 无 tests / phpunit / CI | 质量回归 |
| 🟡 P2 | 分布式锁 | 无 | 定时任务/库存类并发控制 |
| 🟢 P3 | 读写分离 | 单库 | DB 横向扩展 |
| 🟢 P3 | API 文档自动化 | 手写 API.md | 协作效率 |
| 🟢 P3 | 配置中心 / 多环境 | .env 单文件 | 多环境治理 |

---

## 三、P0 必做项

> ✅ **本节三项已全部实现**（2026-06-24），实现细节见 `docs/SECURITY_HARDENING.md`。

### 3.1 接口限流（RateLimit 中间件）— ✅ 已实现

**问题**：登录、验证码、文件上传等接口无任何频率限制，易被爆破/刷量。

**方案**：新增全局可配的限流中间件，基于 Redis 计数器（固定窗口或令牌桶）。

```php
// app/middleware/RateLimit.php（示意）
// key 维度：ip + 路由；或登录接口用 ip + username
// 算法：INCR + EXPIRE 固定窗口，或 Redis Lua 实现令牌桶（更平滑）
// 超限返回 429 Too Many Requests
```

落地要点：
- 通过注解 `#[RateLimit(limit: 60, window: 60)]` 或路由分组配置，按接口差异化限流。
- 登录接口单独加**失败计数锁定**（连续失败 N 次锁定 M 分钟）。
- Lua 脚本保证 INCR+EXPIRE 原子性，避免竞态。
- 响应头返回 `X-RateLimit-Limit / Remaining / Retry-After`。

### 3.2 JWT 失效控制（登出黑名单 + 刷新机制）— ✅ 已实现（token_version 方案）

**问题**：当前 JWT 一旦签发，到期前无法主动失效。登出、改密、禁用账号后旧 Token 仍可用。

**方案**：
1. **登出黑名单**：登出时将 token 的 `jti`（或 token 哈希）写入 Redis，TTL = token 剩余有效期。`AuthMiddleware` 校验时先查黑名单。
2. **双 Token 机制**：短效 access_token（如 2h）+ 长效 refresh_token（如 7d）。提供 `/admin/refresh` 续期接口。
3. **强制下线**：在用户记录上维护 `token_version`，改密/禁用时自增；payload 携带版本号，不匹配即失效（无需逐 token 拉黑）。

> 推荐先做方案 3（token_version），实现成本低、覆盖"改密/禁用即失效"的核心诉求；再按需补黑名单与 refresh。

### 3.3 链路追踪（request_id 贯穿）— ✅ 已实现

**问题**：日志无法把"同一次请求"的多条记录串起来，分布式/多进程下排障困难。

**方案**：
- 新增 `TraceMiddleware`（全局最前），为每个请求生成/透传 `X-Request-Id`，注入 `Request` 与日志上下文。
- monolog 增加 Processor，把 `request_id` 写进每条日志。
- 响应头回写 `X-Request-Id`，前端报错可上报该 id。
- 操作日志、异常日志均带上该 id，实现"一个 id 串全链路"。

---

## 四、P1 重要项

> 进度（2026-06-24）：4.2 / 4.3 ✅ 已实现；4.4 ✅ 模板已具备；
> 4.1 协程**前置改造已完成**（AuthMiddleware 无状态化），实际切换驱动需 swoole 环境，
> 详见 `docs/COROUTINE.md` 与 `docs/P1_CONCURRENCY.md`。

### 4.1 启用协程驱动，让连接池真正生效 — ⏳ 前置就绪，待切换

**现状**：`config/server.php` 中 `event_loop` 为空（默认 select/event），`config/database.php` 的连接池注释已写明"仅在协程驱动下生效"——即当前连接池配置实际未启用。

**已完成（前置改造）**：
- ✅ `AuthMiddleware` 已移除请求级可变实例字段，改为无状态（协程并发不再串号）。
- ✅ `TraceContext` 基于 `support\Context` 实现请求态隔离。
- ✅ `config/server.php`、`.env.example` 已就绪 `WM_EVENT_LOOP` 与连接池参数。

**待执行（运维）**：
- 安装 `ext-swoole`（≥5.x）或 Swow，设 `WM_EVENT_LOOP=Swoole`。
- 压测验证连接池复用与无串号，详见 `docs/COROUTINE.md`。

### 4.2 防重复提交 / 幂等 — ✅ 已实现

**方案**：
- `#[Idempotent]` 注解 + Redis SETNX：客户端携带唯一 `Idempotency-Key`，或服务端按 `uid+路由+参数指纹` 生成锁，窗口期内重复请求直接拒绝/返回首次结果。
- 适用于创建类写操作（建用户、建角色、提交表单）。

### 4.3 缓存三大问题防护 — ✅ 已实现

| 问题 | 防护 |
|---|---|
| 穿透（查不存在的数据） | 空值缓存（短 TTL）+ 参数合法性校验，可选布隆过滤器 |
| 击穿（热点 key 失效瞬间打满 DB） | 互斥锁重建（SETNX 抢锁，单线程回源）或逻辑过期 |
| 雪崩（大量 key 同时失效） | TTL 加随机抖动；多级缓存（进程内 + Redis） |

已封装统一的 `cache_remember($key, $ttl, $callback)` 帮助方法（底层 `CacheGuard`），内置互斥重建与抖动。

### 4.4 数据权限在查询层落地 — ✅ 模板已具备

**现状**：`data_scope` 仅有设计与枚举，查询层未自动注入，存在越权读取风险。

**方案**：
- 在 `BaseService` 列表查询中提供 `applyDataScope($query)`，根据当前用户 data_scope 自动注入部门过滤。
- 或写成 Eloquent Global Scope / Query Macro，按需开启。
- 配合单测覆盖 5 种 scope 的边界。

---

## 五、P2 质量与可观测

> 进度（2026-06-24）：5.1 可观测性 ✅ 已实现；5.3 分布式锁 ✅ 已实现；
> 5.2 测试与 CI 本轮按需跳过。实现细节见 `docs/P2_OBSERVABILITY.md`。

### 5.1 可观测性（Metrics + 慢查询）— ✅ 已实现

- **Metrics**：暴露 `/metrics`（Prometheus 格式）——QPS、延迟直方图（P95/P99 可估算）、各 Worker 内存、队列堆积。跨 Worker 经 Redis 聚合，令牌鉴权（`METRICS_TOKEN`）。
- **慢查询**：`DB::listen()` 监听，超过阈值（`SLOW_QUERY_MS`，默认 200ms）写慢查询日志，带 request_id。
- **队列监控**：`/metrics` 输出 redis-queue 等待队列堆积（`queue_pending_jobs`）。

### 5.2 测试与 CI/CD — ⏳ 待办

**现状**：无 `tests/`、无 `phpunit.xml`、无 `.github/workflows`。

**方案**：
- 引入 PHPUnit，建 `tests/Unit`（Service 业务规则）+ `tests/Feature`（接口级，含鉴权/限流）。
- 静态分析：PHPStan（level 6+）+ PHP-CS-Fixer（配合已有 `docs/CODE_STYLE.md`）。
- GitHub Actions：push/PR 触发 `composer install → phpstan → cs-check → phpunit`。

### 5.3 分布式锁 — ✅ 已实现

- 已封装基于 Redis 的 `Lock::acquire($name, $ttl)` / `Lock::withLock()`（SET NX PX + 唯一 token + Lua 释放，防误删，fail-safe）。
- 用于：定时任务防重入（多实例只跑一个）、库存/计数类并发写。

---

## 六、P3 扩展性

> 进度（2026-06-25）：读写分离 ✅、API 文档自动化 ✅、雪花 ID ✅ 已实现。

- **读写分离** ✅：`config/database.php` 按 `DB_READ_HOST` 开关 read/write + sticky，Eloquent 自动路由读到从库。详见 `docs/READ_WRITE_SPLIT.md`。
- **API 文档自动化** ✅：注解 + `zircote/swagger-php` 生成 OpenAPI，`php webman openapi:gen` → `/swagger`。详见 `docs/OPENAPI.md`。
- **雪花 ID** ✅：`Snowflake::next()` 生成 64bit 全局唯一趋势递增 ID，时钟回拨保护。详见 `docs/P3_SCALABILITY.md`。
- **配置中心 / 多环境** ⏳：区分 `.env.dev/.env.prod`，敏感配置（DB 密码、JWT secret）接入 Nacos/Apollo 或 KMS；密钥不入库不入仓。
- **网关层** ⏳：生产用 Nginx 反代 + 静态资源分离，TLS 终止、gzip、连接复用；大文件上传走对象存储（OSS/S3）而非本地磁盘。

### 5.2 测试与 CI — ✅ 已实现

- PHPUnit 脚手架（`phpunit.xml` + `tests/` + `autoload-dev`），核心纯逻辑单测（Snowflake/异常/helper/缓存防护）。
- PHPStan（`phpstan.neon`，level 5）+ GitHub Actions（`.github/workflows/backend-ci.yml`）。
- 详见 `docs/TESTING.md`。

---

## 七、安全合规补强（横切）

- **敏感数据加密**：手机号/身份证等落库加密（AES-GCM），日志脱敏（密码、token、身份证打码）。
- **操作审计完整性**：操作日志已异步落库，建议补充"登录日志"（成功/失败、IP、UA）与高危操作二次确认。
- **请求体大小限制**：防大包攻击（webman/Nginx 双层限制）。
- **越权校验**：除菜单权限外，写操作需校验"数据归属"（用户只能改自己部门数据），与 §4.4 数据权限联动。
- **密码策略**：强度校验、加盐哈希（确认用 `password_hash`）、登录失败锁定（与 §3.1 联动）。

---

## 八、建议实施顺序（路线图）

```
阶段一（安全与排障，1~2 周）
  └─ P0：限流中间件 → JWT token_version 失效 → trace_id 链路

阶段二（并发与一致性，2~3 周）
  └─ 协程化改造（含 AuthMiddleware 上下文化）→ 缓存防护封装 → 数据权限落地 → 幂等/分布式锁

阶段三（质量与可观测，持续）
  └─ PHPUnit + PHPStan + CI → Metrics/慢查询 → 队列与告警

阶段四（扩展，按需）
  └─ 读写分离 → OpenAPI 自动化 → 对象存储 → 配置中心
```

> **关键提示**：协程化（§4.1）收益最大但风险也最高，务必先完成 `AuthMiddleware`
> 等单例的请求态上下文化改造，并补好接口测试，再切换 `event_loop`。

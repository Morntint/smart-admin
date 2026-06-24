# P0 安全加固说明（限流 / JWT 失效 / 链路追踪）

> 本文档对应 `docs/HIGH_CONCURRENCY_ENTERPRISE.md` 中的 P0 三项，
> 记录 2026-06-24 落地的实现、配置、使用方式与注意事项。

---

## 一、链路追踪（request_id）

### 做了什么

为每个请求生成唯一 `request_id`，贯穿日志、异常响应与跨服务调用，实现"一个 id 串全链路"。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/support/TraceContext.php` | 基于 `support\Context` 的上下文存取（进程/协程双安全） |
| `app/middleware/Trace.php` | 全局中间件：生成/透传 id，写上下文，回写响应头 |
| `app/common/log/TraceProcessor.php` | Monolog Processor：把 request_id 注入每条日志 extra |
| `config/log.php` | 注册 `processors` |
| `config/middleware.php` | Trace 注册在 `''` 全局分组最前 |
| `app/exception/Handler.php` | 5xx 响应体携带 `request_id` |

### 行为

- 请求头带合法 `X-Request-Id`（≤64 字符、`[A-Za-z0-9._-]`）则透传复用，否则生成 32 位 hex。
- 所有响应（含 404 / 异常）回写 `X-Request-Id` 头。
- 每条日志行尾自动带 `{"request_id":"..."}`，按 id 即可聚合一次请求的全部日志。
- 服务端错误（5xx）的 JSON body 含 `request_id`，便于用户截图上报、运维检索。

### 排障姿势

```bash
# 用户上报 request_id 后，一条命令捞出该请求的所有日志
grep '"request_id":"<id>"' runtime/logs/webman-*.log
```

---

## 二、接口限流（RateLimit）

### 做了什么

注解式接口限流 + 登录账号级失败锁定，抵御突发流量与密码爆破。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/attribute/RateLimit.php` | 限流注解 `#[RateLimit(limit, window, by, key)]` |
| `app/common/support/RateLimiter.php` | Redis + Lua 原子固定窗口计数器 |
| `app/admin/middleware/RateLimit.php` | 读注解、计数、超限抛 429、回写限流头 |
| `app/common/exception/TooManyRequestsException.php` | 429 异常（带 retryAfter） |
| `config/middleware.php` | 注册于 admin 链 AuthMiddleware 之后 |
| `app/admin/controller/LoginController.php` | login / captcha 标注限流 |
| `app/admin/service/LoginService.php` | 账号级失败锁定 |

### 用法

```php
use app\common\attribute\RateLimit;

// 按 IP：每 60 秒最多 60 次（默认维度）
#[RateLimit(limit: 60, window: 60)]

// 按登录用户：每个用户每分钟 5 次（未登录回退到 IP）
#[RateLimit(limit: 5, window: 60, by: 'user')]

// 自定义业务名共享计数 + 全局维度
#[RateLimit(limit: 100, window: 1, by: 'global', key: 'export')]
```

`by` 维度：`ip`（默认）/ `user`（取 `admin_user_id`，未登录回退 IP）/ `global`。

### 响应

- 受限接口正常响应携带：`X-RateLimit-Limit`、`X-RateLimit-Remaining`、`X-RateLimit-Reset`。
- 超限返回业务码 429 + `Retry-After` 头：
  ```json
  { "code": 429, "msg": "请求过于频繁，请稍后再试" }
  ```

### 关键设计

- **原子性**：`INCR` + 首次 `EXPIRE` 在一段 Lua 里完成，杜绝"计数器永不过期"竞态。
- **fail-open**：Redis 故障时放行，限流组件自身不拖垮业务。
- **零开销**：未标注 `#[RateLimit]` 的接口直接放行；注解反射结果做进程内 static 缓存。

### 登录失败锁定（账号维度）

- 同一用户名连续失败 **5 次**，锁定 **15 分钟**（`LoginService::MAX_LOGIN_FAILURES / LOGIN_LOCK_WINDOW`）。
- 登录成功清除计数；锁定期内直接拒绝且不校验密码。
- 与 LoginController 上 IP 维度限流（每分钟 10 次）形成**双层防爆破**。

---

## 三、JWT 主动失效（token_version）

### 做了什么

为 JWT 引入版本号，实现"改密即下线""禁用即踢出""登出即失效"，无需维护逐 token 黑名单。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `database/schema.sql` | `sys_user` 新增 `token_version` 列 |
| `database/update_token_version_2026.sql` | 存量库增量升级脚本 |
| `app/model/SysUser.php` | `fillable` 增列 + `bumpTokenVersion()` 原子自增 |
| `app/admin/service/LoginService.php` | 签发 token 写入 `tv`；登出自增版本 |
| `app/admin/controller/LoginController.php` | refresh 续期携带当前 `tv` |
| `app/admin/middleware/AuthMiddleware.php` | 校验 token `tv` 与库内 `token_version` 一致性 |
| `app/admin/service/UserService.php` | 改密/重置/禁用时自增版本 |

### 原理

1. 签发 token 时写入 `tv = 用户当前 token_version`。
2. `AuthMiddleware` 解析 token 后，比对 `payload.tv` 与用户 `token_version`，不一致即抛 401「登录状态已失效」。
3. 任意"应使旧 token 失效"的操作调用 `bumpTokenVersion()`（`token_version += 1`），此前所有 token 立即作废。

### 触发自增（失效）的时机

| 操作 | 位置 | 说明 |
|---|---|---|
| 退出登录 | `LoginService::logout` | 当前会话立即失效 |
| 修改密码（本人） | `UserService::changePassword` | 改密后强制重新登录 |
| 重置密码（管理员） | `UserService::resetPassword` | 旧 token 全失效 |
| 禁用账号 | `UserService::toggleStatus` | 与状态校验双保险，规避缓存窗口期 |
| 删除账号 | 软删除 | 中间件 `find()` 查不到 → 自然失效，无需自增 |

### 升级注意

- **新库**：`schema.sql` 已含该列，无需额外操作。
- **存量库**：执行 `database/update_token_version_2026.sql`。
  - 默认 `token_version = 0`，已签发旧 token（不带 `tv`，按 0 处理）**仍有效**，升级不会强制踢出在线用户。
  - 如需全员重新登录，执行脚本内可选语句：`UPDATE sys_user SET token_version = token_version + 1;`
- **缓存联动**：用户信息缓存 `auth_user_{id}` 含 `token_version`，各失效点均已调用 `clear_permission_cache()`（内部清该键），保证版本变更即时生效。

---

## 四、中间件执行顺序（更新后）

```
请求方向：
  Trace → Cors → [admin 分组] AuthMiddleware → RateLimit → OperationLog → Controller
```

- **Trace** 最前：保证 404/异常响应也带 request_id。
- **RateLimit** 在 Auth 之后：`by='user'` 才能拿到 `admin_user_id`。
- 登录/验证码虽在 Auth 白名单内，但仍经过 RateLimit（admin 分组对全部 admin 控制器生效）。

---

## 五、自测要点

| 场景 | 预期 |
|---|---|
| 任意请求 | 响应头含 `X-Request-Id`；日志行含同一 id |
| 1 分钟内第 11 次登录（同 IP） | 返回 429 + `Retry-After` |
| 同账号连续 5 次错密码后再登录 | 返回「账号已锁定」429，且不校验密码 |
| 登录成功后再错密码 | 失败计数已清零，从 1 重新计 |
| 登出后用旧 token 访问 | 401「登录状态已失效」 |
| 改密/重置密码后用旧 token | 401「登录状态已失效」 |
| 禁用用户后其旧 token 访问 | 401（状态校验或版本校验任一拦截） |

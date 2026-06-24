# 协程化切换指南（Swoole / Swow）

> 本文档说明如何把本项目从默认的「同步多进程」模型切换到「协程」模型，
> 以及切换前必须完成的改造与风险点。
>
> ⚠️ **协程化收益最大（单进程并发、连接池生效），但风险也最高。**
> 切换是运维 + 研发的联合决策，务必先在压测环境验证，再上生产。

---

## 一、为什么要协程化

| 维度 | 同步多进程（默认） | 协程（Swoole/Swow） |
|---|---|---|
| 并发模型 | Worker 数 = CPU×4，每进程一次处理一个请求 | 单进程内多协程并发，IO 等待时切换 |
| 连接池 | **不生效**（每进程独占连接） | **生效**（`database.php`/`redis.php` 的 pool 配置） |
| 适用场景 | 中低并发、CPU 密集 | 高并发、IO 密集（大量 DB/Redis/HTTP 调用） |
| 风险 | 低 | 单例/全局态串号、阻塞函数卡住整个进程 |

---

## 二、切换前置改造（**必做**）

协程模型下，**同一个单例对象会被多个并发协程共享**。若单例持有"随请求变化的可变字段"，
协程切换时会发生**串号**（A 请求读到 B 请求的用户身份）——这是最危险的 bug。

### 2.1 已完成的改造

- ✅ **AuthMiddleware**：已于 P1 移除 `currentRequest/currentUser/isSuperAdmin` 实例字段，
  改为局部变量 + 方法参数传递，无任何请求级可变状态。
- ✅ **TraceContext**：基于 `support\Context`（协程上下文），天然隔离。
- ✅ **JwtService**：实例字段 `secret/expire/issuer` 为构造时一次性写入的只读配置，协程安全。
- ✅ 其它 Service 单例（BaseService/PermissionService/DataScopeService/LoginService/MenuService）
  均无请求级可变实例字段，仅用局部变量与缓存。

### 2.2 新增代码必须遵守的红线

1. **单例 Service / 中间件中，禁止用实例字段保存"当前请求 / 当前用户"等随请求变化的数据。**
   需要跨方法传递时，用方法参数；需要跨中间件传递时，用 `support\Context` 或 `$request` 属性。
2. **禁止使用阻塞函数**：`sleep()`、`curl`、原生 `mysqli`、`file_get_contents` 远程 URL 等
   会阻塞整个进程。改用协程化客户端（webman 的 `support\Redis`、`webman/database` 在协程下自动协程化）。
3. **全局态隔离**：静态变量缓存（如反射结果）只能存"不可变数据"；
   存请求相关数据必须用 `Context`。
4. **`Math.random()` 类不确定性无影响**，但注意 `Context::get()` 必须在协程内调用。

---

## 三、切换步骤

### 3.1 安装扩展

```bash
# Swoole（推荐，生态成熟）
pecl install swoole          # 需 >= 5.0
# 或 Swow
pecl install swow

php -m | grep -i 'swoole\|swow'   # 确认已加载
```

### 3.2 配置

`.env`：

```ini
WM_EVENT_LOOP=Swoole        # 或 Swow
# 连接池（按实例规格与并发量调整）
DB_POOL_MAX=20
DB_POOL_MIN=5
REDIS_POOL_MAX=20
REDIS_POOL_MIN=5
# 协程模型下 Worker 数可大幅下调（单进程已能高并发）
WORKER_COUNT=4
```

> `config/server.php` 的 `event_loop` 已读取 `WM_EVENT_LOOP`，无需改代码。

### 3.3 重启并验证

```bash
php start.php restart -d
php start.php status          # 查看进程与连接数
```

---

## 四、连接池生效验证

切换后，连接池才真正生效。验证方法：

1. **压测对比**：用 `wrk` / `ab` 对一个查 DB 的接口压测，观察 QPS 与 P99。
   ```bash
   wrk -t4 -c100 -d30s -H "Authorization: Bearer <token>" http://127.0.0.1:8787/admin/user?page=1
   ```
2. **连接数监控**：压测时观察 MySQL `SHOW STATUS LIKE 'Threads_connected'`，
   应稳定在 `DB_POOL_MAX × Worker 数` 量级，而非随并发线性飙升。
3. **日志检查**：`runtime/logs/` 无"too many connections""connection pool timeout"等错误。

---

## 五、上线风险清单（Checklist）

- [ ] `php -m` 确认 swoole/swow 已加载且版本达标
- [ ] 全量回归接口测试（重点：登录态、权限、数据权限过滤是否串号）
- [ ] 并发压测下随机抽查：A 用户的 token 不会拿到 B 用户的数据
- [ ] 检查是否引入了阻塞函数（`grep -rn 'sleep\|file_get_contents\|curl_exec' app/`）
- [ ] 连接池上限 × Worker 数 ≤ MySQL/Redis 的 max_connections
- [ ] 优雅停止正常（`STOPSIGNAL SIGTERM`，`stop_timeout` 内排空在途请求）
- [ ] 监控接入：QPS、P99、连接池使用率、协程数

---

## 六、回滚

协程模型若出现问题，回滚极简单——清空环境变量即可恢复同步多进程模型：

```ini
WM_EVENT_LOOP=
```

```bash
php start.php restart -d
```

> 因为业务代码已按协程安全规范编写（无状态单例 + Context），
> 两种模型可无缝切换，回滚不需要改任何代码。

---

## 七、相关文件

| 文件 | 作用 |
|---|---|
| `config/server.php` | `event_loop` 读取 `WM_EVENT_LOOP` |
| `config/database.php` | DB 连接池（协程下生效） |
| `config/redis.php` | Redis 连接池（协程下生效） |
| `app/admin/middleware/AuthMiddleware.php` | 已改造为无状态（协程安全范例） |
| `app/common/support/TraceContext.php` | 基于 Context 的请求态隔离范例 |

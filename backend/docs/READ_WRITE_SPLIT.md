# 读写分离指南

> 把 SELECT 路由到从库、写操作路由到主库，分摊数据库读压力，实现 DB 横向扩展。
> 本项目基于 Eloquent 原生的 read/write 连接能力，零代码改造，仅需配置。

---

## 一、启用方式

在 `.env` 设置从库地址即自动启用（留空则单库模式，配置结构完全兼容）：

```ini
DB_HOST=10.0.0.1          # 主库（写）
DB_READ_HOST=10.0.0.2,10.0.0.3   # 从库（读），多个逗号分隔，自动负载均衡
# DB_WRITE_HOST=10.0.0.1  # 可选，缺省回退 DB_HOST
DB_STICKY=true            # 推荐开启（见下）
```

重启服务生效：

```bash
php start.php restart -d
```

---

## 二、路由规则（Eloquent 自动处理）

| 操作 | 路由到 |
|---|---|
| `SELECT`（`Model::find` / `where()->get()` / `count` 等） | 从库（多个时随机选一个） |
| `INSERT` / `UPDATE` / `DELETE` | 主库 |
| 事务内的所有查询 | 主库（保证一致性） |
| `sticky=true` 时，同一请求内写库之后的读 | 主库 |

业务代码**无需任何改动**——`UserService`、`BaseService` 里的查询会自动分流。

---

## 三、为什么要开 sticky

主从复制有延迟（通常毫秒级，高负载下可能更久）。考虑这个场景：

```php
$user = SysUser::create([...]);   // 写主库
$list = SysUser::all();           // 读从库 —— 可能还没同步到刚插入的数据！
```

`sticky=true` 后：一旦当前请求执行过写操作，**后续读自动走主库**，避免「写完立刻读却读到旧数据」。请求结束后状态重置，不影响下个请求。

> 代价：写后读不再分摊到从库。但保证了一致性，绝大多数后台场景应开启。

---

## 四、强制指定连接（特殊场景）

```php
use support\Db;

// 强制走主库读（如对一致性要求极高的校验）
$row = SysUser::onWriteConnection()->find($id);

// 显式从库
$rows = Db::connection('mysql')->table('sys_user')->get();
```

---

## 五、注意事项

- **从库只读**：确保从库账号无写权限，或 MySQL 从库设 `read_only=1`，防止误写。
- **DDL / 迁移走主库**：`database/*.sql` 导入、表结构变更只在主库执行，靠复制同步到从库。
- **连接池**：协程驱动下 read/write 各自维护连接池（`DB_POOL_*` 对两者生效）。
- **健康检查**：从库宕机时该连接的读会失败，建议配合中间件（如 ProxySQL / MySQL Router）做从库故障转移，应用层只连一个读入口。
- **监控**：关注主从延迟（`SHOW SLAVE STATUS` 的 `Seconds_Behind_Master`），延迟过大时考虑临时关 sticky 之外的从库或加从库。

---

## 六、回滚

清空 `DB_READ_HOST` 并重启即回到单库模式，无需改代码：

```ini
DB_READ_HOST=
```

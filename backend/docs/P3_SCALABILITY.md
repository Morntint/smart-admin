# P3 扩展性说明（雪花 ID）

> 读写分离见 `docs/READ_WRITE_SPLIT.md`，API 文档见 `docs/OPENAPI.md`，本文记录雪花 ID。

---

## 雪花 ID（Snowflake）

### 做了什么

生成 64bit 全局唯一、趋势递增的整数 ID，适合分布式主键 / 分库分表，
替代数据库自增（避免暴露数据量、避免分库主键冲突）。

### 涉及文件

| 文件 | 作用 |
|---|---|
| `app/common/support/Snowflake.php` | 生成器：`Snowflake::next()` |
| `.env.example` | `SNOWFLAKE_WORKER_ID` / `SNOWFLAKE_EPOCH` |

### 位结构

```
| 1 bit 符号位(0) | 41 bit 毫秒时间戳 | 10 bit 机器ID | 12 bit 序列号 |
```

- 41 bit 时间戳：相对纪元（默认 2024-01-01）的毫秒，约可用 69 年
- 10 bit 机器 ID：0~1023，多实例每个分配唯一值
- 12 bit 序列号：同毫秒内自增，单机每毫秒最多 4096 个，溢出自旋到下一毫秒

### 用法

```php
use app\common\support\Snowflake;

$id = Snowflake::next();   // 如 328205755029131264
```

在 Model 创建时用作主键：

```php
SysXxx::create([
    'id'   => Snowflake::next(),
    // ...
]);
```

### 配置

```ini
# 多实例部署时每个实例必须唯一（0~1023）；留空则按 主机名+PID 哈希兜底
SNOWFLAKE_WORKER_ID=1
# 起始纪元毫秒（可选）
SNOWFLAKE_EPOCH=
```

### 关键设计

- **时钟回拨保护**：当前时间早于上次生成时间时——小幅（≤5ms）等待追平，
  大幅直接抛 `RuntimeException`，宁可报错也不生成重复 ID。
- **序列溢出**：同一毫秒生成超过 4096 个时自旋等待下一毫秒。
- **机器 ID 兜底**：未配置 `SNOWFLAKE_WORKER_ID` 时用 `crc32(主机名+PID)` 落到 0~1023，
  降低同机多进程冲突概率——但**生产务必显式配置**，确保全局唯一。

### 注意：多 Worker 场景

workerman 多 Worker 是多进程，每进程独立 Snowflake 实例。若同机多 Worker 都用同一
`SNOWFLAKE_WORKER_ID`，理论上同毫秒可能撞号。两种处理：
1. 依赖 PID 兜底（不显式设 WORKER_ID），各 Worker 自动分散；
2. 或把 10 bit 机器位规划为「5 bit 数据中心 + 5 bit 机器」，由运维统一分配。

对绝大多数后台系统，单机 QPS 远达不到「同毫秒同机器位 4096 个」的撞号边界，
按方案 1 即可。

### 已验证

- 5000 个 ID 连续生成全部唯一、正数、趋势递增（见 `tests/Unit/SnowflakeTest.php`）。

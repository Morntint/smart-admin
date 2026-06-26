# 文档索引

## 📚 架构与规范

| 文档 | 说明 | 状态 |
|---|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | 架构设计、分层职责、异常体系、缓存键命名 | ✅ 已实现 |
| [API.md](API.md) | API 约定、路由风格、响应格式、鉴权流程 | ✅ 已实现 |
| [CODE_STYLE.md](CODE_STYLE.md) | 代码风格、命名规范、最佳实践 | ✅ 已实现 |
| [RESPONSE_CODES.md](RESPONSE_CODES.md) | 业务码与异常对照 | ✅ 已实现 |
| [TESTING.md](TESTING.md) | 测试与 CI、PHPUnit 使用指南 | ✅ 已实现 |

---

## 🚀 部署与运维

| 文档 | 说明 | 状态 |
|---|---|---|
| [DEPLOYMENT.md](DEPLOYMENT.md) | 部署指南（Docker / 环境要求） | ✅ 已实现 |
| [COROUTINE.md](COROUTINE.md) | 协程化切换指南（Swoole / Swow） | 📋 规划文档 |
| [READ_WRITE_SPLIT.md](READ_WRITE_SPLIT.md) | 读写分离实施方案 | 📋 规划文档 |

---

## 🤖 AI 智能模块

| 文档 | 说明 | 状态 |
|---|---|---|
| [AI_TOOL_USAGE.md](AI_TOOL_USAGE.md) | AI 工具调用功能说明（基础） | ✅ 已实现 |
| [AI_TOOL_SELECTION_REFACTOR.md](AI_TOOL_SELECTION_REFACTOR.md) | 发送时动态选择工具实现 | ✅ 已实现 |
| [AI_TOOL_DATE_PARSING.md](AI_TOOL_DATE_PARSING.md) | 相对日期解析（解决 AI 无实时时间问题） | ✅ 已实现 |

---

## 🔒 高并发与企业级能力

| 文档 | 说明 | 状态 |
|---|---|---|
| [HIGH_CONCURRENCY_ENTERPRISE.md](HIGH_CONCURRENCY_ENTERPRISE.md) | 高并发优化路线总览（P0/P1/P2/P3） | 📚 路线图 |

**已在代码中实现的 P0-P1 能力：**
- ✅ 链路追踪（`TraceContext` / `Trace` 中间件）
- ✅ 限流（`RateLimit` 注解）
- ✅ 幂等（`Idempotent` 注解）
- ✅ 缓存防护（`cache_remember` / `CacheGuard`）
- ✅ 分布式锁（`Lock` 类）
- ✅ 雪花 ID（`Snowflake`）

---

## 📖 规划文档

这些是未来可扩展方向的技术方案：

- [OPENAPI.md](OPENAPI.md) - OpenAPI/Swagger 自动生成 API 文档

---

## 🔧 数据库脚本

位于 `database/` 目录：
- `schema.sql` - 完整表结构
- `alter_*.sql` - 增量变更脚本
- `seed_*.sql` - 种子数据

---

## 💡 阅读建议

新手上路推荐顺序：
1. 先看 [ARCHITECTURE.md](ARCHITECTURE.md) 了解分层与职责
2. 再看 [API.md](API.md) 与 [RESPONSE_CODES.md](RESPONSE_CODES.md) 了解接口约定
3. 看 [CODE_STYLE.md](CODE_STYLE.md) 统一编码风格
4. 看 [TESTING.md](TESTING.md) 了解测试体系

开发 AI 功能请参考 AI 工具相关文档。

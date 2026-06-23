# Changelog

本文件记录项目重要变更。版本号遵循 [SemVer](https://semver.org/lang/zh-CN/)。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)。

---

## [Unreleased] - 2026-06-17

### 重构（企业级标准化）
  - `ResponseCode` 枚举：补全 `isClientError()` / `isServerError()` 辅助
  - `BusinessException`：支持 `int|ResponseCode` 双类型构造，新增 `::conflict`/`::notFound`/`::badRequest`/`::forbidden`/`::unauthorized` 静态工厂方法
  - `ValidationException`：携带字段级错误，前端可逐字段提示
  - 全局 `Handler::render` 重写：4xx 走 HTTP 200 + body.code 区分，5xx 走 HTTP 500，调试模式返回完整堆栈

- **基础类**
  - `BaseService`：新增 `applyKeyword`（多字段 OR 模糊搜索）、`assertUnique`（唯一性断言）、`now()`、统一类型注解；移除散落的 `now()` 私有方法
  - `BaseController`：新增 `pageResponse(array)` 简化分页返回；移除冗余的 `clearPermissionCache` 私有逻辑（统一走全局 `clear_permission_cache()`）
  - `BaseValidator`：补全 `dateRangeRules()`，PHPDoc 与字段类型完善
  - `BaseModel`：补 `scopeOnlyEnabled()` Scope，PHPDoc 类型化

- **Service 层**
  - 全部继承统一规范：使用 `assertUnique` 替代手写 `if exists then throw`；`BusinessException::xxx` 替代直接传业务码；`applyKeyword` 替代多字段拼装代码
  - `MenuService` / `DeptService`：抽取 `buildMenuPayload` / `buildDeptPayload`，避免创建/更新两次重复字段拼装
  - `RoleService::update`：菜单变更时同步清理关联用户的权限缓存
  - `LoginService`：使用 `BusinessException::badRequest` 等清晰命名
  - `JwtService`：补 `JWT_ISSUER` 配置项，密钥长度校验更明确

- **Controller 层**
  - 路由 Action 类型签名统一：`Request → Response`
  - 分页接口统一使用 `$this->pageResponse(...)`
  - `LogController` / `FileController` 批量删除接口返回受影响行数
  - 移除控制器内部直接访问 Model 的旧风格

- **Model 层**
  - 关联关系返回类型化（`BelongsTo` / `HasMany` / `BelongsToMany`）
  - 状态/类型映射统一标注 `array<int,string>` 类型
  - `SysUser`：新增 `isSuperAdmin()` 方法
  - `SysFile`：图标映射抽常量 `ICON_MAP`
  - `SysRoleMenu` / `SysUserRole`：新增 `$fillable`，批量插入前 `array_unique + intval` 防御

- **中间件**
  - `AuthMiddleware`：实现 `MiddlewareInterface`，PHPDoc 完整化，路径白名单 `/` 兼容
  - `OperationLog`：敏感字段过滤增加 `confirm_password / token / access_token / refresh_token / captcha / captcha_key`

### 辅助函数

- `app/functions.php`：补 `safe_like` / `safe_like_pattern` / `now_datetime` / `clear_permission_cache`
- `app/helper.php`：仅保留与业务无关的 `cache()` / `sysConfig()`

### 配置 / 部署

- `JwtService` 显式实现 `JwtServiceInterface`（已补 PHPDoc）
- 全部 11 个 `config/*.php` 补全中文头部注释、类型化、env 化关键参数
- `config/static.php` 启用 `StaticFile` 中间件（默认）
- `config/exception.php`、`config/middleware.php` 注释清晰化
- `app/process/Http.php`、`app/controller/IndexController.php` 重写：返回 JSON 心跳 `/ping` 与服务信息，不再引用 workerman iframe
- `Dockerfile` 升级：补 `pcntl / opcache / JIT`，时区 / 内存限制、健康检查
- `docker-compose.yml` 补全 MySQL / Redis 容器、健康检查、卷挂载
- `.env.example` 补 `CACHE_*`、`DB_PREFIX`、`POOL_*`、`LOG_MAX_FILES` 等全部变量
- `.gitignore` 完善（IDE / 上传目录 / 本地 env 兜底）
- 移除 Service 中冗余 `use ResponseCode` / `use Throwable` 等无效导入

### 文档

- 新增 `docs/ARCHITECTURE.md`、`docs/CODE_STYLE.md`、`docs/RESPONSE_CODES.md`、`docs/API.md`、`docs/DEPLOYMENT.md`
- 重写 `README.md`，增加目录结构、快速开始、架构约定、安全说明、文档索引
- 完善 `.env.example`：补 `JWT_ISSUER`，注释更清晰
- 新增 `CONTRIBUTING.md`（提交规范）

### 安全

- 上传目录创建失败显式抛业务异常（避免静默失败）
- LIKE 查询统一走 `safe_like_pattern()`，防 `%` `_` 注入
- 错误响应自动脱敏绝对路径与邮箱
- `OperationLog` 敏感字段过滤扩充至 9 个


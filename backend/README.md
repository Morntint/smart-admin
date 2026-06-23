# Webman Admin —— 企业级后台管理脚手架

基于 [Webman](https://www.workerman.net/webman) 2.x 的高性能后台管理框架，
预置一套企业级权限/部门/字典/日志/文件 RBAC 模板，开箱即用，方便二次开发。

---

## ✨ 特性

- **常驻内存 + 协程级性能**：基于 workerman/webman 2.x，单机轻松扛 10k+ QPS
- **现代 PHP 8.1+**：Enum、Readonly、Constructor Promotion、Match、严格类型
- **清晰分层**：Controller → Service → Model → DB，职责单一，易于维护
- **统一响应格式**：`{ code, msg, data }`，约定式异常 → JSON 输出
- **完整 RBAC**：用户 / 角色 / 菜单 / 部门 / 数据范围 / 按钮权限
- **审计日志**：操作日志（中间件自动写入）+ 登录日志
- **企业级实践**：JWT 鉴权、密码哈希自动升级、敏感字段脱敏、CSRF token、上传安全

## 📦 目录结构

```
webman/
├── app/                            # 业务代码
│   ├── admin/                      # 后台模块
│   │   ├── controller/             # 控制器（仅做参数 → 响应映射）
│   │   ├── service/                # 业务逻辑（核心规则在这里）
│   │   │   └── interface/          # Service 接口
│   │   ├── validation/             # 参数验证器
│   │   └── middleware/             # 后台专用中间件（鉴权、操作日志）
│   ├── common/                     # 通用模块
│   │   ├── enum/                   # 枚举（StatusEnum、MenuTypeEnum 等）
│   │   ├── exception/              # 业务异常（BusinessException 等）
│   │   ├── traits/                 # 通用 Trait（ApiResponse）
│   │   └── ResponseCode.php        # 响应码枚举
│   ├── controller/                 # 公共控制器（首页等）
│   ├── exception/                  # 全局异常处理器
│   ├── middleware/                 # 全局中间件
│   ├── model/                      # Eloquent 模型
│   ├── process/                    # 自定义进程
│   ├── functions.php               # 业务相关全局函数
│   └── helper.php                  # 通用工具函数（cache、sysConfig）
├── config/                         # 框架与组件配置
├── database/schema.sql             # 数据库初始化脚本
├── public/                         # Web 根目录（uploads 静态文件）
├── runtime/                        # 运行时（缓存、日志），需可写
├── support/                        # 框架基础支持
├── docs/                           # 工程文档（架构、规范、变更记录）
├── .env.example                    # 环境变量示例
├── composer.json
└── start.php
```

## 🚀 快速开始

```bash
# 1. 安装依赖
composer install

# 2. 初始化环境变量
cp .env.example .env
# 编辑 .env：DB / Redis / JWT_SECRET（≥32 位）

# 3. 导入数据库
mysql -u root -p < database/schema.sql

# 4. 启动服务（开发：debug 模式 + 热重载）
php start.php start
# Windows
php windows.php
```

服务默认监听 `0.0.0.0:8787`，路由前缀 `/admin/...`。

### 默认账号

```
用户名：admin
密  码：123456
```

## 🧱 架构约定

| 层 | 文件位置 | 职责 |
|---|---|---|
| **路由** | `#[Route]` 注解 | 由 `webman/console` 自动扫描，无需手动维护 routes 表 |
| **Validator** | `app/admin/validation/` | 参数校验（仅校验，不做业务判断） |
| **Controller** | `app/admin/controller/` | 解析参数 → 调 Service → 包装响应 |
| **Service**   | `app/admin/service/`    | 业务规则、事务、缓存清理 |
| **Model**     | `app/model/`            | 字段常量、关联关系、派生属性 |
| **Middleware**| `app/{,admin/}middleware/` | 鉴权、日志记录、CORS |

### 错误响应

业务侧统一抛 `BusinessException`，由 `app/exception/Handler` 渲染为：

```json
{ "code": 423, "msg": "xxx 已存在", "data": null }
```

> 业务码与 HTTP 状态码解耦：4xx 业务码统一返回 HTTP 200，5xx 才用 HTTP 500。
> 前端只看 `body.code` 区分成功 / 失败。

详见 `docs/ARCHITECTURE.md`。

## 📚 文档索引

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) —— 架构设计与分层
- [`docs/CODE_STYLE.md`](docs/CODE_STYLE.md) —— 代码风格 / 命名 / 注释规范
- [`docs/RESPONSE_CODES.md`](docs/RESPONSE_CODES.md) —— 业务码与异常对照
- [`docs/API.md`](docs/API.md) —— 接口约定
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) —— Docker / Nginx / Systemd 部署
- [`CHANGELOG.md`](CHANGELOG.md) —— 版本变更
- [`CONTRIBUTING.md`](CONTRIBUTING.md) —— 贡献流程

## 🔐 安全

- 密码 bcrypt 哈希，cost = 10，登录时自动升级
- JWT secret 强制 ≥ 32 字符，启动即校验
- 文件上传：扩展名 + MIME 双重校验，路径遍历过滤
- 操作日志自动脱敏 password / token / captcha 字段
- 异常响应自动脱敏绝对路径、邮箱

## 📄 许可证

MIT License

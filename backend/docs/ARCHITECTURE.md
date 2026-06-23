# 架构设计

## 分层

```
┌──────────────────────────────────────────────────────────────┐
│                    HTTP 请求 (workerman)                       │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  Middleware 链                                                 │
│  - StaticFile（全局，拦截 /. 隐藏文件）                         │
│  - AuthMiddleware（admin，鉴权 + 权限）                         │
│  - OperationLog（admin，写操作审计）                            │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  Validator（注解触发）  →   Controller                          │
│  - 仅校验参数              - 解析参数                            │
│                            - 调 Service                          │
│                            - 包装响应                            │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  Service                                                       │
│  - 业务规则（唯一性、状态机、删除前校验）                        │
│  - 事务封装                                                     │
│  - 缓存清理                                                     │
│  - 异常抛出（BusinessException）                                │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  Model（Eloquent）                                             │
│  - 字段常量（STATUS_NORMAL = 1）                                │
│  - 关联关系（belongsTo / hasMany / belongsToMany）               │
│  - 派生属性（getStatusTextAttribute）                            │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
                       MySQL  /  Redis  /  File Cache
```

### 各层职责（必读）

| 层 | **应该**做 | **不应该**做 |
|---|---|---|
| Controller | 调 Service、包装响应、设置 HTTP 头 | 写 SQL、写业务逻辑、直接 throw 文本异常 |
| Service | 业务规则、事务、调多个 Model、清缓存 | 直接 `request()` / `response()`、参数校验 |
| Model | 字段常量、关联、Scope、派生属性 | 业务规则、调其他 Service |
| Validator | 参数校验、字段名映射 | 业务唯一性校验（→ Service 层 `assertUnique`） |
| Middleware | 跨切面：鉴权、日志、CORS | 业务路由分发 |

## 异常体系

```
Throwable
├── \support\exception\HttpException    ← 框架内置
├── BusinessException                   ← 业务异常基类
│   ├── ::conflict()       → 409
│   ├── ::notFound()       → 404
│   ├── ::badRequest()     → 400
│   ├── ::forbidden()      → 403
│   └── ::unauthorized()   → 401
├── ValidationException                 ← 参数/规则校验失败（422）
├── ResourceNotFoundException           ← 资源不存在（404）
├── UnauthorizedException               ← 未登录（401）
└── ForbiddenException                  ← 无权限（403）
```

### 抛出建议

```php
// ✅ 推荐
throw BusinessException::conflict('用户名已存在');
throw BusinessException::notFound('用户不存在');

// ✅ 也可以
throw new BusinessException('账号被禁用', ResponseCode::FORBIDDEN);

// ❌ 不要在控制器/Service 中直接抛 RuntimeException
throw new \RuntimeException('xxx');  // 会被当作 5xx 异常并写错误日志
```

## 响应格式

成功：

```json
{
  "code": 200,
  "msg": "操作成功",
  "data": { /* 业务数据 */ }
}
```

失败：

```json
{
  "code": 423,
  "msg": "用户名已存在",
  "data": null
}
```

分页：

```json
{
  "code": 200,
  "msg": "操作成功",
  "data": {
    "list":  [...],
    "total": 100,
    "page":  1,
    "limit": 15
  }
}
```

## 缓存键命名

| 场景 | Key 格式 | TTL |
|---|---|---|
| 用户信息（中间件） | `auth_user_{uid}` | 5 min |
| 用户权限标识 | `user_permissions_{uid}` | 5 min |
| 用户角色 code | `user_role_codes_{uid}` | 5 min |
| 菜单权限标识 | `menu_perm:{path}` | 10 min |
| 系统配置（全部） | `system_config` | 24 h |
| 验证码 | `captcha_{key}` | 60 s |

写操作（用户更新、角色更新、菜单更新）必须主动调用 `clear_permission_cache($uid)` / `SysConfig::clearCache()`。

## 数据权限（data_scope）

| 值 | 含义 |
|---|---|
| 1 | 全部数据 |
| 2 | 本部门数据 |
| 3 | 本部门及以下数据 |
| 4 | 仅本人数据 |
| 5 | 自定义部门（`data_scope_depts` 字段） |

> 当前模板未在查询层自动注入数据权限过滤，由各 Service 按需处理。

## 添加新模块（推荐流程）

1. **建表 / 写迁移** → `database/schema.sql`
2. **建 Model** → 继承 `BaseModel`，定义字段常量、关联
3. **建 Validator** → 继承 `BaseValidator`，写 rules / scenes
4. **建 Service** → 继承 `BaseService`，设 `$modelClass`，实现 CRUD
5. **建 Controller** → 继承 `BaseController`，路由注解 `#[RouteGroup('/admin')]`
6. **配权限** → 在 `sys_menu` 中插入对应 `permission` 标识
7. **写测试**（可选） → 单元测试 / 接口测试

## 性能注意

- Service 单例：`Xxx::getInstance()` 在请求间复用，**严禁在实例字段中存放请求级状态**
- AuthMiddleware 使用了实例字段（`$currentUser`），但每次 `process` 入口已重置
- 关联预加载：`$query->with(['department', 'roles'])` 避免 N+1

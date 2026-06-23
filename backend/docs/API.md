# API 约定

## 通信

- 协议：HTTP / HTTPS
- 编码：UTF-8
- 内容类型：请求 `application/json`（POST/PUT 通用）；响应 `application/json; charset=utf-8`
- 认证：JWT Bearer Token，放在 `Authorization: Bearer <token>` 请求头中

## 路由前缀

```
/admin/<resource>[/<id>][/<action>]
```

- `/admin/login`            登录（无需鉴权）
- `/admin/captcha`          验证码（无需鉴权）
- `/admin/public/*`         公开资源（无需鉴权）
- 其他 `/admin/**`          均需 Token 鉴权 + 权限校验

## RESTful 命名

| 操作 | HTTP | 路径 | 说明 |
|---|---|---|---|
| 列表 | GET | `/admin/user` | 支持 page/limit/keyword/status |
| 详情 | GET | `/admin/user/{id}` | |
| 创建 | POST | `/admin/user` | |
| 更新 | PUT | `/admin/user/{id}` | 全量更新 |
| 部分更新 | PATCH | `/admin/user/{id}/status` | 单字段切换 |
| 删除 | DELETE | `/admin/user/{id}` | |
| 批量删除 | DELETE | `/admin/user` | body: `{ "ids": [1,2,3] }` |

## 公共参数

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `page` | int | 1 | 页码 |
| `limit` | int | 15 | 每页条数（最大 100） |
| `keyword` | string |  | 关键字（不同模块字段不同） |
| `status` | int |  | 状态过滤（0/1） |
| `start_date` | date |  | 区间开始（Y-m-d） |
| `end_date` | date |  | 区间结束（Y-m-d） |

## 响应

### 成功

```json
{
  "code": 200,
  "msg":  "操作成功",
  "data": { /* ... */ }
}
```

### 分页

```json
{
  "code": 200,
  "msg":  "操作成功",
  "data": {
    "list":  [ { "id": 1, "name": "..." } ],
    "total": 128,
    "page":  1,
    "limit": 15
  }
}
```

### 失败

```json
{
  "code": 409,
  "msg":  "用户名已存在"
}
```

具体业务码请参见 [`RESPONSE_CODES.md`](RESPONSE_CODES.md)。

## 鉴权流程

```text
1) POST /admin/login                      → { token, user }
2) 任意请求带 Authorization: Bearer <token>
3) 服务端 JwtService::decode(token)
   ├─ 失效 → 401 UnauthorizedException
   └─ 通过 → 注入 admin_user / admin_user_id 到 Request
4) AuthMiddleware 按路径查 sys_menu.permission 鉴权
   ├─ 超级管理员（role.code = super_admin）→ 直接放行
   ├─ 普通用户拥有该 permission → 放行
   └─ 否则 → 403 ForbiddenException
```

## 接口模块速览

| 模块 | 路由前缀 | 控制器 |
|---|---|---|
| 登录 / Token | `/admin/login`, `/admin/logout`, `/admin/captcha`, `/admin/refresh` | LoginController |
| 用户 | `/admin/user` | UserController |
| 角色 | `/admin/role` | RoleController |
| 菜单 | `/admin/menu` | MenuController |
| 部门 | `/admin/dept` | DeptController |
| 字典 | `/admin/dict`, `/admin/dict-data` | DictController |
| 系统配置 | `/admin/config` | ConfigController |
| 文件 | `/admin/file` | FileController |
| 日志 | `/admin/log/operation`, `/admin/log/login` | LogController |

具体接口签名以源码注解为准（每个 Action 头部均有路径与说明）。

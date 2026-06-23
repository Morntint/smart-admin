# 业务码与异常对照

## 业务码（`ResponseCode` 枚举）

| 码 | 常量 | 含义 | HTTP Status |
|---|---|---|---|
| 200 | `SUCCESS` | 操作成功 | 200 |
| 400 | `BAD_REQUEST` | 请求参数错误 | 200 |
| 401 | `UNAUTHORIZED` | 未登录或登录已过期 | 200 |
| 403 | `FORBIDDEN` | 无权限访问 | 200 |
| 404 | `NOT_FOUND` | 资源不存在 | 200 |
| 405 | `METHOD_NOT_ALLOWED` | 请求方法不被允许 | 200 |
| 409 | `CONFLICT` | 资源冲突（唯一约束） | 200 |
| 422 | `VALIDATION_FAIL` | 参数校验失败 | 200 |
| 423 | `BUSINESS_FAIL` | 业务处理失败（默认） | 200 |
| 429 | `TOO_MANY_REQUESTS` | 频次限制 | 200 |
| 500 | `SERVER_ERROR` | 服务器内部错误 | 500 |
| 503 | `SERVICE_UNAVAILABLE` | 服务暂不可用 | 500 |

> 4xx 业务错误统一以 HTTP 200 返回（前端通过 `body.code` 区分），
> 5xx 服务端错误才使用 HTTP 500。

## 异常类对照

| 异常类 | 默认业务码 | 何时使用 |
|---|---|---|
| `BusinessException` | 423 | 通用业务失败；推荐使用静态方法构造 |
| `ValidationException` | 422 | 参数 / 业务规则校验失败 |
| `ResourceNotFoundException` | 404 | 资源不存在（少用，优先用 `BusinessException::notFound`） |
| `UnauthorizedException` | 401 | 未登录、Token 失效（中间件抛） |
| `ForbiddenException` | 403 | 已登录但权限不足（中间件抛） |

## 推荐写法

```php
// ── 业务侧 ───────────────────────────────────────────
throw BusinessException::notFound('用户不存在');           // 404
throw BusinessException::conflict('用户名已存在');         // 409
throw BusinessException::badRequest('请选择要删除的项');    // 400
throw BusinessException::forbidden('当前操作不被允许');    // 403

// 任意业务码（不在静态方法列表中时）
throw new BusinessException('账号已被禁用', ResponseCode::FORBIDDEN);

// ── 鉴权中间件 ───────────────────────────────────────
throw new UnauthorizedException('请先登录');              // 401
throw new ForbiddenException('无权限访问');               // 403
```

## 错误响应示例

```json
{
  "code": 409,
  "msg": "用户名已存在"
}
```

带字段错误（`ValidationException`）：

```json
{
  "code": 422,
  "msg": "数据验证失败",
  "data": {
    "errors": {
      "username": "用户名格式不正确",
      "mobile":   "手机号已存在"
    }
  }
}
```

调试模式（`APP_DEBUG=true`）下额外返回 `data.exception / file / line / trace`。

# API 文档自动化（OpenAPI / Swagger）

> 用注解从代码生成 OpenAPI 3 文档，替代手写维护，前端可直接对接 Swagger UI 联调。
> 基于 `zircote/swagger-php`（require-dev）。

---

## 一、安装依赖

```bash
composer install      # 已在 require-dev 声明 zircote/swagger-php
```

## 二、生成文档

```bash
php webman openapi:gen                       # 生成 public/openapi.json
php webman openapi:gen --format=yaml -o public/openapi.yaml
# 或用 composer 脚本
composer openapi
```

## 三、查看文档

启动服务后，浏览器打开：

```
http://127.0.0.1:8787/swagger
```

Swagger UI 会加载 `/openapi.json` 渲染交互式文档，可直接在页面上：
- 点 **Authorize** 填入 JWT（`Bearer <token>`），后续请求自动带上；
- 对每个接口 **Try it out** 在线发请求联调。

---

## 四、给接口加文档

在控制器方法上加 `#[OA\*]` 注解（与路由注解共存）。示例见 `app/admin/controller/UserController.php` 的 `index` / `store`。

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/admin/user',
    summary: '用户分页列表',
    tags: ['用户管理'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
    ],
    responses: [
        new OA\Response(response: 200, description: '成功',
            content: new OA\JsonContent(ref: '#/components/schemas/Pagination')),
    ]
)]
#[Get('/user')]
public function index(Request $request): Response { ... }
```

### 全局定义

`app/common/openapi/OpenApiDefinition.php` 集中声明：
- API 元信息（标题、版本、描述）
- `bearerAuth` 安全方案（JWT）
- 通用 Schema：`ApiSuccess` / `ApiError` / `Pagination`

接口响应直接 `ref` 这些 Schema 即可，无需重复定义。

---

## 五、工作流建议

- **开发期**：改完接口注解 → `php webman openapi:gen` → 刷新 /swagger 查看。
- **CI**：可在流水线加 `composer openapi` 校验注解无错（生成失败即注解有问题）。
- **前端协作**：把 `public/openapi.json` 提供给前端，可生成 TypeScript 类型/请求 SDK。
- 手写的 `docs/API.md` 仍保留作为「约定总览」（响应格式、错误码、命名规范），
  与自动生成的「接口明细」互补。

---

## 六、注意

- `openapi.json` 是生成产物，建议 `.gitignore` 排除（或 CI 产出），避免与代码不同步。
- 仅给**对外/需联调**的接口加注解即可，不必全量覆盖。
- 注解不影响运行时性能（仅 `openapi:gen` 扫描时解析）。

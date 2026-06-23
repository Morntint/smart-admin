# 代码风格

## 总则

- PHP 8.1+，严格类型（参数、返回值、属性都加类型声明）
- 统一使用 4 空格缩进，UTF-8 无 BOM，LF 行尾
- 所有公共类 / 方法必须有 PHPDoc，注释一律使用中文，简明扼要
- 优先使用现代语法：`enum`、`readonly`、`match`、`?->`、Constructor Promotion

## 命名

| 对象 | 风格 | 示例 |
|---|---|---|
| 类、Trait、Interface | PascalCase | `UserService`, `ApiResponse`, `JwtServiceInterface` |
| 方法、变量、属性 | camelCase | `pageList`, `$userId` |
| 常量 | SCREAMING_SNAKE_CASE | `STATUS_NORMAL`, `SUPER_ADMIN_ID` |
| 数据库字段、JSON 键 | snake_case | `created_at`, `dept_id` |
| 路由 | kebab-case + RESTful | `/admin/user/{id}/reset-password` |

### 约定后缀

- Service → `XxxService`（业务逻辑）
- Controller → `XxxController`
- Validator → `XxxValidator`
- Model → 直接业务名，如 `SysUser`、`SysMenu`
- Exception → `XxxException`

## PHPDoc

```php
/**
 * 创建用户
 *
 * @param array<string,mixed> $data       已通过 Validator 校验的输入
 * @param int                 $operatorId 操作者用户 ID
 * @throws BusinessException 用户名/手机号已存在时抛出
 */
public function create(array $data, int $operatorId): SysUser
{
    // ...
}
```

- 数组参数尽量声明形状：`array{list: Collection, total: int}` 或 `array<string,mixed>`
- 抛出的关键异常用 `@throws` 标注
- 业务约束 / 规则写在类头注释中，让阅读者一眼看懂"为什么这么做"

## 控制器

```php
#[Get('/user/{id}')]
public function show(Request $request, int $id): Response
{
    return $this->success($this->userService->detail($id));
}
```

- 一个 Action 通常 ≤ 5 行
- 不在控制器中写 `if ($notFound) throw ...` —— 该在 Service 里抛
- 分页响应统一用 `$this->pageResponse($result)`

## Service

```php
public function update(int $id, array $data, int $operatorId): SysUser
{
    /** @var SysUser $user */
    $user = $this->findOrFail($id, [], '用户不存在');
    $this->assertUnique('mobile', $data['mobile'] ?? '', $id, '手机号已存在');

    $this->transaction(function () use ($user, $data, $operatorId) {
        $user->fill([...])->save();
        SysUserRole::addUserRoles($user->id, $data['role_ids'] ?? []);
    });

    clear_permission_cache($id);
    return $user;
}
```

- 写操作涉及多张表 → 必须 `transaction()` 包裹
- 写完后涉及缓存 → 调 `clear_permission_cache()` / `SysConfig::clearCache()`
- 业务异常抛 `BusinessException`，不抛 `\RuntimeException`
- 方法 ≤ 30 行；超出时考虑抽 `private` 辅助方法

## Model

- 只放字段常量（`STATUS_NORMAL`）、关联（`belongsTo`）、Scope、派生属性（`getXxxAttribute`）
- **不要**在 Model 中调用其他 Service 或写跨表业务逻辑
- 时间戳：业务表通常 `$timestamps = false` 由 Service 显式设置 `created_at` / `updated_at`，
  日志类的纯插入表使用 `$fillable` + `$timestamps = false`

## Validator

- `$rules` 写所有可能字段
- `$scenes` 按场景挑选字段
- `$messages` 只写需要中文化的关键消息（基类已有通用模板）
- `$attributes` 写字段中文名，让错误消息更友好

## 异常处理

```php
// 业务侧（推荐）
throw BusinessException::conflict('用户名已存在');

// 控制器侧（已经登录但权限不够）
throw new ForbiddenException();
```

不要：

```php
// ❌ 直接 return JSON，跳过统一处理器
return $this->error('xxx');     // 大多数情况下应该 throw

// ❌ 把校验放在 Validator 之外
if ($id < 1) { ... }            // → 用 Validator 的 rules
```

## 文件 I/O

- 读写 `public/uploads/` 必须经过 `realpath()` 校验，限定在白名单目录内（参见 `FileService`）
- 文件名必须经过 `sanitizeFilename()`：去除 `..`、`/`、`\\` 与控制字符

## 数据库

- 不要在循环里发 SQL —— 用 `whereIn` / `with` 预加载
- 关键字段加索引：`status`、`parent_id`、`dict_id`、`user_id`、`created_at`
- LIKE 查询用 `safe_like_pattern()`，避免用户输入的 `%` `_` 干扰

## 缓存

- 每条缓存都要有明确 TTL，**禁止永久缓存动态业务数据**
- 写操作完成后必须主动失效相关缓存键
- 命名遵循：`{业务}_{对象}_{id}` 或 `{业务}:{key}`

## Git Commit

```
feat:    新功能
fix:     bug 修复
refactor: 重构（无功能变化）
perf:    性能优化
docs:    文档
test:    测试
chore:   构建/工具/依赖
style:   代码风格（不影响逻辑）
```

示例：`feat(user): 增加重置密码接口` / `fix(auth): 修复 token 解析 NullPointer`

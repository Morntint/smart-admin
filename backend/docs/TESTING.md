# 测试与持续集成

> PHPUnit 单元/功能测试 + PHPStan 静态分析 + GitHub Actions CI。

---

## 一、本地运行

```bash
cd backend
composer install            # 安装 require-dev（phpunit / phpstan / swagger-php）

composer test               # 跑全部测试（= vendor/bin/phpunit）
composer analyse            # 静态分析（= vendor/bin/phpstan analyse）

# 单个测试套件 / 文件
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit tests/Unit/SnowflakeTest.php
```

---

## 二、目录结构

```
backend/
├── phpunit.xml             # PHPUnit 配置（bootstrap、testsuite、source 覆盖范围）
├── phpstan.neon            # PHPStan 配置（level 5，忽略框架动态特性）
└── tests/
    ├── bootstrap.php       # 测试引导：加载 autoload/.env/配置/helper（不启动服务）
    ├── Unit/               # 单元测试（纯逻辑，无 IO）
    │   ├── SnowflakeTest.php
    │   ├── BusinessExceptionTest.php
    │   └── HelpersTest.php
    └── Feature/            # 功能/集成测试（需 DB/Redis，按需补充）
```

`tests/bootstrap.php` 会：
- 加载 Composer autoload 与 `.env`；
- `loadAllConfig` 加载全部配置；
- 显式 require `config/autoload.php` 里声明的 helper 文件（`functions.php`/`helper.php`）；
- 强制 `CACHE_DRIVER=array`，使纯逻辑测试无需 Redis/文件缓存。

---

## 三、已覆盖的测试

| 测试 | 覆盖内容 |
|---|---|
| `SnowflakeTest` | ID 唯一性（5000 个不重复）、正数、趋势递增、非法 worker_id 抛错 |
| `BusinessExceptionTest` | 异常工厂方法码值、ResponseCode 分类与文案 |
| `HelpersTest` | safe_like 防注入、密码哈希、时间格式、cache_remember 命中/空值缓存 |

> 这些都是**纯逻辑**测试，不依赖 DB。涉及 DB 的 Service 测试（如 UserService CRUD、
> 数据权限过滤）属 Feature 测试，需在 CI 里连真实 MySQL 或用内存 SQLite，按需扩展。

---

## 四、编写新测试

```php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FooTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertSame(2, 1 + 1);
    }
}
```

- 命名空间 `Tests\`（已在 composer `autoload-dev` 映射到 `tests/`）。
- 纯逻辑放 `Unit/`；需要数据库/HTTP 的放 `Feature/`。

---

## 五、CI（GitHub Actions）

工作流：`.github/workflows/backend-ci.yml`，在 push/PR 触及 `backend/**` 时触发：

1. 装 PHP 8.2 + 扩展（pdo_mysql/pcntl/redis 等）
2. `composer validate --strict`
3. `composer install`（带 vendor 缓存）
4. `composer analyse`（PHPStan，初期 `|| true` 容忍，逐步收紧）
5. `composer test`（PHPUnit，附带 Redis service 容器）

### 逐步收紧建议

- PHPStan 当前 level 5 且对部分静态分析误报做了忽略；稳定后可升到 level 6+ 并去掉 CI 里的 `|| true`。
- Feature 测试接入后，给 CI 加 MySQL service 容器并导入 `database/schema.sql`。

---

## 六、注意

- **本机无法 composer install** 时，测试代码已写好，依赖到位后 `composer test` 即可运行。
- 测试默认 array 缓存驱动，不污染真实 file/redis 缓存。
- 不要在测试里连生产数据库；Feature 测试用独立测试库。

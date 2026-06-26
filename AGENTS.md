# Smart Admin — AGENTS.md

## Project structure

Monorepo with two independent packages:

- `backend/` — Webman 2.1 (PHP 8.2+), annotation-routed, JWT + RBAC admin API
- `frontend/` — Vue 3.5 + TypeScript 5.6 + Vite 7 + Element Plus 2.11

## Quick start

```bash
# backend
cd backend && cp .env.example .env       # then edit DB/Redis/JWT secrets
composer install && mysql -u root -p < database/schema.sql
php start.php start                       # http://127.0.0.1:8787

# frontend
cd frontend && pnpm install
pnpm dev                                  # http://localhost:5173 (proxies /admin → 8787)
```

Default admin: `admin` / `admin123`

## Commands

| Context | Command | Notes |
|---------|---------|-------|
| backend | `php start.php start` | Dev server (not `php artisan serve`) |
| backend | `php start.php start -d` | Daemon mode |
| backend | `composer test` | PHPUnit (Unit suite) |
| backend | `composer analyse` | PHPStan level 5 |
| backend | `composer openapi` | Generate `public/openapi.json` from annotations |
| frontend | `pnpm dev` | Opens browser, proxies `/admin` → `127.0.0.1:8787` |
| frontend | `pnpm build` | Runs `vue-tsc --noEmit` first, then Vite build |
| frontend | `pnpm lint` | ESLint |
| frontend | `pnpm fix` | ESLint `--fix` |
| frontend | `pnpm lint:prettier` | Prettier write |
| frontend | `pnpm lint:stylelint` | Stylelint fix |
| frontend | `pnpm commit` | Commitizen (cz-git) |
| frontend | `pnpm clean:dev` | Removes dev cache (`tsx scripts/clean-dev.ts`) |

## Backend architecture

- **Annotation routing**: `#[Get('/user')]`, `#[Post('/user')]`, `#[Put('/user/{id}')]`, `#[Delete('/user/{id}')]`, `#[Patch]` — auto-registered by `webman/console`. No manual route registration needed.
- **Controller → Service → Model**: Controllers only parse params and return responses. All business logic in `app/admin/service/*`. Services use `BaseService::getInstance()` singleton (no DI container).
- **Permission check**: `#[RequiresPermission('system:user:add')]` on controller methods. Super admin (ID=1) bypasses all checks.
- **Idempotency**: `#[Idempotent(window: 5)]` deduplicates writes within the window.
- **Middleware chain** (in request direction): `Trace → Metrics → Cors → AuthMiddleware → RateLimit → Idempotent → OperationLog`
- **JWT auth**: Token in `Authorization: Bearer <token>` header or `?token=` query param. Token version (`tv` in payload) invalidates old tokens on password change/logout.
- **Auth exceptions**: `/admin/login`, `/admin/captcha`, `/admin/public/*` are whitelisted.
- **Standard response**: `{ code: 200, msg: "success", data: {...} }`. Use `$this->success()`, `$this->pageResponse()`, `$this->error()` from `ApiResponse` trait.
- **DB**: MySQL with optional read-write splitting (`DB_READ_HOST`). Eloquent 12.x. Connection pool only works under Swoole/Swow coroutine mode.
- **Cache driver**: File by default; set `CACHE_DRIVER=redis` for multi-process/coroutine. User info cached with key `auth_user_{id}` (5 min TTL).
- **PHPStan level 5** — ignores several well-known webman/Eloquent dynamic properties (see `phpstan.neon`).
- **Migrations**: Manual SQL files in `database/` (no Laravel-style migration runner).

## Frontend architecture

- **Hash routing**: `createWebHashHistory()` (not history mode).
- **Auto-imports**: `unplugin-auto-import` + `unplugin-vue-components` with `ElementPlusResolver`. No manual `import` needed for Vue/Vue Router/Pinia/@vueuse/core APIs or Element Plus components.
- **Path aliases**: `@/` → `src/`, `@views/`, `@stores/`, `@styles/`, `@utils/`, etc. (defined in both `vite.config.ts` and `tsconfig.json`).
- **HTTP client** (`src/utils/http/`): Axios wrapper with auto Bearer token, silent Token refresh (5 min ahead of expiry), 401 debounced auto-logout, optional `showSuccessMessage` / `showErrorMessage` on calls.
- **API files**: Grouped in `src/api/` by domain (e.g. `system-manage.ts`, `auth.ts`, `ai-manage.ts`). Use `request.get<T>()`, `request.post<T>()`, etc.
- **ESLint**: Single quotes, no semicolons, `@typescript-eslint/no-explicit-any: off`, `vue/multi-word-component-names: off`.
- **Build**: `terser` for minification, drops `console`/`debugger` in production, gzip compression for files >10KB.
- **SCSS**: Globally injects `@styles/core/el-light.scss` and `@styles/core/mixin.scss` into every SCSS context via `vite.config.ts` `additionalData`.
- **State management**: Pinia with persisted state plugin.

## Key conventions

- User ID `1` is super admin — never delete or disable.
- Backend controllers always use response trait methods; never `echo` or `var_dump`.
- `showSuccessMessage: true` on frontend API calls triggers a success toast (opt-in, not default).
- Menu routes are dynamically loaded from the API (`/admin/menu/routes`) after login.
- Manual SQL migrations live in `backend/database/` — use sequential naming.
- Frontend does NOT have test infrastructure (no test runner configured).

## Important files

- `backend/docs/` — COROUTINE.md, READ_WRITE_SPLIT.md, DEPLOYMENT.md, etc.
- `backend/config/route.php` — only non-annotation routes (health check, metrics, OPTIONS CORS catch-all)
- `backend/.env.example` — documents every env var with comments
- `frontend/src/types/api/api.d.ts` — all API response type definitions

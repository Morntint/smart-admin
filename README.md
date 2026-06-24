# Smart Admin - 企业级后台管理系统

基于 Webman + Vue 3 的现代化企业级后台管理系统，采用前后端分离架构，提供完整的 RBAC 权限管理、系统配置、日志审计等功能。

## 技术栈

### 后端
- **框架**: Webman 2.1 (高性能 PHP 框架)
- **PHP**: 8.2+
- **ORM**: Laravel Eloquent 12.x
- **缓存**: Laravel Cache 12.x + Redis
- **认证**: JWT (firebase/php-jwt 7.0)
- **验证**: Laravel Validation 2.2
- **数据库**: MySQL 5.7+ / 8.0

### 前端
- **框架**: Vue 3.5 + TypeScript 5.6
- **构建工具**: Vite 7.1
- **UI 组件**: Element Plus 2.11
- **状态管理**: Pinia 3.0
- **路由**: Vue Router 4.5
- **样式**: Tailwind CSS 4.1 + SCSS
- **图表**: ECharts 6.0
- **HTTP**: Axios 1.12
- **代码规范**: ESLint + Prettier + Stylelint + Husky

## 功能模块

### 系统管理
- **用户管理**: 用户增删改查、状态切换、重置密码、批量删除
- **角色管理**: 角色维护、菜单权限分配、数据权限控制
- **菜单管理**: 树形菜单配置（目录/菜单/按钮）、动态路由
- **部门管理**: 组织架构树形管理
- **系统配置**: 键值对配置管理、支持多种数据类型
- **字典管理**: 数据字典维护、字典数据管理

### 日志审计
- **操作日志**: 记录用户操作行为、请求参数、执行时长
- **登录日志**: 登录/登出记录、IP 地址追踪
- **系统日志**: 系统运行日志查看

### 权限控制
- RBAC 权限模型（用户-角色-菜单）
- 按钮级权限控制
- 数据权限范围（全部/本部门/本部门及以下/仅本人/自定义）
- JWT Token 认证

## 项目结构

```
smart-admin/
├── backend/                 # 后端项目
│   ├── app/
│   │   ├── admin/          # 管理后台模块
│   │   │   ├── controller/ # 控制器
│   │   │   ├── service/    # 业务逻辑层
│   │   │   ├── validation/ # 表单验证
│   │   │   └── middleware/ # 中间件
│   │   ├── model/          # 数据模型
│   │   └── common/         # 公共模块（异常、枚举、响应码）
│   ├── config/             # 配置文件
│   ├── database/           # 数据库脚本
│   └── public/             # 静态资源
│
└── frontend/               # 前端项目
    ├── src/
    │   ├── api/           # API 接口
    │   ├── views/         # 页面视图
    │   ├── components/    # 组件库
    │   ├── router/        # 路由配置
    │   ├── store/         # 状态管理
    │   ├── hooks/         # 组合式函数
    │   └── types/         # TypeScript 类型定义
    └── public/            # 静态资源
```

## 快速开始

### 环境要求
- PHP >= 8.2
- Node.js >= 20.19.0
- pnpm >= 8.8.0
- MySQL >= 5.7
- Redis

### 后端部署

```bash
cd backend

# 安装依赖
composer install

# 配置环境变量
cp .env.example .env
# 编辑 .env 配置数据库和 Redis 连接

# 初始化数据库
mysql -u root -p < database/schema.sql

# 启动服务（开发环境）
php start.php start

# 或生产环境
php start.php start -d
```

默认管理员账号：`admin` / `admin123`

### 前端部署

```bash
cd frontend

# 安装依赖
pnpm install

# 开发环境
pnpm dev

# 生产构建
pnpm build
```

前端默认访问地址：http://localhost:5173

## 数据库表结构

| 表名 | 说明 |
|------|------|
| sys_user | 用户表 |
| sys_role | 角色表 |
| sys_menu | 菜单表 |
| sys_department | 部门表 |
| sys_user_role | 用户角色关联表 |
| sys_role_menu | 角色菜单关联表 |
| sys_config | 系统配置表 |
| sys_dict | 字典类型表 |
| sys_dict_data | 字典数据表 |
| sys_operation_log | 操作日志表 |
| sys_login_log | 登录日志表 |
| sys_file | 文件管理表 |

## API 规范

### 统一响应格式
```json
{
  "code": 200,
  "message": "success",
  "data": {}
}
```

### 错误码
- `200`: 成功
- `400`: 参数错误
- `401`: 未认证
- `403`: 无权限
- `404`: 资源不存在
- `500`: 服务器错误

## 开发规范

### 后端
- 控制器只负责参数接收和响应返回
- 业务逻辑统一放在 Service 层
- 使用注解路由（#[Get / #[Post / #[Put / #[Delete]）
- 表单验证使用 Laravel Validation

### 前端
- 使用 Composition API + `<script setup>`
- 组件命名采用 PascalCase
- API 接口统一在 `src/api/` 目录管理
- 类型定义统一在 `src/types/` 目录

## 特性

- 前后端完全分离，API 驱动
- 动态路由和菜单权限
- 多环境配置（开发/生产）
- 代码规范自动检查（ESLint + Prettier + Husky）
- 响应式布局，支持移动端
- 深色模式支持
- 国际化支持
- 丰富的图表组件
- 完整的日志审计

## 许可证

MIT

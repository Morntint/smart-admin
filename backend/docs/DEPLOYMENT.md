# 部署指南

## 1. 环境要求

| 组件 | 版本 | 备注 |
|---|---|---|
| PHP | >= 8.1（推荐 8.3） | 需启用 `pdo_mysql`、`pcntl`、`opcache` 扩展 |
| Swoole | >= 5.x（可选） | 协程驱动；纯 workerman 进程模式可不装 |
| MySQL | >= 5.7（推荐 8.0） | utf8mb4 字符集 |
| Redis | >= 6.0（可选） | 用于缓存 / Session 共享 |
| Workerman | ^4.x | 由 `workerman/webman-framework` 引入 |

## 2. 一键部署（Docker）

```bash
# 1. 准备 .env
cp .env.example .env
$EDITOR .env  # 修改 DB_PASSWORD / JWT_SECRET 等

# 2. 启动 app + mysql + redis
docker compose up -d

# 3. 验证
curl http://localhost:8787/ping   # → {"code":200,"msg":"pong"}
```

> 数据库 Schema 会在 `mysql` 容器首次启动时由
> `./database/schema.sql` 自动导入。

## 3. 传统部署（Linux）

```bash
# 1. 安装 PHP 扩展（以 Debian/Ubuntu 为例）
apt install php-cli php-mysql php-bcmath php-curl php-mbstring \
            php-xml php-zip php-pcntl

# 2. 安装 Composer 依赖
composer install --no-dev --optimize-autoloader

# 3. 导入数据库
mysql -u root -p < database/schema.sql

# 4. 修改 .env
cp .env.example .env
$EDITOR .env

# 5. 启动
php start.php start         # 前台运行
php start.php start -d      # 守护进程
php start.php reload        # 热重载
php start.php stop          # 停止
```

## 4. Nginx 反向代理

```nginx
server {
    listen 80;
    server_name api.example.com;

    # 上传文件
    client_max_body_size 20m;

    # 静态资源交给 Nginx
    location /static/ {
        alias /var/www/webman-admin/public/static/;
        expires 7d;
        access_log off;
    }
    location /uploads/ {
        alias /var/www/webman-admin/public/uploads/;
        expires 30d;
        access_log off;
    }

    # 代理到 webman（监听 127.0.0.1:8787）
    location / {
        proxy_pass         http://127.0.0.1:8787;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   Upgrade           $http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 60s;
    }
}
```

## 5. Systemd 托管

`/etc/systemd/system/webman-admin.service`：

```ini
[Unit]
Description=Webman Admin Daemon
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/webman-admin
ExecStart=/usr/bin/php start.php start
ExecReload=/bin/kill -USR1 $MAINPID
ExecStop=/bin/kill -TERM $MAINPID
Restart=always
RestartSec=5
LimitNOFILE=65535
Environment=APP_ENV=production
Environment=APP_DEBUG=false

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now webman-admin
systemctl status webman-admin
```

## 6. 上线 Checklist

- [ ] `.env` 中 `APP_DEBUG=false`、`APP_ENV=production`
- [ ] `JWT_SECRET` 替换为 32 字节以上随机串
- [ ] `JWT_ISSUER` 设置为当前服务标识
- [ ] 数据库账号仅授予本业务库权限
- [ ] 防火墙仅开放 80/443（webman 8787 端口不对外）
- [ ] 启用 HTTPS（Nginx + Let's Encrypt）
- [ ] 设置 `SESSION_COOKIE_SECURE=true`
- [ ] `runtime/logs/` 接入日志收集（Loki / ELK）
- [ ] 监控 `/ping` 探活
- [ ] 备份策略：数据库每日全备 + binlog
- [ ] 性能测试：`wrk -t4 -c100 -d30s http://.../admin/ping`

## 7. 性能调优

| 维度 | 推荐值 |
|---|---|
| Worker 数 | `cpu_count() * 4`（默认） |
| DB 连接池 | max 5-10，min 1（根据 QPS 调整） |
| Redis 连接池 | max 5-10 |
| 缓存 | 启用 Redis 替代 file（`CACHE_DRIVER=redis`） |
| Opcache | `opcache.enable=1`，CLI 同样开启以便预热 |
| PHP 8.3 JIT | `opcache.jit_buffer_size=128M` |
| 上传大小 | 与 `sys_config.upload_file_size` 保持一致 |

## 8. 常见问题

**Q: 启动报 "JWT secret too short"**
A: 检查 `.env` 中 `JWT_SECRET` 长度 ≥ 32 字符；生产用 `openssl rand -hex 32` 生成。

**Q: 上传 413 Payload Too Large**
A: Nginx 加 `client_max_body_size`；workerman 改 `config/server.php` 的 `max_package_size`；应用层 `sys_config.upload_file_size`。

**Q: 操作日志写入失败影响业务**
A: 已在 `OperationLog::process` 中用 `try/catch` 静默吞掉异常，不会影响主链路。

**Q: 修改 sys_config 后没生效**
A: 写入已自动清缓存；如手动改数据库记得调 `SysConfig::clearCache()`。

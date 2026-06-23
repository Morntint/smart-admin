# ──────────────────────────────────────────────────────────────
# Webman Admin 生产镜像
#
# 使用：
#   docker build -t webman-admin .
#   docker run -d -p 8787:8787 --env-file .env webman-admin
# ──────────────────────────────────────────────────────────────
FROM php:8.3-cli-alpine AS base

LABEL maintainer="ops@example.com"

# ─── 系统依赖 ─────────────────────────────────────────────────
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && apk add --no-cache libstdc++ libgcc \
 && docker-php-source extract

# ─── PHP 扩展 ────────────────────────────────────────────────
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pcntl bcmath \
 && docker-php-ext-enable opcache pcntl

# ─── PHP 配置 ────────────────────────────────────────────────
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "memory_limit=512M"           >> "$PHP_INI_DIR/conf.d/00-app.ini" \
 && echo "date.timezone=Asia/Shanghai" >> "$PHP_INI_DIR/conf.d/00-app.ini" \
 && echo "opcache.enable=1"            >> "$PHP_INI_DIR/conf.d/00-app.ini" \
 && echo "opcache.enable_cli=1"        >> "$PHP_INI_DIR/conf.d/00-app.ini" \
 && echo "opcache.jit_buffer_size=128M">> "$PHP_INI_DIR/conf.d/00-app.ini"

# ─── 清理 ────────────────────────────────────────────────────
RUN docker-php-source delete \
 && apk del .build-deps \
 && rm -rf /var/cache/apk/* /tmp/*

# ─── 应用 ────────────────────────────────────────────────────
WORKDIR /app
COPY . /app

EXPOSE 8787

# 优雅停止：SIGTERM 触发 webman 的 stop 流程
STOPSIGNAL SIGTERM

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD wget -q -O - http://127.0.0.1:8787/ping || exit 1

CMD ["php", "start.php", "start"]

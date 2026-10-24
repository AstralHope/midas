# 核心修复：锁定至修复版本 php:8.2.35-fpm-alpine
FROM php:8.2.35-fpm-alpine

# 安装常用工具和 PHP 扩展，并通过 apk upgrade 修复系统级 CVE 漏洞（如 CVE-2026-109618、CVE-2026-109619）
# 编译完成后清理 $PHPIZE_DEPS 与 libzip-dev 编译依赖，仅保留运行时 libzip
RUN apk update && apk upgrade --no-cache \
    && apk add --no-cache \
        $PHPIZE_DEPS \
        libzip-dev \
        libzip \
        unzip \
    && docker-php-ext-install pdo_mysql opcache zip \
    && apk del $PHPIZE_DEPS libzip-dev \
    && rm -rf /var/cache/apk/*

# 设置工作目录
WORKDIR /var/www/html

# docker/php/Dockerfile
FROM wordpress:6.5-php8.2-fpm

# インストールする PHP 拡張を追加したい場合は build-arg で指定
RUN docker-php-ext-install pdo_mysql

# Composer を導入し、プラグインごとの依存解決に利用
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 認証情報を env で注入するための tweek
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

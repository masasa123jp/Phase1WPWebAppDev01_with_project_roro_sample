# WordPress + XDebug + wp-cli for local development
FROM wordpress:6.5-php8.2-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip less nano \
 && docker-php-ext-install zip \
 && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp

# Enable XDebug for local debugging (disabled by default on XServer)
RUN pecl install xdebug-3.3.1 && docker-php-ext-enable xdebug

# Composer (for PHPCS etc.)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

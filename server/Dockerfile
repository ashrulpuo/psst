# syntax=docker/dockerfile:1.7

# ---- Composer dependencies (no dev, optimized autoloader) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The composer image has no GMP; the runtime stage below installs it, so skip only that platform check here.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-gmp
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction --ignore-platform-req=ext-gmp

# ---- Runtime ----
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache icu-libs libpq gmp \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev gmp-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl opcache gmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app ./

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD php -r "exit(@fsockopen('127.0.0.1', 9000) ? 0 : 1);"
CMD ["php-fpm"]

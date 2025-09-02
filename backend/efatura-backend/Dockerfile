# --- Build vendor with Composer (no-dev) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --ignore-platform-req=ext-pcntl
COPY . .
RUN composer dump-autoload -o

# --- PHP-FPM runtime ---
FROM php:8.2-fpm-alpine

RUN apk add --no-cache bash curl git icu-dev libzip-dev oniguruma-dev libxml2-dev \
  && docker-php-ext-install pdo_mysql intl zip soap pcntl \
  && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Copy application (including vendor from previous stage)
COPY --chown=www-data:www-data --from=vendor /app /var/www/html

# Ensure writable dirs
RUN mkdir -p storage bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]



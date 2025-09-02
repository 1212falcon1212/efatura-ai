FROM php:8.2-fpm

RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      bash git curl zip unzip \
      libicu-dev libxml2-dev libonig-dev; \
    docker-php-ext-install -j"$(nproc)" intl pdo_mysql soap opcache; \
    php -v; \
    rm -rf /var/lib/apt/lists/*

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

WORKDIR /var/www/html

# Disable Xdebug/Redis (safety) if present
RUN echo "; zend_extension=xdebug" > /usr/local/etc/php/conf.d/zz-no-xdebug.ini \
 && echo "; extension=redis.so" > /usr/local/etc/php/conf.d/zz-no-redis.ini

CMD ["php-fpm"]



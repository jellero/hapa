FROM php:8.4-fpm-alpine

RUN apk add --no-cache git icu-dev libzip-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY docker/php/conf.d/security.ini /usr/local/etc/php/conf.d/zz-hapa-security.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]

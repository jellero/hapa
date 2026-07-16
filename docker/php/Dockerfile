FROM php:8.4-fpm-alpine

RUN apk add --no-cache git icu-libs libzip libpq oniguruma \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev linux-headers oniguruma-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install intl mbstring pdo pdo_pgsql sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && git config --system --add safe.directory /var/www/html

COPY docker/php/conf.d/security.ini /usr/local/etc/php/conf.d/zz-hapa-security.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]

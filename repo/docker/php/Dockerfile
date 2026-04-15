FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    bash \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        opcache \
        zip \
        mbstring \
        bcmath \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/backend

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

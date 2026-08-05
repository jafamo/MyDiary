ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        opcache \
        zip \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

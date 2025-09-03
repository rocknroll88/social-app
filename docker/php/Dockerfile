FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
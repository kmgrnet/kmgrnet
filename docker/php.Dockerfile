FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mysqli \
    && docker-php-ext-enable pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

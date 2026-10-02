FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# Ziggy type imports resolve against vendor during the PHP stage; Vite bundles its Vue plugin there.

FROM php:8.3-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev libicu-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql bcmath zip gd mbstring dom intl pcntl posix opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache

FROM assets AS build-assets
COPY --from=php-base /var/www/html/vendor /app/vendor
RUN npm run build

FROM nginx:1.28-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=php-base /var/www/html/public /var/www/html/public
COPY --from=build-assets /app/public/build /var/www/html/public/build

FROM php-base AS application
COPY --from=build-assets /app/public/build ./public/build
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

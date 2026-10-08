# Ledger: one image, nginx + php-fpm under supervisord, SQLite on a volume.

# --- PHP dependencies ----------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- Front-end assets (Filament theme, self-hosted IBM Plex) ---------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
# The Filament theme imports its base CSS from vendor/.
COPY --from=vendor /app/vendor/filament ./vendor/filament
RUN npm run build

# --- Runtime -------------------------------------------------------------------
FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor su-exec icu-libs libzip libpng freetype libjpeg-turbo sqlite \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl zip gd opcache \
    && apk del .build-deps \
    && rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ledger.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/ledger-entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN php artisan package:discover --ansi \
    && php artisan filament:assets \
    && rm -rf storage/app/reports \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    LEDGER_REPORTS_PATH=/data/reports

VOLUME ["/data"]
EXPOSE 8080

ENTRYPOINT ["ledger-entrypoint"]

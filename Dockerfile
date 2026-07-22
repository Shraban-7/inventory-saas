# syntax=docker/dockerfile:1.7

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


FROM composer:2 AS vendor

WORKDIR /app

RUN apk add --no-cache icu-dev libzip-dev \
    && docker-php-ext-install intl zip

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction


FROM php:8.3-fpm-bookworm AS app

ARG APP_USER=www-data
ARG APP_UID=33
ARG APP_GID=33

ENV APP_HOME=/var/www/html \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

WORKDIR ${APP_HOME}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        nginx \
        $PHPIZE_DEPS \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        posix \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-inventory.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-inventory.conf
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint

RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && sed -i 's/^user .*/user www-data;/' /etc/nginx/nginx.conf \
    && chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R ${APP_USER}:${APP_USER} ${APP_HOME} /var/lib/nginx /var/log/nginx \
    && chmod -R ug+rwx storage bootstrap/cache

COPY --from=vendor --chown=${APP_USER}:${APP_USER} /app/vendor ./vendor
COPY --chown=${APP_USER}:${APP_USER} . .
COPY --from=frontend --chown=${APP_USER}:${APP_USER} /app/public/build ./public/build

RUN rm -rf docker/mysql docker/proxysql tests/load \
    && php -r "file_exists('vendor/autoload.php') || throw new RuntimeException('Composer autoload missing');"

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["web"]

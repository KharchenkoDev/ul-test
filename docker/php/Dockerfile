ARG PHP_VERSION=8.3

# =============================================================
# BASE — общие расширения и конфиги
# =============================================================
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2.4 \
    /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    mbstring \
    xml \
    curl \
    zip \
    bcmath \
    opcache \
    pcntl \
    intl

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache $PHPIZE_DEPS

COPY docker/php/php.ini      /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

WORKDIR /var/www/html

# =============================================================
# DEV — Xdebug + Composer с dev-зависимостями
# =============================================================
FROM base AS dev

RUN apk add --no-cache autoconf gcc g++ make linux-headers \
    && install-php-extensions xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY src/composer.json src/composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY src/ .

RUN composer dump-autoload --optimize

# =============================================================
# PROD — без Xdebug, только prod-зависимости
# =============================================================
FROM base AS prod

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY src/composer.json src/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader

COPY src/ .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm /usr/bin/composer

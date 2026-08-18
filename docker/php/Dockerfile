FROM php:8.4-fpm-alpine

# UID/GID хоста, чтобы файлы, созданные внутри контейнера (кэш, миграции,
# сгенерированные бандлами классы), принадлежали тебе, а не root.
ARG UID=1000
ARG GID=1000

# Рантайм: git/unzip нужны composer'у, shadow даёт usermod (в busybox его нет).
# icu-data-full — полные данные локалей: по умолчанию alpine ставит icu-data-en,
# и тогда intl молча форматирует даты по-английски даже для локали ru_RU.
# icu-libs указан явно: иначе он уедет вместе с icu-dev при `apk del .build-deps`.
#
# Второй apk add — всё, что нужно только на время компиляции расширений:
# отдельной виртуальной группой, чтобы снести одной командой и не тащить
# компилятор с заголовками в готовый образ.
RUN apk add --no-cache git unzip shadow icu-libs icu-data-full \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl opcache \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl clear-cache \
    && apk del .build-deps

# www-data в alpine имеет uid 82 — переселяем его на uid/gid хоста.
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www/app

# Кэш composer в volume — чтобы `composer install` не качал всё заново.
ENV COMPOSER_HOME=/var/www/.composer
RUN mkdir -p "$COMPOSER_HOME" && chown www-data:www-data /var/www/.composer

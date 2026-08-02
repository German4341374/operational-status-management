# syntax=docker/dockerfile:1.12
FROM composer:2.10.2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative \
    --ignore-platform-req=ext-pdo_pgsql

FROM php:8.5.9-fpm-alpine3.23 AS runtime
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && apk del .build-deps \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY bin ./bin
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-application.ini"
COPY --chmod=755 docker/app-entrypoint.sh /usr/local/bin/app-entrypoint

RUN mkdir -p var/cache/public var/cache/rate-limit var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod 0700 var/sessions

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=5 CMD php-fpm -t || exit 1
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]

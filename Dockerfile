FROM php:8.3-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apk add --no-cache git unzip icu-dev libzip-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring intl opcache zip dom xml xmlwriter
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
EXPOSE 9000

FROM base AS development
CMD ["php-fpm"]

FROM base AS production
ENV APP_ENV=prod APP_DEBUG=0
COPY symfony/composer.json symfony/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-progress --no-interaction
COPY symfony/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile --env=prod \
    && rm -rf var/cache \
    && mkdir -p var/cache var/log var/company_logos \
    && chown -R www-data:www-data var
COPY --chmod=755 docker/php/production-entrypoint.sh /usr/local/bin/app-entrypoint
USER www-data
HEALTHCHECK --interval=10s --timeout=3s --start-period=15s --retries=3 CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine AS web
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

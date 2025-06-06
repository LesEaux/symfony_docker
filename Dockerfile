############################################
# IMAGE PHP-FPM 8.3 (Alpine)                #
############################################
FROM php:8.3-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    SYMFONY_ENV=prod

# 1) Installer les paquets système nécessaires
RUN apk add --no-cache \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev \
    zip \
    icu-dev \
    libzip-dev \
    zlib-dev \
    curl

# 2) Installer les extensions PHP requises
RUN docker-php-ext-install pdo_mysql mbstring intl opcache zip

# 3) Installer Composer globalement
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4) Définir le working directory
WORKDIR /var/www/html

# 5) Copier composer.json & composer.lock pour profiter du cache Docker
COPY composer.json composer.lock ./

# 6) Installer les dépendances PHP SANS exécuter les scripts (pour éviter l’erreur bin/console)
RUN composer install --no-dev --optimize-autoloader --no-progress --no-scripts

# 7) Copier tout le code (src/, config/, public/, bin/, …)
COPY . .

# 8) Exécuter manuellement les scripts Symfony qui étaient bloqués (vidage du cache, installation des assets, etc.)
RUN php bin/console cache:clear --no-warmup --env=prod && \
    php bin/console cache:warmup --env=prod

# 9) Ajuster les droits sur var/ (c’est important pour Symfony)
RUN chown -R www-data:www-data /var/www/html/var

EXPOSE 9000
CMD ["php-fpm"]

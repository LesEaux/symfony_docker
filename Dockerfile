############################################
# 1. Image PHP-FPM 8.3 (basée sur Alpine)  #
############################################
FROM php:8.3-fpm-alpine

# Environnement, réglages
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    SYMFONY_ENV=prod

# Installer dépendances système
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

# Installer extensions PHP nécessaires
RUN docker-php-ext-install pdo_mysql mbstring intl opcache zip

# Installer Composer globalement
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier uniquement composer.json & composer.lock (pour tirer parti du build cache)
COPY composer.json composer.lock ./

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-progress

# Copier le reste du code
COPY . .

# Droits sur le dossier var
RUN chown -R www-data:www-data /var/www/html/var

# Exposer le port utilisé pour PHP-FPM
EXPOSE 9000

# Lancer PHP-FPM
CMD ["php-fpm"]

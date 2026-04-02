# Dockerfile pour PHP-FPM 8.4
# Image de base officielle PHP 8.4 avec FPM
FROM php:8.4-fpm

# Installation des extensions PHP nécessaires pour Symfony + PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définition du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers de dépendances en premier (optimisation cache Docker)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Copie du reste du code source
COPY . .

# Génération de l'autoloader optimisé
RUN composer dump-autoload --optimize

# Permissions pour le dossier var/ (cache et logs Symfony)
RUN chown -R www-data:www-data var/

EXPOSE 9000
CMD ["php-fpm"]

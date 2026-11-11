# STAGE 1: Download dependensi PHP via Composer
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

# STAGE 2: PHP-FPM Runtime (PHP 8.4 kompatibel dengan ^8.3 di composer.json)
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Install dependensi sistem & ekstensi PostgreSQL untuk Laravel
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath

# Copy vendor dari Stage 1
COPY --from=vendor /app/vendor ./vendor

# Copy semua file proyek Laravel
COPY . .

# Set hak akses folder storage dan cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

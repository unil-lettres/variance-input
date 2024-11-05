FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/app

RUN mkdir -p /var/www/html/app/storage \
    && mkdir -p /var/www/html/app/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/app \
    && chmod -R 775 /var/www/html/app/storage /var/www/html/app/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

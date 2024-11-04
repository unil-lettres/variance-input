# Use an official PHP image as the base
FROM php:8.2-fpm

# Install system dependencies and enable PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory to the Laravel app folder
WORKDIR /var/www/html/app

# Set permissions for Laravel storage and bootstrap/cache directories
RUN mkdir -p /var/www/html/app/storage \
    && mkdir -p /var/www/html/app/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/app \
    && chmod -R 775 /var/www/html/app/storage /var/www/html/app/bootstrap/cache

# Expose port 8000 for Laravel's built-in server
EXPOSE 8000

# Run Laravel's built-in server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

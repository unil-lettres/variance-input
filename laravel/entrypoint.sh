#!/bin/bash
set -e

# 1) Laravel environment setup
# Copy .env if missing
if [ ! -f "/var/www/html/.env" ]; then
  cp "/var/www/html/.env.example" "/var/www/html/.env"
fi

# Create and permission storage directories
mkdir -p /var/www/html/storage/app \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775      /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure uploads directory exists and set permissions
mkdir -p /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/public/uploads
chmod -R 775      /var/www/html/public/uploads

# 2) Install PHP dependencies and run artisan tasks
cd /var/www/html
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:clear
php artisan key:generate --ansi --force
php artisan migrate --force

# 3) Start the Laravel development server
exec "$@"

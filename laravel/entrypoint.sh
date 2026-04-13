#!/bin/bash
set -e

# 1) Laravel environment setup
# Copy .env if missing
if [ ! -f "/var/www/html/.env" ]; then
  cp "/var/www/html/.env.example" "/var/www/html/.env"
fi

# Create and permission storage directories
mkdir -p /var/www/html/storage/app \
         /var/www/html/storage/framework \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

for path in \
  /var/www/html/storage \
  /var/www/html/storage/app \
  /var/www/html/storage/framework \
  /var/www/html/storage/framework/cache \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache
do
  chown www-data:www-data "$path"
  chmod 775 "$path"
done

# Ensure uploads directory exists and set permissions
mkdir -p /var/www/html/public/uploads
chown www-data:www-data /var/www/html/public/uploads
chmod 775 /var/www/html/public/uploads

# Configure PHP upload limits (useful when running artisan serve)
cat <<'PHPINI' > /usr/local/etc/php/conf.d/uploads.ini
; Increased limits for large facsimile uploads
upload_max_filesize = 256M
post_max_size = 300M
max_file_uploads = 300
PHPINI

# 2) Install PHP dependencies and run artisan tasks
cd /var/www/html
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  composer install --no-dev --optimize-autoloader
fi
if ! grep -Eq '^APP_KEY=.+$' /var/www/html/.env; then
  php artisan key:generate --ansi --force
fi

if [ "${LARAVEL_RUN_MIGRATIONS_ON_BOOT:-false}" = "true" ]; then
  php artisan migrate --force
fi

# 3) Start the Laravel development server
exec "$@"

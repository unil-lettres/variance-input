############################################################
#  Laravel PHP-FPM + Node 18 image, ready for MariaDB
############################################################
FROM php:8.2-fpm

# 1) System packages + Node + PHP extensions
RUN apt-get update && apt-get install -y \
      git unzip curl mariadb-client \
      libzip-dev libjpeg-dev libpng-dev libfreetype6-dev \
      libmagickwand-dev imagemagick \
  # Node 18.x
  && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
  && apt-get install -y nodejs \
  # PHP GD + Zip + MySQL drivers
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd zip pdo pdo_mysql \
  && pecl install imagick \
  && docker-php-ext-enable imagick

# 2) Composer (copied from official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) Project files -----------------------------------------
WORKDIR /var/www/html

# copy .env early so composer scripts using env vars work
COPY example.env .env

# 4) PHP dependencies (no scripts yet – artisan not present)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# 5) Node dependencies
COPY package*.json ./
RUN npm install

# 6) Copy full source + entrypoint
COPY . .
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 7) Composer post-install scripts, package discovery
RUN composer dump-autoload --optimize \
 && php artisan package:discover --ansi

# 8) Build Vite / front-end assets
RUN npm run build

# 9) Storage link
RUN php artisan storage:link

# 10) Serve application
EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

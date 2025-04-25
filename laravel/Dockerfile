FROM php:8.2-fpm

# 1) Install system packages + Node + PHP GD extension
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    curl \
    # Node 18.x
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    # PHP extensions
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd

# 2) Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) Set working directory to /var/www/html
WORKDIR /var/www/html

# 4) Copy package files & install Node dependencies
COPY package*.json ./
RUN npm install

# 5) Copy entire Laravel app
COPY . .

# 6) Build front-end (Vite) if needed
RUN npm run build

# 7) Permissions, expose port, run Artisan server
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

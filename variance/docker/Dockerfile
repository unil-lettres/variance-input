FROM php:7.4-fpm

ENV COMPOSER_VERSION=2.5.8

# Update repositories
RUN apt-get update

# Install additional packages
RUN apt-get install -y \
    git \
    curl \
    nano \
    zip \
    unzip \
    supervisor \
    libcurl4-openssl-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev

# Install needed php extensions
RUN apt-get clean; docker-php-ext-install pdo pdo_mysql mysqli curl gettext mbstring intl zip

# Install specific version of Composer
RUN curl --silent --show-error https://getcomposer.org/installer | php -- \
    --$COMPOSER_VERSION \
    --install-dir=/usr/local/bin --filename=composer

# Copy supervisor configuration file
# docker exec <container-id> supervisorctl status
# docker exec <container-id> supervisorctl tail -f <service>
# docker exec <container-id> supervisorctl restart <service>
COPY ./config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

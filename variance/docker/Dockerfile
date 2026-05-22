FROM php:7.4-fpm

ENV COMPOSER_VERSION=2.9.8
ENV COMPOSER_SHA256=59b2c50e10cafa0d8efc19ede9a326d782f096c674a26baf98cf042ce23de890

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

# Install pinned Composer release and verify the downloaded PHAR.
RUN curl --fail --location --silent --show-error \
        "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
        --output /usr/local/bin/composer \
    && echo "${COMPOSER_SHA256}  /usr/local/bin/composer" | sha256sum -c - \
    && chmod +x /usr/local/bin/composer \
    && composer --version

# Copy supervisor configuration file
# docker exec <container-id> supervisorctl status
# docker exec <container-id> supervisorctl tail -f <service>
# docker exec <container-id> supervisorctl restart <service>
COPY ./config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

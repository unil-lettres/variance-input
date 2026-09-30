FROM php:7.4-fpm

ENV COMPOSER_VERSION=2.9.8
ENV COMPOSER_SHA256=59b2c50e10cafa0d8efc19ede9a326d782f096c674a26baf98cf042ce23de890

# Bullseye LTS ended in August 2026; the live security mirror now returns 404.
# Keep this compatibility build reproducible from the final signed snapshot.
# Only timestamp expiry is disabled for these frozen sources; signatures and
# package hashes remain verified. This does not extend PHP/Bullseye support.
ARG DEBIAN_SNAPSHOT=20260901T000000Z
RUN printf '%s\n' \
      "deb [check-valid-until=no] https://snapshot.debian.org/archive/debian/${DEBIAN_SNAPSHOT}/ bullseye main" \
      "deb [check-valid-until=no] https://snapshot.debian.org/archive/debian-security/${DEBIAN_SNAPSHOT}/ bullseye-security main" \
      > /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y \
    git \
    curl \
    nano \
    zip \
    unzip \
    supervisor \
    libcurl4-openssl-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

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

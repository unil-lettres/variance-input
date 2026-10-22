FROM php:7.4.33-fpm@sha256:3ac7c8c74b2b047c7cb273469d74fc0d59b857aa44043e6ea6a0084372811d5b AS legacy_base

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

# Reproduce the upstream minimal EOL mitigation used by PHP259.
# The supported-version upgrade remains separate; PHP_VERSION stays7.4.33.
FROM legacy_base AS php259_builder
USER root
RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf build-essential bison re2c pkg-config patch \
    libargon2-dev libcurl4-openssl-dev libsodium-dev libxml2-dev libonig-dev \
    libssl-dev libreadline-dev libsqlite3-dev zlib1g-dev libicu-dev libzip-dev
COPY php259/upstream-712e60f.patch /tmp/php259.patch
COPY php259/patched-files.sha256 /tmp/patched-files.sha256
RUN echo '924846abf93bc613815c55dd3f5809377813ac62a9ec4eb3778675b82a27b927  /usr/src/php.tar.xz' | sha256sum -c - \
 && echo '5fe57ca6ee96d59b36c6e0ca842843c50884f177bef1e85234cf669b577f5e48  /tmp/php259.patch' | sha256sum -c - \
 && mkdir /tmp/php-build \
 && tar -xJf /usr/src/php.tar.xz -C /tmp/php-build --strip-components=1
WORKDIR /tmp/php-build
RUN patch -p1 < /tmp/php259.patch \
 && sha256sum -c /tmp/patched-files.sha256 \
 && tar -cJf /tmp/patched-source.tar.xz .
RUN export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS" \
 && ./configure $(php-config --configure-options) --with-gettext=shared --enable-intl=shared --with-mysqli=shared,mysqlnd --with-pdo-mysql=shared,mysqlnd --with-zip=shared \
 && make -j2 && make install INSTALL_ROOT=/tmp/php-install

FROM legacy_base
RUN rm /usr/local/lib/php/extensions/no-debug-non-zts-20190902/*.so
COPY --from=php259_builder /tmp/php-install/usr/local/bin/ /usr/local/bin/
COPY --from=php259_builder /tmp/php-install/usr/local/sbin/ /usr/local/sbin/
COPY --from=php259_builder /tmp/php-install/usr/local/include/php/ /usr/local/include/php/
COPY --from=php259_builder /tmp/php-install/usr/local/lib/php/build/ /usr/local/lib/php/build/
COPY --from=php259_builder /tmp/php-install/usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php259_builder /tmp/patched-source.tar.xz /usr/src/php.tar.xz
COPY php259/patched-files.sha256 /usr/local/share/php259/patched-files.sha256
ENV PHP_SHA256= PHP_URL= PHP_ASC_URL=
LABEL ch.unil.ops.php-remediation="259" \
      ch.unil.ops.php-backport="712e60f9edfd03f0960fc4fc3c2c375f42b90aa2" \
      ch.unil.ops.php-support="EOL; upstream minimal mitigation only"
RUN sha256sum /usr/src/php.tar.xz > /usr/local/share/php259/source.sha256 \
 && php -r 'if (PHP_VERSION !== "7.4.33") { exit(1); } foreach (["curl", "gettext", "intl", "mbstring", "mysqli", "pdo_mysql", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: ".$extension."\n"); exit(1); } }' \
 && php-fpm -v && php-fpm -t

ARG BASE
FROM ${BASE} AS builder
USER root
RUN apt-get update && apt-get install -y --no-install-recommends autoconf build-essential bison re2c pkg-config libargon2-dev libcurl4-openssl-dev libsodium-dev libxml2-dev libonig-dev libssl-dev libreadline-dev libsqlite3-dev zlib1g-dev
COPY php-8.3.36.tar.xz /tmp/php-8.3.36.tar.xz
RUN echo 'dd54693aa334c5c4d72e9aee96b082c2473750a18a6c1d105789c93a3b1692fd  /tmp/php-8.3.36.tar.xz' | sha256sum -c - \
 && mkdir /tmp/php-build && tar -xJf /tmp/php-8.3.36.tar.xz -C /tmp/php-build --strip-components=1
WORKDIR /tmp/php-build
RUN export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS" \
 && ./configure $(php-config --configure-options) --enable-gd=shared --with-freetype --with-jpeg --with-zip=shared --with-pdo-mysql=shared,mysqlnd \
 && make -j2 \
 && make install INSTALL_ROOT=/tmp/php-install
FROM ${BASE}
COPY --from=builder /tmp/php-install/usr/local/bin/ /usr/local/bin/
COPY --from=builder /tmp/php-install/usr/local/sbin/ /usr/local/sbin/
COPY --from=builder /tmp/php-install/usr/local/include/php/ /usr/local/include/php/
COPY --from=builder /tmp/php-install/usr/local/lib/php/build/ /usr/local/lib/php/build/
COPY --from=builder /tmp/php-install/usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY php-8.3.36.tar.xz /usr/src/php.tar.xz
ENV PHP_VERSION=8.3.36 PHP_SHA256=dd54693aa334c5c4d72e9aee96b082c2473750a18a6c1d105789c93a3b1692fd PHP_URL=https://www.php.net/distributions/php-8.3.36.tar.xz PHP_ASC_URL=https://www.php.net/distributions/php-8.3.36.tar.xz.asc
LABEL ch.unil.ops.php-remediation="259" ch.unil.ops.php-source-sha256="dd54693aa334c5c4d72e9aee96b082c2473750a18a6c1d105789c93a3b1692fd"
RUN php -r 'exit(PHP_VERSION === "8.3.36" ? 0 : 1);' && php-fpm -v

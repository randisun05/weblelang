# syntax=docker/dockerfile:1
# check=skip=SecretsUsedInArgOrEnv
# Image produksi WebLelang: FrankenPHP (PHP 8.3 + Caddy, HTTPS otomatis).
# Satu image dipakai untuk semua peran: web, queue, scheduler (lihat docker/entrypoint.sh).

############################
# Basis runtime + ekstensi #
############################
FROM dunglas/frankenphp:1-php8.3-alpine AS base
# (Opsional) CA tambahan untuk build di balik proxy TLS — lihat docker/certs/README.md
COPY docker/certs/ /tmp/certs/
RUN cat /tmp/certs/*.crt >> /etc/ssl/certs/ca-certificates.crt 2>/dev/null || true

RUN install-php-extensions pdo_mysql gd intl zip pcntl opcache exif \
    && apk add --no-cache mariadb-client tzdata \
    && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-weblelang.ini
WORKDIR /app

#####################
# Dependensi PHP    #
#####################
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

#####################
# Aset frontend     #
#####################
FROM node:22-alpine AS assets
# (Opsional) CA tambahan untuk build di balik proxy TLS — lihat docker/certs/README.md
COPY docker/certs/ /tmp/certs/
RUN cat /tmp/certs/*.crt >> /etc/ssl/certs/ca-certificates.crt 2>/dev/null || true
ENV NODE_EXTRA_CA_CERTS=/etc/ssl/certs/ca-certificates.crt
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
# Ziggy (route() di Vue) diimpor dari vendor PHP.
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
# Variabel VITE_* dibaca saat build (mis. konfigurasi websocket).
ARG VITE_APP_NAME=WebLelang
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
RUN npm run build

#####################
# Image final       #
#####################
FROM base AS app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm -f /usr/bin/composer .env \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:80

EXPOSE 80 443 443/udp
ENTRYPOINT ["entrypoint"]
CMD ["web"]

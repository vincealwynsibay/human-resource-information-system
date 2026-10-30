# Production image: PHP-FPM and nginx (serversideup/php), the built front end, and Chromium for the PDF downloads.
# Build:  docker build -t talia .        Run it with deploy/docker-compose.yml.

# 1. Front end. Puppeteer stays (Browsershot drives it at runtime) but uses the system Chromium, so no download.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources resources
RUN npx vite build && npm prune --omit=dev

# 2. PHP dependencies, without the development tools.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 3. Runtime.
FROM serversideup/php:8.4-fpm-nginx-bookworm
USER root
RUN install-php-extensions gd intl zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends chromium default-mysql-client fonts-noto-core \
    && rm -rf /var/lib/apt/lists/*
COPY --from=assets /usr/local/bin/node /usr/local/bin/node

ENV LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium \
    LARAVEL_PDF_NODE_BINARY=/usr/local/bin/node \
    LARAVEL_PDF_NODE_MODULES_PATH=/var/www/html/node_modules \
    LARAVEL_PDF_NO_SANDBOX=true \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    PHP_OPCACHE_ENABLE=1

WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build public/build
COPY --chown=www-data:www-data --from=assets /app/node_modules node_modules
RUN php artisan package:discover --ansi && chown -R www-data:www-data bootstrap/cache storage

USER www-data

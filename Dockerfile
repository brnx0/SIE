# syntax=docker/dockerfile:1.7

###############################
# Stage 1 - vendor (Composer) #
###############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# sem scripts: artisan ainda nao tem app completa aqui.
# --ignore-platform-reqs: imagem composer nao tem gd/pgsql, mas o runtime
# (php:8.3-fpm) tem. So resolvemos deps aqui; extensoes reais existem la.
RUN composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --no-scripts \
      --ignore-platform-reqs \
      --optimize-autoloader

############################
# Stage 2 - assets (Vite) #
############################
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
# usa lockfile se existir; senao install normal
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY . .
# app.ts importa de vendor/tightenco/ziggy -> traz do stage vendor antes do build
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

#########################
# Stage 3 - runtime PHP #
#########################
FROM php:8.3-fpm-bookworm AS runtime

# Extensoes: pgsql (db), gd (mpdf), mbstring/bcmath/intl/zip + opcache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        locales \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip intl bcmath opcache \
    && sed -i '/pt_BR.UTF-8/s/^# //' /etc/locale.gen && locale-gen \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV LANG=pt_BR.UTF-8 LC_ALL=pt_BR.UTF-8

# Config PHP (opcache + uploads)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# App + deps + assets buildados
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Copia canonica do public (assets buildados) - usada p/ re-sync no boot,
# garantindo que o volume compartilhado com nginx nunca fique com asset velho.
RUN cp -r public /opt/public-dist

# Permissoes storage/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

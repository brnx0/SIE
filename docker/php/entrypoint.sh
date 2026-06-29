#!/usr/bin/env bash
set -e

# Roda so para o processo principal (php-fpm), nao p/ worker/scheduler
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Preparando app..."

    # Re-sync public/ p/ o volume compartilhado com nginx (evita asset velho).
    if [ -d /opt/public-dist ]; then
        cp -ru /opt/public-dist/. /var/www/html/public/
    fi

    # storage link (ignora se ja existe)
    php artisan storage:link 2>/dev/null || true

    # Migrations (controlavel via env; default ON em homolog)
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        echo "[entrypoint] Rodando migrations..."
        php artisan migrate --force
    fi

    # Caches de produo
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache 2>/dev/null || true

    echo "[entrypoint] Pronto."
fi

exec "$@"

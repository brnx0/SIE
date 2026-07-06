#!/usr/bin/env bash
set -e

# Roda so para o processo principal (php-fpm), nao p/ worker/scheduler
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Preparando app..."

    # Re-sync public/ p/ o volume compartilhado com nginx (evita asset velho).
    if [ -d /opt/public-dist ]; then
        cp -ru /opt/public-dist/. /var/www/html/public/
    fi

    # NAO cria public/storage aqui: storage/app fica em VOLUME do container e o
    # public e bind-mount do host, entao o symlink apontaria p/ um caminho que o
    # nginx do host nao alcanca (403). Remove qualquer symlink antigo p/ o nginx
    # cair no index.php -> rota PublicStorageController serve o arquivo do volume.
    rm -f /var/www/html/public/storage 2>/dev/null || true

    # Migrations: default OFF (prod = controlado no deploy). Ligue RUN_MIGRATIONS=true
    # so se quiser migrar no boot. Fluxo prod recomendado (manual, antes do up):
    #   docker compose -f docker-compose.app.yml run --rm app php artisan migrate --force
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
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

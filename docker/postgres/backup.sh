#!/usr/bin/env bash
# Backup do Postgres por municipio (pg_dump + gzip + rotacao).
# Roda no HOST via cron. Le credenciais do .env do projeto.
#
# Crontab exemplo (diario 02:30, log em /var/log/sie_backup.log):
#   30 2 * * * cd /opt/sie_live_hom && ./docker/postgres/backup.sh >> /var/log/sie_backup.log 2>&1
#
# Vars opcionais (.env): BACKUP_DIR (default /opt/backups/<municipio>), BACKUP_KEEP_DAYS (default 7)
set -euo pipefail

# raiz do projeto (2 niveis acima deste script)
cd "$(dirname "$0")/../.."

# carrega .env
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

MUNICIPIO="${MUNICIPIO:-homolog}"
# Instancia UNICA compartilhada. Rodado no dir de cada municipio, faz backup do
# banco daquele municipio (DB_DATABASE do .env local).
CONTAINER="sie_pg"
DB="${DB_DATABASE:-sie_auth}"
DB_USER="${DB_USERNAME:-sie_homolog}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"
DEST="${BACKUP_DIR:-/opt/backups/${MUNICIPIO}}"

mkdir -p "$DEST"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="${DEST}/${DB}_${STAMP}.sql.gz"

echo "[$(date '+%F %T')] backup ${CONTAINER} -> ${FILE}"

# --clean --if-exists: restore dropa objetos antes (idempotente). --no-owner: portavel.
docker exec "$CONTAINER" pg_dump -U "$DB_USER" -d "$DB" --no-owner --clean --if-exists \
    | gzip -9 > "$FILE"

# valida dump nao-vazio
if [ ! -s "$FILE" ]; then
    echo "[$(date '+%F %T')] ERRO: dump vazio, removendo"
    rm -f "$FILE"
    exit 1
fi

# rotacao: apaga backups mais velhos que KEEP_DAYS
find "$DEST" -name "${DB}_*.sql.gz" -type f -mtime +"${KEEP_DAYS}" -delete

echo "[$(date '+%F %T')] OK ($(du -h "$FILE" | cut -f1)) | mantendo ${KEEP_DAYS} dias"

#!/usr/bin/env bash
# Restore de um backup gerado por backup.sh.
# USO:  ./docker/postgres/restore.sh /opt/backups/coruripe/sie_coruripe_20260630_023000.sql.gz
#
# ATENCAO: sobrescreve o banco atual (o dump usa --clean --if-exists).
set -euo pipefail

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
    echo "USO: $0 <arquivo.sql.gz>"
    exit 1
fi

cd "$(dirname "$0")/../.."
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

MUNICIPIO="${MUNICIPIO:-homolog}"
CONTAINER="sie_pg"
DB="${DB_DATABASE:-sie_auth}"
DB_USER="${DB_USERNAME:-sie_homolog}"

echo "ATENCAO: vai SOBRESCREVER o banco '${DB}' no container '${CONTAINER}'."
read -r -p "Digite 'CONFIRMO' para prosseguir: " ok
[ "$ok" = "CONFIRMO" ] || { echo "abortado"; exit 1; }

echo "[$(date '+%F %T')] restaurando ${FILE} -> ${CONTAINER}/${DB}"
gunzip -c "$FILE" | docker exec -i "$CONTAINER" psql -U "$DB_USER" -d "$DB"
echo "[$(date '+%F %T')] restore concluido"

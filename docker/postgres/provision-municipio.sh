#!/usr/bin/env bash
# Provisiona um municipio na instancia UNICA de Postgres (sie_pg): cria a role
# dona e o banco isolado. Idempotente — rodar de novo nao altera nada existente
# (exceto que re-sincroniza a senha da role e as permissoes de isolamento).
#
# USO:  bash ./docker/postgres/provision-municipio.sh <db_name> <db_user> <db_password>
# Ex.:  bash ./docker/postgres/provision-municipio.sh sie_coruripe sie_coruripe 'S3nh4F0rte'
#
# Le o superusuario do .env do diretorio da instancia:
#   PG_SUPERUSER (default postgres), PG_SUPERUSER_PASSWORD (obrigatorio)
#
# Isolamento: a role e dona SO do seu banco; REVOKE CONNECT de PUBLIC impede que
# um municipio enxergue o banco de outro. A senha entra por variavel psql
# (quote_literal), nunca interpolada direto no SQL.
set -euo pipefail

DB_NAME="${1:-}"
DB_USER="${2:-}"
DB_PASS="${3:-}"

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "USO: $0 <db_name> <db_user> <db_password>"
    exit 1
fi

# raiz do projeto (2 niveis acima deste script) para achar o .env da instancia
cd "$(dirname "$0")/../.."
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

CONTAINER="sie_pg"
SUPER="${PG_SUPERUSER:-postgres}"
SUPER_PASS="${PG_SUPERUSER_PASSWORD:?defina PG_SUPERUSER_PASSWORD no .env}"

# psql como superusuario dentro do container. -v ON_ERROR_STOP para abortar no 1o erro.
run_psql() {
    docker exec -i -e PGPASSWORD="$SUPER_PASS" "$CONTAINER" \
        psql -U "$SUPER" -v ON_ERROR_STOP=1 "$@"
}

echo "[$(date '+%F %T')] provisionando municipio: banco='${DB_NAME}' role='${DB_USER}'"

# 1) Role + banco no contexto administrativo (db 'postgres').
#    \gexec roda o comando gerado so quando o WHERE NOT EXISTS retorna linha.
run_psql -d postgres \
    -v usr="$DB_USER" -v pwd="$DB_PASS" -v db="$DB_NAME" <<'SQL'
-- Cria a role apenas se ainda nao existir (senha via quote_literal).
SELECT 'CREATE ROLE ' || quote_ident(:'usr') || ' LOGIN PASSWORD ' || quote_literal(:'pwd')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'usr')
\gexec

-- Se a role ja existia, re-sincroniza a senha com o que foi passado.
SELECT 'ALTER ROLE ' || quote_ident(:'usr') || ' LOGIN PASSWORD ' || quote_literal(:'pwd')
WHERE EXISTS (SELECT FROM pg_roles WHERE rolname = :'usr')
\gexec

-- Cria o banco (dono = role) apenas se ainda nao existir. Herda a collation
-- ICU pt-BR do template0; TEMPLATE template0 exigido ao fixar locale.
SELECT 'CREATE DATABASE ' || quote_ident(:'db')
    || ' OWNER ' || quote_ident(:'usr')
    || ' ENCODING ''UTF8'' TEMPLATE template0'
    || ' LOCALE_PROVIDER icu ICU_LOCALE ''pt-BR'' LOCALE ''C.UTF-8'''
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db')
\gexec

-- Isolamento: so a role dona conecta neste banco.
REVOKE CONNECT ON DATABASE :"db" FROM PUBLIC;
GRANT ALL ON DATABASE :"db" TO :"usr";
SQL

# 2) Dono do schema public = role do municipio, para as migrations do Laravel
#    criarem tabelas sem GRANT extra (PG15+ nao da CREATE em public p/ PUBLIC).
run_psql -d "$DB_NAME" -v usr="$DB_USER" <<'SQL'
ALTER SCHEMA public OWNER TO :"usr";
GRANT ALL ON SCHEMA public TO :"usr";
SQL

echo "[$(date '+%F %T')] OK — municipio '${DB_NAME}' pronto e isolado."

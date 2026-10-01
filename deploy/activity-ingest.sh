#!/usr/bin/env bash
# For every active client in cidery_host, ingest pending activity rows into that
# client's MaluDB memory schema. Reads credentials from config/services.env.
set -euo pipefail
HERE="$(cd "$(dirname "$0")/.." && pwd)"
set -a; . "$HERE/config/services.env"; set +a
export PGHOST="$CIDERY_DB_HOST" PGPORT="$CIDERY_DB_PORT" PGUSER="$CIDERY_APP_DB_USER" PGPASSWORD="$CIDERY_APP_DB_PASSWORD"
for db in $(psql -d cidery_host -Atc "select db_name from clients where status='active'"); do
    total=0
    while :; do
        n=$(psql -d "$db" -Atc "select app.activity_ingest_pending(500)")
        total=$((total + n))
        [ "$n" -lt 500 ] && break
    done
    [ "$total" != "0" ] && echo "$db: ingested $total activity rows"
done
exit 0

#!/usr/bin/env bash
# For every active client in cidery_host, ingest pending activity rows into
# that client's MaluDB memory schema. Connects as cidery_app using PGPASSWORD
# from /etc/cidery/cidery.env (CIDERY_DB_PASSWORD) or peer auth when run locally.
set -euo pipefail
export PGUSER="${CIDERY_DB_USER:-cidery_app}"
export PGPASSWORD="${CIDERY_DB_PASSWORD:-}"
export PGHOST="${CIDERY_DB_HOST:-/var/run/postgresql}"
for db in $(psql -d cidery_host -Atc "select db_name from clients where status='active'"); do
    n=$(psql -d "$db" -Atc "select app.activity_ingest_pending(2000)")
    [ "$n" != "0" ] && echo "$db: ingested $n activity rows"
done
exit 0

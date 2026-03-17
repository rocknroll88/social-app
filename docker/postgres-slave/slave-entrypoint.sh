#!/bin/sh
set -eu

PGDATA="${PGDATA:-/var/lib/postgresql/data}"
PRIMARY_HOST="${PRIMARY_HOST:-db}"
REPLICATION_USER="${REPLICATION_USER:-replicator}"

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    rm -rf "$PGDATA"/*

    until pg_isready -h "$PRIMARY_HOST" -p 5432 -U postgres >/dev/null 2>&1; do
        sleep 1
    done

    pg_basebackup \
        -h "$PRIMARY_HOST" \
        -D "$PGDATA" \
        -U "$REPLICATION_USER" \
        -Fp \
        -Xs \
        -P \
        -R

    chmod 0700 "$PGDATA"
fi

exec docker-entrypoint.sh postgres -c hot_standby=on

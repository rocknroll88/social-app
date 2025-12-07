#!/bin/bash
set -e

echo ">> Configuring pg_hba.conf for coordinator access"

# Добавляем доступ координатору
echo "host all all all md5" >> "$PGDATA/pg_hba.conf"
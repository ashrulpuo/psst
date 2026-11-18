#!/usr/bin/env bash
# Restore an encrypted backup made by `php artisan psst:backup`.
#
# Usage: BACKUP_PASSPHRASE=... DB_DATABASE=psst DB_USERNAME=... DB_PASSWORD=... DB_HOST=... \
#        scripts/restore-backup.sh [--force] <psst-YYYYMMDD-HHMMSS.sql.gz.enc>
#
# Refuses to restore into a database that already has tables unless --force is given.
set -euo pipefail

force=0
file=""
for arg in "$@"; do
  case "$arg" in
    --force) force=1 ;;
    -*) echo "unknown option: $arg" >&2; exit 2 ;;
    *) file="$arg" ;;
  esac
done

if [[ -z "$file" || ! -f "$file" ]]; then
  echo "usage: $0 [--force] <backup-file.sql.gz.enc>" >&2
  exit 2
fi
: "${BACKUP_PASSPHRASE:?BACKUP_PASSPHRASE must be set}"
: "${DB_DATABASE:?DB_DATABASE must be set}"

export PGHOST="${DB_HOST:-127.0.0.1}"
export PGPORT="${DB_PORT:-5432}"
export PGUSER="${DB_USERNAME:-psst}"
export PGPASSWORD="${DB_PASSWORD:-}"
export PGDATABASE="$DB_DATABASE"

tables="$(psql -XAtq -c "select count(*) from information_schema.tables where table_schema = 'public'")"
if [[ "$tables" != "0" && "$force" -ne 1 ]]; then
  echo "refusing to restore: database '$DB_DATABASE' already has $tables tables (use --force to override)" >&2
  exit 1
fi

openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in "$file" \
  | gunzip \
  | psql -X -q --single-transaction -v ON_ERROR_STOP=1

echo "restored $file into $DB_DATABASE"

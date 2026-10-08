#!/usr/bin/env bash
# Applies migrations/*.sql in lexical order with the mysql client.
# Every migration MUST be idempotent (CREATE TABLE IF NOT EXISTS; guarded ALTERs); there is no bookkeeping table.
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
source scripts/lib/dbcred.sh
load_db_credentials
defaults="$(mktemp)"
trap 'rm -f "$defaults"' EXIT
write_mysql_defaults "$defaults"
for f in migrations/*.sql; do
  run_mysql "$defaults" "$DB_NAME" < "$f" || exit 1
  echo "applied $f"
done

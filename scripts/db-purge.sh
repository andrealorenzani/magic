#!/usr/bin/env bash
# Manual use only: deletes audit rows older than N days; person rows go with them (ON DELETE CASCADE).
# Nothing runs this automatically.
# Usage: scripts/db-purge.sh --days N   (N: 1 to 99999)
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
DAYS=""
if [ "${1:-}" = "--days" ]; then DAYS="${2:-}"; fi
[ $# -eq 2 ] && [ -n "$DAYS" ] || { echo "usage: db-purge.sh --days N" >&2; exit 2; }
[[ $DAYS =~ ^[0-9]{1,5}$ ]] && [ "$((10#$DAYS))" -ge 1 ] || { echo "error: --days needs a whole number from 1 to 99999" >&2; exit 2; }
DAYS=$((10#$DAYS))
source scripts/lib/dbcred.sh
load_db_credentials
defaults="$(mktemp)"
trap 'rm -f "$defaults"' EXIT
write_mysql_defaults "$defaults"
run_mysql "$defaults" "$DB_NAME" \
  -e "DELETE FROM magic_audit WHERE created_at < UTC_TIMESTAMP() - INTERVAL ${DAYS} DAY" || exit 1
echo "purged audit rows older than ${DAYS} days"

#!/usr/bin/env bash
# Work with the database of the docker-compose setup. The password stays inside the container.
# Usage: scripts/docker-db.sh migrate | shell | query "SQL" | audit [N] | reset
set -euo pipefail
cd "$(dirname "$0")/.."
# Runs the mysql client inside the db container, reading user/password/database from its own environment.
run() { docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE" "$@"' sh "$@"; }
case "${1:-}" in
  migrate) for f in migrations/*.sql; do run < "$f"; echo "applied $f"; done ;;
  shell) docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' ;;
  query) [ -n "${2:-}" ] || { echo "usage: $0 query \"SQL\"" >&2; exit 2; }; printf '%s;\n' "${2%;}" | run -t ;;
  audit) n="${2:-10}"; [[ $n =~ ^[0-9]{1,4}$ ]] || { echo "N must be a number" >&2; exit 2; }
         printf 'SELECT a.id, a.created_at, a.functionality, a.on_date, GROUP_CONCAT(p.name ORDER BY p.role SEPARATOR " / ") AS people FROM magic_audit a LEFT JOIN magic_audit_person p ON p.audit_id = a.id GROUP BY a.id ORDER BY a.id DESC LIMIT %d;\n' "$n" | run -t ;;
  reset) docker compose down -v && docker compose up -d --build ;;
  *) echo "usage: $0 migrate | shell | query \"SQL\" | audit [N] | reset" >&2; exit 2 ;;
esac

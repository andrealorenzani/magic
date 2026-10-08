#!/usr/bin/env bash
# Sourced helper. load_db_credentials sets DB_HOST DB_PORT DB_NAME DB_USER DB_SECRET.
# It never prints anything and never reads values onto a command line.
# The section of ~/.password comes from DB_PASSWORD_SECTION in the gitignored .deploy.local.
# Keys read: dbname, dbuser, dbpass; optional host, port (host defaults to the section name, port to 3306).
load_db_credentials() {
  local root; root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
  [ -f "$root/.deploy.local" ] && source "$root/.deploy.local"
  [ -n "${DB_PASSWORD_SECTION:-}" ] || { echo "error: database section not configured (.deploy.local)" >&2; return 2; }
  local pwfile="$HOME/.password" out
  [ -f "$pwfile" ] || { echo "error: credentials file missing" >&2; return 2; }
  out="$(DB_SECTION="$DB_PASSWORD_SECTION" PWFILE="$pwfile" python3 - <<'PY'
import configparser, os, shlex, sys
# Never print exception text: a parse error message contains the offending line of the file.
try:
    cp = configparser.ConfigParser(interpolation=None)
    cp.read(os.environ["PWFILE"])
    sec = os.environ["DB_SECTION"]
    if not cp.has_section(sec):
        sys.exit(3)
    s = cp[sec]
    name, user, pw = s.get("dbname"), s.get("dbuser"), s.get("dbpass")
    if not name or not user or pw is None:
        sys.exit(4)
    vals = {"DB_HOST": s.get("host") or sec, "DB_PORT": s.get("port") or "3306", "DB_NAME": name, "DB_USER": user, "DB_SECRET": pw}
    lines = [f"{k}={shlex.quote(v)}" for k, v in vals.items()]
except SystemExit:
    raise
except BaseException:
    sys.exit(5)
print("\n".join(lines))
PY
  )" 2>/dev/null || { echo "error: database credentials incomplete or section missing" >&2; return 2; }
  eval "$out"
  out=""
}

# write_mysql_defaults FILE: option file for the mysql client (keeps the password off the command line).
write_mysql_defaults() {
  local f="$1" q
  q() { local s=${1//\\/\\\\}; s=${s//\"/\\\"}; printf '"%s"' "$s"; }
  printf '[client]\n' > "$f"
  printf 'host=%s\n' "$(q "$DB_HOST")" >> "$f"
  printf 'port=%s\n' "$DB_PORT" >> "$f"
  printf 'user=%s\n' "$(q "$DB_USER")" >> "$f"
  printf 'password=%s\n' "$(q "$DB_SECRET")" >> "$f"
}

# run_mysql DEFAULTS_FILE [mysql args...]: runs the mysql client (stdin passes through) and never shows its
# stderr, which can contain the user name and client address. On failure prints only a generic message with the
# numeric error code and SQLSTATE, and returns non-zero.
run_mysql() {
  local defaults="$1"; shift
  local errf rc=0 text code state msg
  errf="$(mktemp)"
  mysql --defaults-extra-file="$defaults" --default-character-set=utf8mb4 "$@" 2>"$errf" || rc=$?
  if [ "$rc" -ne 0 ]; then
    text="$(head -c 4096 "$errf" 2>/dev/null || true)"
    code=""; state=""
    if [[ $text =~ ERROR[[:space:]]+([0-9]+)([[:space:]]+\(([0-9A-Za-z]{5})\))? ]]; then
      code="${BASH_REMATCH[1]}"; state="${BASH_REMATCH[3]:-}"
    fi
    case "$code" in
      1045) msg="access denied" ;;
      1044) msg="access denied to database" ;;
      1049) msg="unknown database" ;;
      2002|2003|2005|2013) msg="cannot connect to the server" ;;
      "") msg="mysql failed" ;;
      *) msg="statement failed" ;;
    esac
    if [ -n "$code" ]; then
      echo "mysql error $code${state:+ ($state)}: $msg" >&2
    else
      echo "mysql error: $msg" >&2
    fi
    rm -f "$errf"
    return 1
  fi
  rm -f "$errf"
}

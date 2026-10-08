#!/usr/bin/env bash
# Upload committed changes to the server through the sftp-upload skill's uploader.
# Config: .deploy.local (gitignored; see .deploy.local.example). State: .deploy-state (gitignored).
# Usage: scripts/deploy.sh [--all] [--dry-run]
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .deploy.local ] || { echo "error: .deploy.local missing (copy .deploy.local.example)" >&2; exit 2; }
# shellcheck disable=SC1091
source .deploy.local
: "${DEPLOY_HOST:?}" "${DEPLOY_DIR:?}"
UPLOADER="${DEPLOY_UPLOADER:-$HOME/.claude/skills/sftp-upload/scripts/upload.py}"
ALL=0; DRY=0
for a in "$@"; do case "$a" in --all) ALL=1;; --dry-run) DRY=1;; *) echo "unknown option $a" >&2; exit 2;; esac; done

# Only committed code is deployed, so the server always matches a known commit.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "error: uncommitted changes; commit first" >&2; exit 2
fi
php tests/run.php >/dev/null || { echo "error: tests failing; not deploying" >&2; exit 1; }

HEAD_SHA=$(git rev-parse HEAD)
LAST=""; [ -f .deploy-state ] && LAST=$(cat .deploy-state)
if [ "$ALL" = 1 ] || [ -z "$LAST" ] || ! git cat-file -e "$LAST^{commit}" 2>/dev/null; then
  FILES=$(git ls-files)
else
  FILES=$(git diff --name-only --diff-filter=ACMR "$LAST" HEAD)
  git diff --name-only --diff-filter=D "$LAST" HEAD | sed 's/^/not removed on server (delete manually): /' >&2
fi
[ -n "$FILES" ] || echo "no tracked files changed"

TRUST=(); [ "${DEPLOY_TRUST_NEW_HOST:-0}" = 1 ] && TRUST=(--trust-new-host)
while IFS= read -r f; do
  [ -n "$f" ] || continue
  d=$(dirname "$f"); dir="${DEPLOY_DIR%/}/"; [ "$d" != "." ] && dir="${DEPLOY_DIR%/}/$d"
  if [ "$DRY" = 1 ]; then echo "would upload $f"; continue; fi
  # --force: files of this project are overwritten on purpose (updates).
  python3 "$UPLOADER" "$f" --host "$DEPLOY_HOST" --dir "$dir" --force "${TRUST[@]}" >/dev/null
  echo "uploaded $f"
done <<< "$FILES"

# config.php is gitignored: upload it only when its content changed (or with --all). Contents are never shown.
if [ -f config.php ]; then
  CFG_HASH=$(sha256sum config.php | cut -d' ' -f1)
  OLD_HASH=""; [ -f .deploy-config-hash ] && OLD_HASH=$(cat .deploy-config-hash)
  if [ "$ALL" = 1 ] || [ "$CFG_HASH" != "$OLD_HASH" ]; then
    if [ "$DRY" = 1 ]; then
      echo "would upload config.php"
    else
      python3 "$UPLOADER" config.php --host "$DEPLOY_HOST" --dir "${DEPLOY_DIR%/}/" --force "${TRUST[@]}" >/dev/null
      echo "uploaded config.php (contents not shown)"
      echo "$CFG_HASH" > .deploy-config-hash
    fi
  fi
fi

[ "$DRY" = 1 ] || echo "$HEAD_SHA" > .deploy-state

#!/usr/bin/env bash
# Rewrites docs/badges/version.svg (from VERSION) and docs/badges/loc.svg (from the tracked source files).
# Offline and deterministic; docs/badges/deployed.svg is never touched.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/docs/badges"

if [ ! -f "$ROOT/VERSION" ]; then
  echo "VERSION is missing" >&2
  exit 1
fi
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "VERSION must look like N.N.N" >&2
  exit 1
fi

# badge LABEL VALUE FILE: a flat two-part badge, self-contained.
badge() {
  local label="$1" value="$2" file="$3"
  awk -v label="$label" -v value="$value" 'BEGIN {
    lw = length(label) * 6.6 + 14; vw = length(value) * 6.6 + 14
    lw = int(lw + 0.5); vw = int(vw + 0.5); w = lw + vw
    printf "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"%d\" height=\"20\" viewBox=\"0 0 %d 20\" role=\"img\" aria-label=\"%s: %s\">\n", w, w, label, value
    printf "  <title>%s: %s</title>\n", label, value
    printf "  <rect width=\"%d\" height=\"20\" rx=\"3\" fill=\"#2e2a63\"/>\n", w
    printf "  <rect x=\"%d\" width=\"%d\" height=\"20\" rx=\"3\" fill=\"#f2c96b\"/>\n", lw, vw
    printf "  <rect x=\"%d\" width=\"6\" height=\"20\" fill=\"#f2c96b\"/>\n", lw
    printf "  <g font-family=\"Verdana, DejaVu Sans, sans-serif\" font-size=\"11\" text-anchor=\"middle\">\n"
    printf "    <text x=\"%.1f\" y=\"14\" fill=\"#ece9ff\">%s</text>\n", lw / 2, label
    printf "    <text x=\"%.1f\" y=\"14\" fill=\"#1a1330\">%s</text>\n", lw + vw / 2, value
    printf "  </g>\n</svg>\n"
  }' > "$file"
}

mkdir -p "$OUT"
badge "version" "v$VERSION" "$OUT/version.svg"

if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  LINES="$(cd "$ROOT" && git ls-files -z -- '*.php' '*.js' '*.css' '*.sh' '*.sql' ':!docs' | while IFS= read -r -d '' f; do [ -f "$f" ] && printf '%s\0' "$f"; done | xargs -0 cat | wc -l)"
  SHOWN="$(awk -v n="$LINES" 'BEGIN { h = int(n / 100 + 0.5); if (h < 10) printf "%d", h * 100; else printf "%.1fk", h / 10 }')"
  badge "lines of code" "$SHOWN" "$OUT/loc.svg"
else
  echo "not a git work tree: lines-of-code badge left as it is" >&2
fi
echo "badges updated"

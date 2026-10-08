# ✦ Arcana

A page for magic lovers with two modes (choose on the home page):

**Self discovery** — enter your **birth date**, **birth time** and **birth city**:
- ☉ ↑ ☽ **Sun, Ascendant and Moon** signs
- **Mercury to Pluto** and the mean North Node, with retrograde marks (births 1800-2100)
- the **signs with most affinity** to you and the sign of the **love of your life**
- your three **biorhythms** (physical, emotional, intellectual) for today, with next peaks and troughs

**Love** — your name and birth data plus a loved person (their name is enough; date, time and city are optional):
- **name affinity** in percent (formula below)
- **biorhythm synchrony** of the two of you (needs both birth dates)
- **common values** in Sun, Moon and Ascendant
- a **3-day tarot reading** (same input and day give the same cards)

Both results have a **Print** button (and a print layout without forms), so you can keep a paper copy.

**Name affinity.** Join both names and count the letters A, M, O, R, E, in that order. A count of 10 or more keeps its last digit and adds its first digit to the previous slot (for A, to the next one); the five slots form a number. While the number is above 100, replace it by the sums of each pair of adjacent digits (6,6,9 gives 12 and 15, so 135) until it is 100 or less. Example: Andrea Lorenzani and Silvia Pellico have A=4, M=0, O=2, R=2, E=3, so 40223, 4245, 669, 135, and the answer is **48%**. Name affinity, biorhythms, scores and tarot are for wonder, not science.

PHP 8.1+, no Composer, no database. Tropical zodiac, computed with Meeus astronomical algorithms (Sun/Moon ≈ 0.01°). Planets use JPL Keplerian elements (1800-2100, about 0.01-0.05°). Nothing is stored; only the city text is sent to the free [Open-Meteo geocoding API](https://open-meteo.com/en/docs/geocoding-api) (cached, with an offline list of major cities as fallback). Results are shareable GET URLs: they contain names and birth data, so they are marked noindex/no-store and should be shared only with people you trust. "Today" is the server's UTC date; add `&on=YYYY-MM-DD` to fix it.

## Run it

```bash
php -S localhost:8081 -t public     # http://localhost:8081
php tests/run.php                   # tests (astronomy, time zones, input validation, both modes)
```

## Deploy (Apache shared hosting)

1. Set the site's **web directory to `<project>/public`** (PHP ≥ 8.1, preferred). Alternatively keep the project root as web directory: the root `.htaccess` serves `public/` and blocks `src/`, `docs/`, etc.
2. Upload the whole project (SFTP/git) so `src/`, `templates/`, `cache/` sit beside `public/`.
3. `chmod 775 cache` so geocoding results can be cached.

If MySQL is added later, create the database in the hosting panel and keep credentials in an untracked `config.php` (see [docs/architecture.md](docs/architecture.md) §7).

Automated: copy `.deploy.local.example` to `.deploy.local` (gitignored), fill in the target, keep credentials in `~/.password`, commit, then run `scripts/deploy.sh [--dry-run] [--all]`. It runs the tests, uploads only committed files changed since the last deployed commit (via the `sftp-upload` skill), and cannot delete remote files. The `/new-feature` pipeline ends with the `deployer` agent running this.

## How it works

Wall-clock birth time → UTC (PHP tz database, historical DST) → Julian Day → Sun/Moon/planet longitudes and Ascendant (sidereal time + latitude) → zodiac sign. The Ascendant moves ~1° every 4 minutes, so birth-time precision matters; beyond ±66° latitude it is approximate.

Docs: [architecture](docs/architecture.md) · [code map](docs/code.md) · [roadmap](docs/roadmap.md) · [decisions](docs/decisions/).

## Building new features with agents

```
/new-feature "add Mercury and Venus signs"
```

runs **architect** (reads `docs/`, writes an ADR) → **implementer** (code + tests) → **reviewer** → **documenter** (updates `docs/` and this README). Agents live in `.claude/agents/`; project rules in `CLAUDE.md`.

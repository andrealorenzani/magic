# ✦ Arcana

A page for magic lovers: enter your **birth date**, **birth time** and **birth city** and discover your

- ☉ **Sun sign** (zodiac star sign)
- ↑ **Ascendant** (rising sign)
- ☽ **Moon sign**

PHP 8.1+, no Composer, no database. Tropical zodiac, computed with Meeus astronomical algorithms (Sun/Moon ≈ 0.01°). Birth data is not stored; only the city text is sent to the free [Open-Meteo geocoding API](https://open-meteo.com/en/docs/geocoding-api) (cached, with an offline list of major cities as fallback). Results are shareable GET URLs.

## Run it

```bash
php -S localhost:8081 -t public     # http://localhost:8081
php tests/run.php                   # tests (astronomy, time zones, input validation)
```

## Deploy on DreamHost

1. Domains → Manage Websites → set the site's **web directory to `<project>/public`** (PHP ≥ 8.1).
2. Upload the whole project (SFTP/git) so `src/`, `templates/`, `cache/` sit beside `public/`.
3. `chmod 775 cache` so geocoding results can be cached.

If MySQL is added later, create the database in the DreamHost panel and keep credentials in an untracked `config.php` (see [docs/architecture.md](docs/architecture.md) §7).

## How it works

Wall-clock birth time → UTC (PHP tz database, historical DST) → Julian Day → Sun/Moon longitudes and Ascendant (sidereal time + latitude) → zodiac sign. The Ascendant moves ~1° every 4 minutes, so birth-time precision matters; beyond ±66° latitude it is approximate.

Docs: [architecture](docs/architecture.md) · [code map](docs/code.md) · [roadmap](docs/roadmap.md) · [decisions](docs/decisions/).

## Building new features with agents

```
/new-feature "add Mercury and Venus signs"
```

runs **architect** (reads `docs/`, writes an ADR) → **implementer** (code + tests) → **reviewer** → **documenter** (updates `docs/` and this README). Agents live in `.claude/agents/`; project rules in `CLAUDE.md`.

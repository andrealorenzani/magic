# ✦ Magic

A page for magic lovers with two modes (choose on the home page):

**Self discovery** — enter your **birth date**, **birth time** and **birth city**:
- ☉ ↑ ☽ **Sun, Ascendant and Moon** signs
- **Mercury to Pluto** and the mean North Node, with retrograde marks (births 1800-2100)
- the **signs with most affinity** to you and the sign of the **love of your life**
- your three **biorhythms** (physical, emotional, intellectual) for today, with next peaks and troughs

**Love** — your name and birth data plus a loved person (their name is enough; date, time and city are optional):
- **name affinity** in percent
- **biorhythm synchrony** of the two of you (needs both birth dates)
- **common values** in Sun, Moon and Ascendant
- a **3-day tarot reading** (same input and day give the same cards)

Both results have a **Print** button (and a print layout without forms), so you can keep a paper copy.

Name affinity, biorhythms, scores and tarot are for wonder, not science.

PHP 8.1+, no Composer. MySQL is optional and used only for the audit trail (below). Tropical zodiac. Sun and Moon are very accurate; planets (1800-2100) are approximate. Only the city text is sent to the free [Open-Meteo geocoding API](https://open-meteo.com/en/docs/geocoding-api) (cached, with an offline list of major cities as fallback). Results are shareable GET URLs: they contain names and birth data, so they are marked noindex/no-store and should be shared only with people you trust. "Today" is the server's UTC date; add `&on=YYYY-MM-DD` to fix it.

**Terms and Conditions.** On the first visit a popup asks you to accept the Terms and Conditions; until you do, nothing is processed and nothing is recorded. Acceptance is remembered with one functional cookie (`magic_terms`, 1 year, no identifier). The same text is in the expandable "Terms and Conditions" section at the end of the page, where you can withdraw your acceptance.

**Privacy.** Each Self or Love result is recorded in an audit log: names, birth date, time and place (including the loved person's details, only what is entered) and a YAML summary of the result. No IP address or user agent is stored and nothing is used to track you. There is no automatic deletion: the owner purges with `scripts/db-purge.sh --days N` or removes a person on request (details in [docs/architecture.md](docs/architecture.md) §5). Without a database the app works normally and stores nothing.

## Run it

### With Docker (PHP, Apache and MySQL included)

```bash
docker compose up --build     # page at http://localhost:8081
```

Open the page and accept the Terms and Conditions in the popup (results are recorded in the local database only after that).

- **Database content:** the `migrations/` folder is applied automatically the first time the data volume is created. Apply it again later with `scripts/docker-db.sh migrate`; `scripts/docker-db.sh reset` wipes the volume and starts from scratch.
- **Query it:** `scripts/docker-db.sh audit [N]` (latest N results, default 10), `scripts/docker-db.sh query "SELECT ..."`, `scripts/docker-db.sh shell` (interactive mysql). The database is not published to the host; to use your own client add `ports: ["127.0.0.1:3307:3306"]` to the `db` service in `docker-compose.yml`.
- **Stop:** `docker compose down` (keeps the data); `docker compose down -v` also deletes the data.
- The local database password is a throwaway default; override it with the `MAGIC_DB_PASSWORD` environment variable.

### Without Docker

PHP 8.1+ is needed only if you prefer the built-in server or want to run the tests:

```bash
php -S localhost:8081 -t public     # http://localhost:8081 (no database unless config.php exists)
php tests/run.php                   # tests (astronomy, time zones, input validation, both modes, audit)
```

## Deploy (Apache shared hosting)

1. Set the site's **web directory to `<project>/public`** (PHP ≥ 8.1, preferred). Alternatively keep the project root as web directory: the root `.htaccess` serves `public/` and blocks `src/`, `docs/`, etc.
2. Upload the whole project (SFTP/git) so `src/`, `templates/`, `cache/` sit beside `public/`.
3. `chmod 775 cache` so geocoding results can be cached.

### Database (optional, audit trail)

1. Create a MySQL database in the hosting panel.
2. Create `config.php` in the project root (gitignored): either copy `config.php.example` and edit it, or run `scripts/make-config.sh` (reads the database section of `~/.password`; its name is `DB_PASSWORD_SECTION` in `.deploy.local`).
3. Run `scripts/db-migrate.sh` (needs the `mysql` client and a database server that accepts your machine; otherwise paste `migrations/001_create_magic_audit.sql` into the panel's SQL tool).

If the tables or `config.php` are missing, pages still work and the audit write fails silently. Manual retention: `scripts/db-purge.sh --days N`. Status: the tables have not been created in the real database yet (the server refused the connection from the development machine).

Automated: copy `.deploy.local.example` to `.deploy.local` (gitignored), fill in the target, keep credentials in `~/.password`, commit, then run `scripts/deploy.sh [--dry-run] [--all]`. It runs the tests, uploads only committed files changed since the last deployed commit (via the `sftp-upload` skill), and uploads `config.php` too when it changed (contents never shown), and cannot delete remote files. The `/new-feature` pipeline ends with the `deployer` agent running this.

## How it works

The wall-clock birth time is converted to UTC with the PHP time zone database (historical DST), then the signs are determined for that instant and place. The Ascendant changes quickly, so birth-time precision matters; at extreme latitudes it is approximate.

Docs: [architecture](docs/architecture.md) · [code map](docs/code.md) · [roadmap](docs/roadmap.md) · [decisions](docs/decisions/).

## Building new features with agents

```
/new-feature "add Mercury and Venus signs"
```

runs **architect** (reads `docs/`, writes an ADR) → **implementer** (code + tests) → **reviewer** → **documenter** (updates `docs/` and this README). Agents live in `.claude/agents/`; project rules in `CLAUDE.md`.

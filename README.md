# ✦ Magic

A page for magic lovers in three steps (choose on the home page): Self Discovery, Soul Affinity, Friends hidden codes.

**1. Self Discovery** — enter your **birth date**, **birth time** and **birth city**:
- ☉ ↑ ☽ **Sun, Ascendant and Moon** signs
- **Mercury to Pluto** and the mean North Node, with retrograde marks (births 1800-2100)
- the **Midheaven**, the house of each body and a **chart wheel**
- the **signs with most affinity** to you and the sign of the **love of your life**
- **born under** (moon phase at birth) and **Today's sky**
- your three **biorhythms** (physical, emotional, intellectual) for today, with next peaks and troughs

**2. Soul Affinity** (locked until your Self Discovery data is stored; it uses that data, so you do not type yourself again) — you plus another soul (their name is enough; date, time and city are optional, or import their hidden code):
- **name affinity** in percent
- **biorhythm synchrony** of the two of you (needs both birth dates)
- **common values** in Sun, Moon and Ascendant, and a **synastry** table
- a **Past / Present / Future tarot spread** (78 cards) for the two of you (same input and day give the same cards)

**3. Friends hidden codes** (locked until Self Discovery data is stored; needs JavaScript) — the hidden codes friends shared with you, kept in your browser with a nickname you choose: search, rename, remove, Compare.

Self Discovery has an optional **name** and the buttons Generate hidden data code, Clear data, Reveal my sky and Save the data. A small **?** next to fields and results explains them.

**Hidden sharing.** "Generate hidden data code" makes a link and QR code with your details that another person can paste or scan in Soul Affinity ("Import from a user"); their screen does not show your name, birth data or place and the result has no share section. "Hidden" only means not shown: the link contains the details, so share it only with someone you trust. Opening such a link is recorded in the audit like any Soul Affinity result.

Self Discovery and Soul Affinity have an optional **current city**: it sets your local "today" and shows the **distance** between places. Details in [docs/features.md](docs/features.md).

Both results have a **Share** section and a **Print** button (and a print layout without forms), so you can keep a paper copy.

**Sharing.** Under a result, "Share this reading" shows a short link (`?c=...`) to the exact reading (same day, and in Love the same tarot cards) with a small QR code, a link to a live reading (same people, today's values), and a Copy button where the browser supports it. Old long links still work. The QR is generated on the server in pure PHP; if the link is too long for a QR only the link is shown. Links contain the names and birth details you entered: share them only with people you trust. Opening a shared link is not recorded in the audit again, because share links carry `noaudit`.

**Testing without recording.** Add `&noaudit` (or `?noaudit`) to any URL and that request is not written to the audit log.

Name affinity, biorhythms, scores and tarot are for wonder, not science.

PHP 8.1+, no Composer. MySQL is optional and used only for the audit trail (below). Tropical zodiac. Sun and Moon are very accurate; planets (1800-2100) are approximate. Only the city text is sent to the free [Open-Meteo geocoding API](https://open-meteo.com/en/docs/geocoding-api) (cached, with an offline list of major cities as fallback). Results are shareable GET URLs: they contain names and birth data, so they are marked noindex/no-store and should be shared only with people you trust. "Today" is the day at your current city if you enter one, else the server's UTC date; add `&on=YYYY-MM-DD` to fix it.

**Browser memory.** If your browser allows it, your own details, a short list of people you looked up and your friends' hidden codes with nicknames are remembered in your browser only, never stored by the server; "Clear data" erases them (the Terms acceptance stays), and so does withdrawing your acceptance.

**Terms and Conditions.** On the first visit a popup asks you to accept the Terms and Conditions; until you do, nothing is processed and nothing is recorded. Acceptance is remembered with one functional cookie (`magic_terms`, 1 year, no identifier). The Terms were updated in v0.9 (version 4), so everybody accepts once again. The same text is in the expandable "Terms and Conditions" section at the end of the page, where you can withdraw your acceptance.

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
php tests/run.php                   # 248 tests (astronomy, time zones, input validation, all modes, audit, short links, browser memory, page flow)
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

Docs: [features](docs/features.md) · [architecture](docs/architecture.md) · [code map](docs/code.md) · [changelog](docs/changelog.md) · [roadmap](docs/roadmap.md) · [decisions](docs/decisions/).

## Building new features with agents

```
/new-feature "add Mercury and Venus signs"
```

runs **architect** (reads `docs/`, writes an ADR) → **implementer** (code + tests) → **reviewer** → **documenter** (updates `docs/` and this README). Agents live in `.claude/agents/`; project rules in `CLAUDE.md`.

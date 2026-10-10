# Developer guide

The business description is in [README.md](README.md). Design and code maps are in [docs/](docs/).

## Requirements

PHP 8.1+. No Composer, no build step, no dependencies. MySQL is optional and used only for the audit trail. Docker is optional for a local PHP + Apache + MySQL setup.

## Run

```bash
php -S localhost:8081 -t public      # http://localhost:8081 (no database unless config.php exists)
docker compose up --build            # PHP + Apache + MySQL, page at http://localhost:8081
```

Accept the Terms in the popup; results are recorded in the local database only after that.

Docker database helper `scripts/docker-db.sh`:

- `migrate` re-applies `migrations/` (applied automatically the first time the data volume is created).
- `audit [N]` shows the latest N results (default 10); `query "SELECT ..."` runs SQL; `shell` opens mysql; `reset` wipes the volume.
- `docker compose down` keeps the data, `docker compose down -v` deletes it.
- The database is not published to the host. The local password is a throwaway default, overridable with `MAGIC_DB_PASSWORD`.

## Tests

```bash
php tests/run.php        # 264 tests, exit code != 0 on failure
```

Dependency-free runner. Astronomical output needs a reference-value test. `tests/cases/docs.php` also checks `VERSION`, the badges, the README and DEVELOPER.md structure and that the site name appears only where allowed.

## Testing without recording

Add `&noaudit` (or `?noaudit`) to any URL and that request is not written to the audit log. Share links carry it. Add `&on=YYYY-MM-DD` to fix "today" (biorhythms, tarot).

## Database (optional, audit trail)

1. Create a MySQL database.
2. Create `config.php` in the project root (gitignored): copy `config.php.example`, or run `scripts/make-config.sh` (reads the database section of `~/.password`; the section name is `DB_PASSWORD_SECTION` in `.deploy.local`).
3. Run `scripts/db-migrate.sh` (needs the `mysql` client and a server that accepts your machine; otherwise paste `migrations/001_create_magic_audit.sql` into the hosting panel's SQL tool). All tables are prefixed `magic_`.

If the tables or `config.php` are missing, pages still work and the audit write fails silently. Manual retention: `scripts/db-purge.sh --days N`.

## Deploy (Apache shared hosting)

1. Set the web directory to `<project>/public` (preferred). Alternatively keep the project root: the root `.htaccess` serves `public/` and blocks everything else.
2. Upload the whole project so `src/`, `templates/`, `cache/` sit beside `public/`; `chmod 775 cache`.
3. Copy `.deploy.local.example` to `.deploy.local` (gitignored) and fill in the target; credentials stay in `~/.password`.
4. Commit, then run `scripts/deploy.sh [--dry-run] [--all]`. It runs the tests, uploads committed files changed since the last deployed commit through the `sftp-upload` skill (and `config.php` when it changed), and cannot delete remote files.

Never write the server host, domain, provider or credentials in tracked files.

## Badges and releases

- `VERSION` (one line, `N.N.N`) is the single source of the version.
- `scripts/update-badges.sh` rewrites `docs/badges/version.svg` and `docs/badges/loc.svg` offline from `VERSION` and the tracked source files. `deployed.svg` is hand-authored and never regenerated.
- Release: bump `VERSION`, run `scripts/update-badges.sh`, move "Unreleased" in `docs/changelog.md` under the same version heading.

## Agent workflow

```
/new-feature "idea"
```

runs architect (ADR in `docs/decisions/`) → implementer (code + tests) → reviewer → documenter → commit → deployer (`scripts/deploy.sh`). Agents live in `.claude/agents/`; project rules are in `CLAUDE.md`. Only the documenter edits `docs/`, `README.md`, `DEVELOPER.md`, `VERSION` and `docs/badges/`.

## Layering and CSP

`src/Astro`, `src/Time`, `src/Chart.php` and the other pure classes do no I/O; the clock is read only in `public/index.php`. Templates only render and escape every output with `e()`.

No inline scripts or styles: `public/.htaccess` sets a strict CSP and no third-party source is allowed. Scripts position elements through CSSOM properties only.

## Documents

[architecture](docs/architecture.md) · [code map](docs/code.md) · [features](docs/features.md) · [changelog](docs/changelog.md) · [roadmap](docs/roadmap.md) · [decisions](docs/decisions/)

# Magic — agent rules

PHP 8.1+ page (Sun, Ascendant, Moon) for Apache shared hosting. MySQL is the only allowed database, and only via an ADR. Read `docs/architecture.md` and `docs/code.md` before changing anything.

- Run tests: `php tests/run.php` · Dev server: `php -S localhost:8081 -t public`
- No Composer/dependencies, no build step. `src/Astro`, `src/Time`, `src/Chart.php` stay pure (no I/O).
- Escape every template output with `e()`; no inline scripts/styles (CSP in `public/.htaccess`).
- New features go through `/new-feature <idea>` (architect → implementer → reviewer → documenter → commit → deployer).
- **Changelog**: `docs/changelog.md` must be updated (by the `documenter`) for every user- or developer-visible change; a change is not finished without its entry.
- Only the `documenter` agent edits `docs/` and `README.md`; if you change code directly, invoke it afterwards.
- Astronomical output needs a reference-value test.
- **Every change must end up on the server**: after committing, run `scripts/deploy.sh` (or the `deployer` agent). It uses the `sftp-upload` skill; target and options are in the gitignored `.deploy.local` (template: `.deploy.local.example`), credentials in `~/.password`.
- **Confidentiality**: never write the server host, domain name, hosting-provider name or credentials into any tracked file, doc, commit message or agent output. Use "the server"/"shared hosting". Such data may only live in gitignored files (`.deploy.local`).
- **Database**: MySQL only (PDO, prepared statements). **All tables of this project are prefixed `magic_`** (e.g. `magic_audit`). Schema changes are SQL files in `migrations/`, applied with the `mysql` command. DB host, user, password and database name live only in the gitignored `config.php` and in `~/.password` (never print that file, never put its values in tracked files, commits or agent output). A DB failure must never break a page.
- **No algorithms in docs**: never write about any algorithm (names, formulas, rules, steps, sources) in any doc, README, ADR, UI text or code comment. Describe what a feature does, not how it computes it.
- **Terms and Conditions**: the page is unusable until the visitor accepts the T&Cs (popup on first visit, cookie `magic_terms`, text in `templates/partials/terms.php`, expandable section at the end of the page). No audit record and no result without that consent.
- **Local run**: `docker compose up --build` (PHP + Apache + MySQL, page at http://localhost:8081); database helper: `scripts/docker-db.sh`.
- **Shareable results**: everything flagged as shareable must always be encoded in the share URL (and its QR code). Whatever is purely derived from the input is recomputed; anything random or not derivable (e.g. the tarot cards drawn and their orientation) must be in the URL, so a shared page shows exactly what the sender saw. Any new feature with such output must extend the share link and its tests.

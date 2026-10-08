# Arcana — agent rules

PHP 8.1+ page (Sun, Ascendant, Moon) for Apache shared hosting. MySQL is the only allowed database, and only via an ADR (none used today). Read `docs/architecture.md` and `docs/code.md` before changing anything.

- Run tests: `php tests/run.php` · Dev server: `php -S localhost:8081 -t public`
- No Composer/dependencies, no build step. `src/Astro`, `src/Time`, `src/Chart.php` stay pure (no I/O).
- Escape every template output with `e()`; no inline scripts/styles (CSP in `public/.htaccess`).
- New features go through `/new-feature <idea>` (architect → implementer → reviewer → documenter → commit → deployer).
- Only the `documenter` agent edits `docs/` and `README.md`; if you change code directly, invoke it afterwards.
- Astronomical output needs a reference-value test.
- **Every change must end up on the server**: after committing, run `scripts/deploy.sh` (or the `deployer` agent). It uses the `sftp-upload` skill; target and options are in the gitignored `.deploy.local` (template: `.deploy.local.example`), credentials in `~/.password`.
- **Confidentiality**: never write the server host, domain name, hosting-provider name or credentials into any tracked file, doc, commit message or agent output. Use "the server"/"shared hosting". Such data may only live in gitignored files (`.deploy.local`).

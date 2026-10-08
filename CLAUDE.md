# Arcana — agent rules

PHP 8.1+ page (Sun, Ascendant, Moon) deployed on DreamHost shared hosting. MySQL is the only allowed database, and only via an ADR (none used today). Read `docs/architecture.md` and `docs/code.md` before changing anything.

- Run tests: `php tests/run.php` · Dev server: `php -S localhost:8081 -t public`
- No Composer/dependencies, no build step. `src/Astro`, `src/Time`, `src/Chart.php` stay pure (no I/O).
- Escape every template output with `e()`; no inline scripts/styles (CSP in `public/.htaccess`).
- New features go through `/new-feature <idea>` (architect → implementer → reviewer → documenter).
- Only the `documenter` agent edits `docs/` and `README.md`; if you change code directly, invoke it afterwards.
- Astronomical output needs a reference-value test.

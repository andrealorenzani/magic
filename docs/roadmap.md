# Roadmap

Maintained by the `documenter` agent. Move items to "Done" when shipped (link the ADR).

## Done
- v0.1 — Sun, Ascendant, Moon from date/time/city (PHP, no database)
- v0.3 — Two modes ([ADR 0002](decisions/0002-self-discovery-and-love-modes.md)): Self discovery (Mercury–Pluto and North Node 1800–2100, sign affinities, "love of your life" sign, biorhythms) and Love (name affinity, biorhythm synchrony, common Sun/Moon/Ascendant values, 3-day tarot); printable results (`@media print` + print button); partial data for the loved person; daily "today" with `on=` override
- Planets Mercury–Pluto (without chart wheel) — shipped in v0.3
- Compatibility between two people (basic, via common values) — shipped in v0.3
- Audit trail ([ADR 0003](decisions/0003-mysql-audit-trail.md)) — every Self/Love result is recorded in MySQL (`magic_audit`, `magic_audit_person`) with a YAML summary; code, scripts, notice and tests shipped. **Pending operation:** the tables are not created in the real database yet (connection refused from the dev machine); apply `scripts/db-migrate.sh` from an allowed host or via the hosting panel's SQL tool
- Terms and Conditions consent ([ADR 0003](decisions/0003-mysql-audit-trail.md), update) — blocking popup on first visit, functional cookie `magic_terms`, expandable T&Cs section with withdraw; no result and no audit without consent
- Local run with Docker — `docker compose up --build` (PHP + Apache + MySQL 8.4), `scripts/docker-db.sh`
- Project renamed from Arcana to Magic (namespace `Magic\`)

## Ideas (unprioritised)
- Chart wheel (SVG)
- Houses (Placidus / Whole Sign) and Midheaven
- Social image card for shared results
- MySQL: saved charts / accounts (needs privacy ADR)
- Automatic retention job for the audit (today `scripts/db-purge.sh --days N` is manual)
- Write rate limit / de-duplication of audit rows
- Admin view of the audit, behind authentication
- Name field in the Self form (today the audit stores '')
- Mode 600 for the uploaded `config.php` (the uploader keeps the server default)
- Italian and other languages
- Sidereal zodiac toggle
- Full synastry (planet-to-planet aspects between two charts)
- Daily horoscope / moon phase widget
- PWA / offline support
- Manual QA of keyboard navigation (incl. the new notice) and Print to PDF in Chrome and Firefox (A4 layout, ADR 0002 risk)
- Planets before 1800 (`Planets::supports` is the gate)
- Client time zone for "today" (server uses the UTC date, so it can be a day off)
- More tarot cards (minor arcana) and more copy variety
- Cap/cleanup of the geocoding cache in `cache/`
- Optional "private mode" (POST) so names stay out of URLs and logs

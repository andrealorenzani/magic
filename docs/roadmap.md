# Roadmap

Maintained by the `documenter` agent. Move items to "Done" when shipped (name the ADR; the ones before 1.0.0 are summarised in `docs/decisions/0001-before_release.md`).

## Done
- v0.1 — Sun, Ascendant, Moon from date/time/city (PHP, no database)
- v0.3 — Two modes (ADR 0002): Self discovery (Mercury–Pluto and North Node 1800–2100, sign affinities, "love of your life" sign, biorhythms) and Love (name affinity, biorhythm synchrony, common Sun/Moon/Ascendant values, 3-day tarot, replaced in v0.6); printable results (`@media print` + print button); partial data for the loved person; daily "today" with `on=` override
- Planets Mercury–Pluto (without chart wheel) — shipped in v0.3
- Compatibility between two people (basic, via common values) — shipped in v0.3
- Audit trail (ADR 0003) — every Self/Love result is recorded in MySQL (`magic_audit`, `magic_audit_person`) with a YAML summary; code, scripts, notice and tests shipped. **Pending operation:** the tables are not created in the real database yet (connection refused from the dev machine); apply `scripts/db-migrate.sh` from an allowed host or via the hosting panel's SQL tool
- Terms and Conditions consent (ADR 0003, update) — blocking popup on first visit, functional cookie `magic_terms`, expandable T&Cs section with withdraw; no result and no audit without consent
- Local run with Docker — `docker compose up --build` (PHP + Apache + MySQL 8.4), `scripts/docker-db.sh`
- v1.0.0 — First major release: refactor (dead consent action and unused constants removed, `release/` never deployed), docs refreshed, ADRs consolidated into `docs/decisions/0001-before_release.md`, release process documented in `DEVELOPER.md`
- Sharing and polish (ADR 0004) — Share section (frozen and live links, QR in pure PHP, tarot in `t`, share links carry `noaudit`), `?noaudit`, Past/Present/Future tarot spread (replaces the 3-day tarot), compact biorhythm synchrony, richer "In common", Terms gate fixes (no-store, `Vary: Cookie`, query kept, CSP additions), audit `format_version` 2
- Project renamed from Arcana to Magic (namespace `Magic\`)
- Current position, short links, browser memory and derived features (ADR 0005, v0.7): optional current city and reading-day rule, distance and geography, short `?c=` share links with a smaller QR, browser memory with "Forget my data", Midheaven, houses and chart wheel, moon phase and Today's sky, synastry, 78-card tarot, geocoding cache cap, Terms version 2 (audit `format_version` 3)
- Menu, hidden sharing and import, help popovers, Self name, v0.8 (ADR 0006): Menu (clean browser data, share hidden data), hidden-details link/QR and import by paste or scan, optional Self name, "?" popovers, click-to-copy and collapsible QR, required marks, smaller wheel, Terms section and gate fixes, Terms version 3 (audit `format_version` 4 for hidden-link opens)
- Self Discovery first, Soul Affinity, Friends hidden codes, v0.9 (ADR 0007): Menu removed, Self buttons (Generate hidden data code, Clear data, Reveal my sky, Save the data), Soul Affinity (Love renamed in the UI) unlocked by stored Self data with no "You" part, Import from a user, Friends hidden codes in the browser with nicknames, shared confirm dialog, newcomer hidden-link path, Terms version 4 (no audit format change)
- Business README, badges, quiet buttons, v0.10 (ADR 0008): README as a business specification, `DEVELOPER.md`, `VERSION` and badges (`scripts/update-badges.sh`), quiet buttons with `.btn--primary`, WhatsApp button for hidden codes, honest hidden-code wording, help terms replacing the "?" buttons (33 keys), Self Discovery without a share section, no "Sharing" stub for hidden-derived Soul Affinity, compact Friends list with icon buttons, Terms version 5
- Docs: `docs/features.md` and `docs/changelog.md`

## Ideas (unprioritised)
- Planets before 1800 (`Planets::supports` is the gate); skipped earlier: needs another data source
- Social image card for shared results; skipped: needs image rendering without dependencies, and result pages are behind the Terms and noindex
- Personal tarot spread in Self mode, "Draw again" button; skipped: needs more copy or non-reproducible readings (owner decision)
- Count opens of shared links in the audit; skipped: needs a marker column and migration
- Another house system; skipped: would need a house-system selector
- Manual QR scan test on real phones for a short, typical and longest link; owner review of the tarot and daily texts
- Manual QA of keyboard navigation (share box, saved-people select) and Print to PDF in Chrome and Firefox (wheel, synastry, details open)
- MySQL: saved charts / accounts (needs privacy ADR)
- Automatic retention job for the audit (today `scripts/db-purge.sh --days N` is manual)
- Write rate limit / de-duplication of audit rows
- Admin view of the audit, behind authentication
- Mode 600 for the uploaded `config.php` (the uploader keeps the server default)
- Italian and other languages
- Sidereal zodiac toggle
- PWA / offline support
- Real encryption of hidden codes, so only the chosen recipient can read them (today a hidden code is only an encoding; needs its own ADR)
- Hidden links without the data inside them (needs server-side storage and its own ADR)
- Optional "private mode" (POST) so names stay out of URLs and logs
- Longer place labels in short codes (today cut at 32 bytes)

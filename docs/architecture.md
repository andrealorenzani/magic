# Architecture

> Status: v0.3 (PHP, two modes) · Owner of this file: the `documenter` agent (`.claude/agents/documenter.md`).

## 1. Goal

Arcana is a web page for magic lovers. The visitor enters **birth date**, **birth time (hh:mm)** and **birth city**, and receives:

| Result | Meaning | Depends on |
|---|---|---|
| Sun sign | zodiac star sign | date + time (cusp days) |
| Ascendant (rising sign) | sign on the eastern horizon at birth | date, time, **latitude, longitude** |
| Moon sign | sign the Moon occupied | date + time |

The home page offers two modes (ADR [0002](decisions/0002-self-discovery-and-love-modes.md)):

- **Self discovery** (`?mode=self`): the three results above plus Mercury–Pluto and the mean North Node, the signs with most affinity, a "love of your life" sign, the three biorhythms and a printable page.
- **Love** (`?mode=love`): the user and a loved person (only the name is required for the loved person): name affinity %, biorhythm synchrony, common Sun/Moon/Ascendant values, a 3-day tarot reading, printable.

Long-term ambition: the most-used page for magic lovers, so adding features (houses, sharing…) must be cheap.

## 2. Constraints & key decisions

| # | Decision | Why | Trade-off |
|---|---|---|---|
| D1 | **PHP 8.1+, server-rendered, deployable on Apache shared hosting** (Apache + PHP, FTP/SFTP/git deploy). No Composer, no build step, no framework | Hard requirement (hosting). Nothing to install on the server | We write a tiny autoloader/router ourselves |
| D2 | **Database: MySQL only, and only when a feature needs persistence. v0.2 uses none** | Nothing in the big-three flow needs storing; fewer moving parts, no personal data at rest | Features like accounts/saved charts/share-by-id need an ADR + schema (see §7) |
| D3 | **Astronomy implemented in-house in PHP** (`Arcana\Astro\*`, Meeus low-precision series) | No dependency (no Swiss Ephemeris binary on shared hosting), testable. Sun/Moon ≈ 0.01°. Planets (1800–2100) from the JPL Keplerian elements (E. M. Standish, Table 1, valid 1800–2050, extrapolated to 2100): about 0.01° inner planets and Mars, a few hundredths of a degree for Jupiter–Pluto, ignoring light-time and aberration | A sign may be wrong within ~0.01° (Sun/Moon) or ~0.05° (planets) of a boundary; outside 1800–2100 only the big three are shown |
| D4 | **Tropical zodiac** | The Western standard users expect | Sidereal could be added later |
| D5 | **Geocoding via Open-Meteo** (lat, lon, IANA time zone), called **server-side**, results cached on disk in `cache/`, bundled fallback list if the API is down | One call yields all place data; server-side call keeps visitors' queries out of third-party JS and allows caching | Needs outbound HTTP (`curl` or `allow_url_fopen`, both normally on at shared hosts) |
| D6 | **Time zones via PHP's `DateTimeZone`** | Correct historical DST/offsets from the tz database, no library | Very old dates use LMT as in the tz database |
| D7 | **Pure core, thin web layer** | `Arcana\Astro`, `Chart`, `Time` have no I/O and are unit-tested from the CLI | — |
| D8 | **Progressive enhancement**: the page works without JavaScript (server resolves the city text); JS only adds city autocomplete | Robust, SEO-friendly, shareable GET URLs | — |
| D9 | **Agentic dev workflow** in `.claude/` | See §6 | — |

## 3. System overview

```
 Browser ── GET /?mode=self&date&time&city[&lat&lon&tz][&on=]            ┐
         └─ GET /?mode=love&a_name&a_date&a_time&a_city..&b_name&b_..    ├─► public/index.php
    │                                                                     ┘        │
    │  assets/autocomplete.js (one per [data-place])                               ├─ Request::mode / parseToday (clock read here only)
    │      └─ GET api/cities.php?q= ──► Geocoder ──────────────────────────────────┤
    │  assets/print.js (un-hides the Print button)                                 ├─ Request::parse | parseLove ─► Geo\Geocoder ─► Open-Meteo
    │                                                                              │      (cache/ on disk, Geo\FallbackCities if unreachable)
    │                                                                              ├─ SelfReading::build  ─┐ pure view-models
    │                                                                              ├─ LoveReading::build  ─┤
    │                                                                              │    Chart::full / partial ─► Time\Zone, Astro\Angles/Sun/Moon/
    │                                                                              │         Ascendant/Planets/MeanNode/Zodiac
    │                                                                              │    Love\SignAffinity, NameAffinity, Common · Bio\Biorhythm, Synchrony
    │                                                                              │    Tarot\Reading (+ Content\* copy)
    │                                                                              └─ templates/home.php ► self-result.php | love-result.php (+ partials/)
```

Layering rule (checked by `reviewer` and `tests/cases/layering.php`): `Astro\*`, `Time\*`, `Chart`, `Love\*`, `Bio\*`, `Tarot\*`, `SelfReading` and `LoveReading` never do I/O (no network, files, `$_GET`, echo, clock). Only `public/` and `Request`/`Geo` touch the outside world. Templates only render; they escape everything with `e()`.

## 4. Calculation pipeline

Shared core (both modes):

1. **Input** — `year, month, day, hour, minute` (wall-clock at birthplace) + `lat, lon (east+), timeZone`.
2. **UTC** — `Zone::toUnix` via `DateTimeImmutable` in the IANA zone. DST gap → shifted forward; overlap → first occurrence.
3. **Julian Day** — `Angles::julianDay(unix)`; UT used as TT (≤ 0.01° effect on the Moon).
4. **Sun** — apparent longitude (Meeus ch. 25). **Moon** — 38 largest terms of Meeus ch. 47 + nutation.
5. **Ascendant** — `RAMC = GMST + lon`; `ASC = atan2(cos RAMC, −(sin RAMC·cos ε + tan φ·sin ε))`.
6. **Sign** — `floor(lon/30)`; remainder → degree/minute.

Self mode (`SelfReading::build($input, $today)`):

7. **Planets** — `Chart::full` adds Mercury–Pluto (Kepler equation per body from JPL elements, Earth subtracted, precession and nutation added; retrograde = longitude falls over ±12 h) when the year is 1800–2100, and the mean North Node (any year).
8. **Affinities** — `SignAffinity::rank` scores the 11 other signs against the user's Sun, Moon, Ascendant, Venus and Mars (aspect 60% + ruler friendship 25% + modality 15%): top 3 by a balanced weighting and one "love of your life" sign by a heart-focused weighting.
9. **Biorhythms** — physical 23, emotional 28, intellectual 33 days from the birth *date* to `today`.

Love mode (`LoveReading::build($a, $b, $today)`):

7. **Partial charts** — `Chart::partial`: with date+time+place all three signs; with date only, Sun and Moon at 12:00 UTC flagged `approx` when the sign changes during that UTC day; no Ascendant.
8. **Name affinity** — AMORE counts, carry rule, repeated pair sums down to a value ≤ 100 (see README).
9. **Synchrony** — per cycle phase offset `delta`, `sync`, `amplitude`, next combined peak/trough, next day both are high/low.
10. **Common values** — same sign 100, same element 80, complementary elements 60, same modality 40, else 20; weighted Sun 2, Moon 2, Ascendant 1.
11. **Tarot** — `hash('sha256', seed|date|i)` picks 3 distinct Major Arcana for today, tomorrow, the day after; stateless, same input and day give the same cards.

"Today" is the **server's UTC date** (`gmdate`), read only in `public/index.php`, and may be overridden by a valid `on=YYYY-MM-DD` (1900–2100) for tests and reproducible shared links. A visitor far from UTC can therefore see biorhythms and tarot one day off.

Limits: Ascendant flagged approximate beyond ±66° latitude; no houses yet; planets unavailable outside 1800–2100; biorhythms, scores, name affinity and tarot are entertainment.

## 5. Security & privacy

- All user input validated in `Request::parse` (date/time regex + `checkdate`, lat/lon ranges, `tz` checked against `DateTimeZone::listIdentifiers()`); output escaped with `e()`.
- Outbound requests go only to the fixed Open-Meteo host; the query is URL-encoded.
- Only the **city text** is sent to a third party. Birth data and names are processed per request and **not stored** (no DB, no logs of inputs by the app).
- **Names now travel in the GET URL** (Love mode also carries the loved person's birth data). They can appear in web-server access logs, browser history and shared links; the Love form warns "share the link only with people you trust". Result pages send `X-Robots-Tag: noindex`, `<meta name="robots" content="noindex">` and `Cache-Control: private, no-store`. POST was rejected because it breaks shareable URLs (D8); a "private mode" would need its own ADR. Mention this in any privacy policy.
- Names are validated: 1–40 characters, no control characters, at least one letter, valid UTF-8.
- `src/`, `templates/`, `cache/`, `tests/`, `docs/` live **outside** the web root (`public/` is the document root); `cache/` also has a deny `.htaccess`.
- If the web directory is the project root instead, the root `.htaccess` 301-redirects `/public/...` to `/...` and rewrites everything else into `public/`, so `src/`, `templates/`, `cache/`, `docs/`, `tests/` and `README.md` return 404 (this relies on Apache `mod_rewrite`; prefer `public/` as web root).
- `public/.htaccess` sets a strict CSP (no inline scripts/styles — keep it that way).

## 6. Agentic development workflow

```
/new-feature "idea"
    │
    ▼
 architect ──► reads docs/ ──► docs/decisions/NNNN-title.md (design, risks, test plan)
    ▼
 implementer ──► PHP code + tests per the ADR (`php tests/run.php` must pass)
    ▼
 reviewer ──► layering, accuracy, security, a11y ──► findings (loop to implementer if blocking)
    ▼
 documenter ──► updates architecture.md, code.md, roadmap.md, README.md
    ▼
 commit ──► deployer ──► scripts/deploy.sh (tests, then SFTP upload of changed files)
```

Agents: `.claude/agents/`; orchestration: `.claude/commands/new-feature.md`; rules: `CLAUDE.md`. `docs/` is the shared memory. ADRs: `docs/decisions/` (0001 records the move to PHP, 0002 the two modes).

## 7. Extension points

| To add… | Touch |
|---|---|
| A body in Self mode | planets are data-driven: add a row to `Planets::ELEMENTS`/`BODIES`, copy in `Content\Bodies::INFO`, and the planet grid in `templates/self-result.php` picks it up from `Chart::full` |
| Houses | new `src/Astro/Houses.php` reusing `Angles`/`Ascendant::gmst`; expose it in `Chart::full` |
| Planets before 1800 | extend `Planets` with Standish Table 2 (b, c, s, f terms); `supports()` is the single gate |
| A third mode | `Request::MODES`, a `Request::parseX`, a pure `XReading::build`, `templates/x-result.php`, a chooser card in `templates/home.php` |
| Tarot cards / copy / languages | `Content\TarotDeck`, `Content\Traits`, `Content\Bodies`, `Content\Signs` only |
| Scoring weights | constants at the top of `Love\SignAffinity`, `Love\Common`, `Bio\Synchrony::THRESHOLD` |
| Persistence (saved/shared charts, accounts, caching city lookups in SQL) | **MySQL** via PDO: add `src/Db.php` (config from an untracked `config.php`, MySQL host/user/password from the hosting panel), SQL in `migrations/NNN_*.sql`, an ADR superseding D2. Never store birth data without an explicit privacy decision |
| New page/route | new file in `public/` (plain PHP entry scripts; `index.php` is a small front controller on `mode`) + template in `templates/` |

## 8. Testing

`php tests/run.php` — dependency-free runner (exit code ≠ 0 on failure); core checks live in `tests/run.php`, ADR 0002 checks in `tests/cases/*.php` (`planets`, `bio`, `love`, `tarot`, `request`, `layering`), required by the runner. Astronomy is checked against published values (Meeus examples, equinox, a known natal chart, planets at J2000 and sign ingresses); the zone converter against known offsets including DST gap/overlap; `Request` against bad/tampered input; `layering` greps the pure code for I/O and the templates for unescaped output or inline script/style. The web layer is verified with `php -S localhost:8081 -t public` and curl/browser.

## 9. Deployment (Apache shared hosting)

1. Create a domain/subdomain with PHP ≥ 8.1 and set its **web directory to `<project>/public`** (Panel → Domains → Manage Websites → Edit). *Alternative:* leave the web directory at the project root; the root `.htaccess` then serves `public/` as the site (verified live).
2. Upload the whole project (SFTP/rsync/git) so `src/`, `templates/`, `cache/` sit next to `public/`.
3. Ensure `cache/` is writable by the PHP user (`chmod 775 cache`).
4. Visit the site; no configuration needed. (When MySQL is introduced: create the DB in the panel and put credentials in an untracked `config.php`.)

### Automated deploy

`scripts/deploy.sh [--all] [--dry-run]` wraps the `sftp-upload` skill (`~/.claude/skills/sftp-upload`). Target settings come from the gitignored `.deploy.local` (template: `.deploy.local.example`); credentials live outside the repo in `~/.password`. Behaviour:

- Refuses to run with uncommitted tracked changes and runs `php tests/run.php` first; failing tests abort the deploy.
- Uploads only committed files changed since the last deployed commit (recorded in the gitignored `.deploy-state`); `--all` uploads every tracked file.
- Cannot delete remote files: removed files are listed as warnings and must be deleted manually.
- Run by the `deployer` agent as the last step of `/new-feature`. Host, domain and provider names never appear in the repo.

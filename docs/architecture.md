# Architecture

> Status: v0.2 (PHP) · Owner of this file: the `documenter` agent (`.claude/agents/documenter.md`).

## 1. Goal

Arcana is a web page for magic lovers. The visitor enters **birth date**, **birth time (hh:mm)** and **birth city**, and receives:

| Result | Meaning | Depends on |
|---|---|---|
| Sun sign | zodiac star sign | date + time (cusp days) |
| Ascendant (rising sign) | sign on the eastern horizon at birth | date, time, **latitude, longitude** |
| Moon sign | sign the Moon occupied | date + time |

Long-term ambition: the most-used page for magic lovers, so adding features (planets, houses, tarot, compatibility, sharing…) must be cheap.

## 2. Constraints & key decisions

| # | Decision | Why | Trade-off |
|---|---|---|---|
| D1 | **PHP 8.1+, server-rendered, deployable on DreamHost shared hosting** (Apache + PHP, FTP/SFTP/git deploy). No Composer, no build step, no framework | Hard requirement (hosting). Nothing to install on the server | We write a tiny autoloader/router ourselves |
| D2 | **Database: MySQL only, and only when a feature needs persistence. v0.2 uses none** | Nothing in the big-three flow needs storing; fewer moving parts, no personal data at rest | Features like accounts/saved charts/share-by-id need an ADR + schema (see §7) |
| D3 | **Astronomy implemented in-house in PHP** (`Arcana\Astro\*`, Meeus low-precision series) | No dependency (no Swiss Ephemeris binary on shared hosting), testable. Sun/Moon ≈ 0.01° | Moon sign wrong only within ~0.01° of a sign boundary |
| D4 | **Tropical zodiac** | The Western standard users expect | Sidereal could be added later |
| D5 | **Geocoding via Open-Meteo** (lat, lon, IANA time zone), called **server-side**, results cached on disk in `cache/`, bundled fallback list if the API is down | One call yields all place data; server-side call keeps visitors' queries out of third-party JS and allows caching | Needs outbound HTTP (`curl` or `allow_url_fopen`, both normally on at DreamHost) |
| D6 | **Time zones via PHP's `DateTimeZone`** | Correct historical DST/offsets from the tz database, no library | Very old dates use LMT as in the tz database |
| D7 | **Pure core, thin web layer** | `Arcana\Astro`, `Chart`, `Time` have no I/O and are unit-tested from the CLI | — |
| D8 | **Progressive enhancement**: the page works without JavaScript (server resolves the city text); JS only adds city autocomplete | Robust, SEO-friendly, shareable GET URLs | — |
| D9 | **Agentic dev workflow** in `.claude/` | See §6 | — |

## 3. System overview

```
 Browser ── GET /?date&time&city[&lat&lon&tz] ──► public/index.php
    │                                                 │
    │  assets/autocomplete.js                         ├─ Request::parse   validate input; geocode city if lat/lon/tz missing
    │      └─ GET api/cities.php?q= ──► Geocoder ─────┤        └─ Geo\Geocoder ─► Open-Meteo API (cache/ on disk,
    │                                                 │                           Geo\FallbackCities if unreachable)
    │                                                 ├─ Chart::compute  ◄── the core
    │                                                 │     ├─ Time\Zone          wall-clock → UTC (DateTimeZone)
    │                                                 │     ├─ Astro\Angles       JD, centuries, nutation, obliquity
    │                                                 │     ├─ Astro\Sun / Moon / Ascendant
    │                                                 │     └─ Astro\Zodiac       longitude → sign
    │                                                 └─ templates/home.php  (+ Content\Signs copy) → HTML
```

Layering rule (checked by `reviewer`): `Astro\*`, `Time\*` and `Chart` never do I/O (no network, files, `$_GET`, echo). Only `public/` and `Request`/`Geo` touch the outside world. Templates only render; they escape everything with `e()`.

## 4. Calculation pipeline

1. **Input** — `year, month, day, hour, minute` (wall-clock at birthplace) + `lat, lon (east+), timeZone`.
2. **UTC** — `Zone::toUnix` via `DateTimeImmutable` in the IANA zone. DST gap → shifted forward; overlap → first occurrence.
3. **Julian Day** — `Angles::julianDay(unix)`; UT used as TT (≤ 0.01° effect on the Moon).
4. **Sun** — apparent longitude (Meeus ch. 25). **Moon** — 38 largest terms of Meeus ch. 47 + nutation.
5. **Ascendant** — `RAMC = GMST + lon`; `ASC = atan2(cos RAMC, −(sin RAMC·cos ε + tan φ·sin ε))`.
6. **Sign** — `floor(lon/30)`; remainder → degree/minute.

Limits: Ascendant flagged approximate beyond ±66° latitude; no houses yet.

## 5. Security & privacy

- All user input validated in `Request::parse` (date/time regex + `checkdate`, lat/lon ranges, `tz` checked against `DateTimeZone::listIdentifiers()`); output escaped with `e()`.
- Outbound requests go only to the fixed Open-Meteo host; the query is URL-encoded.
- Only the **city text** is sent to a third party. Birth data is processed per request and **not stored** (no DB, no logs of inputs by the app). Note that the shareable GET URL contains the birth data, so web-server access logs may record it — mention in any privacy policy.
- `src/`, `templates/`, `cache/`, `tests/`, `docs/` live **outside** the web root (`public/` is the document root); `cache/` also has a deny `.htaccess`.
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
```

Agents: `.claude/agents/`; orchestration: `.claude/commands/new-feature.md`; rules: `CLAUDE.md`. `docs/` is the shared memory. ADRs: `docs/decisions/` (0001 records the move to PHP).

## 7. Extension points

| To add… | Touch |
|---|---|
| A body (Mercury…Pluto) | new `src/Astro/<Body>.php`, call it in `Chart::compute`, add role copy in `Content\Signs::ROLES`, add card in `templates/home.php` |
| Houses | new `src/Astro/Houses.php` reusing `Angles`/`Ascendant::gmst` |
| Copy / languages | `src/Content/` only |
| Persistence (saved/shared charts, accounts, caching city lookups in SQL) | **MySQL** via PDO: add `src/Db.php` (config from an untracked `config.php`, DreamHost MySQL host/user/password), SQL in `migrations/NNN_*.sql`, an ADR superseding D2. Never store birth data without an explicit privacy decision |
| New page/route | new file in `public/` (plain PHP entry scripts, no router yet) + template in `templates/` |

## 8. Testing

`php tests/run.php` — dependency-free runner (exit code ≠ 0 on failure). Astronomy is checked against published values (Meeus examples, equinox, a known natal chart); the zone converter against known offsets including DST gap/overlap; `Request` against bad/tampered input. The web layer is verified with `php -S localhost:8081 -t public` and curl/browser.

## 9. Deployment (DreamHost)

1. Create a domain/subdomain with PHP ≥ 8.1 and set its **web directory to `<project>/public`** (Panel → Domains → Manage Websites → Edit).
2. Upload the whole project (SFTP/rsync/git) so `src/`, `templates/`, `cache/` sit next to `public/`.
3. Ensure `cache/` is writable by the PHP user (`chmod 775 cache`).
4. Visit the site; no configuration needed. (When MySQL is introduced: create the DB in the panel and put credentials in an untracked `config.php`.)

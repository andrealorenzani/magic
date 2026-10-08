# Code navigation guide

Where things are. For the *why*, read [architecture.md](architecture.md).

## Repository map

```
/.htaccess                   Only used when the web dir is the project root: 301 /public/… → /…, rewrites all else into public/, 404 for config.php, migrations/, scripts/
/config.php.example          Tracked template for the gitignored config.php (MySQL host, port, name, user, password)
/config.php                  (gitignored) real DB settings, project root, outside public/; made by scripts/make-config.sh
public/                      ← web root (the server's "web directory")
  index.php                  Front controller: mode → Request::parse|parseLove → SelfReading|LoveReading::build → templates/home.php; sets noindex + no-store on results; after rendering a result, flushes and writes the audit record (errors swallowed)
  api/cities.php             JSON city autocomplete endpoint (?q=…) backed by Geocoder
  assets/styles.css          All styling (dark/starry theme, element colours, bio chart classes, @media print)
  assets/autocomplete.js     Progressive enhancement: suggestions + fills hidden lat/lon/tz, one instance per [data-place] container
  assets/print.js            Un-hides the Print button and calls window.print()
  .htaccess                  Apache hardening + CSP + caching
src/
  autoload.php               PSR-4 style autoloader for namespace Arcana\ (no Composer)
  bootstrap.php              Loads autoloader, defines ARCANA_ROOT and the e() escape helper
  Chart.php                  ★ Chart::compute (big three), Chart::full (+ planets, node), Chart::partial (optional time/place)
  Request.php                mode(), parseToday(), parse() (Self), parseLove(), parsePerson(): validated input / errors / notes
  SelfReading.php            Pure: SelfReading::build($input, $today) → Self view-model
  LoveReading.php            Pure: LoveReading::build($a, $b, $today) → Love view-model
  Astro/Angles.php           rad, norm360, julianDay, centuries, nutationLongitude, obliquity
  Astro/Sun.php              Sun::longitude($jd)
  Astro/Moon.php             Moon::longitude($jd) — periodic-term table
  Astro/Ascendant.php        Ascendant::gmst($jd), Ascendant::longitude($jd, $lat, $lon)
  Astro/Zodiac.php           Zodiac::SIGNS, Zodiac::fromLongitude($lon)
  Astro/Planets.php          Mercury–Pluto from JPL Keplerian elements: supports($jd), longitude($id,$jd), all($jd), kepler()
  Astro/MeanNode.php         MeanNode::longitude($jd) — mean North node (Meeus 47.7)
  Bio/Biorhythm.php          dayNumber/date/dayOf, value, forPerson (3 cycles, peaks, 30-day curve)
  Bio/Synchrony.php          Synchrony::pair — per-cycle delta/sync/amplitude/combined peaks, overall, band
  Love/NameAffinity.php      AMORE name affinity: compute, fromCounts, step, normalize
  Love/SignAffinity.php      Sign-vs-sign scoring: rank($refs) → ranking, mostAffine (3), soulmate
  Love/Common.php            Common::between($chartA, $chartB) — shared Sun/Moon/Ascendant values
  Tarot/Reading.php          Reading::draw($seed, $today, 3) — deterministic, stateless
  Audit/Yaml.php             Pure: Yaml::dump(array): string — small deterministic block-style emitter, strict quoting, fixed [a-z][a-z0-9_]* keys
  Audit/AuditRecord.php      Pure: fromSelf / fromLove → record (persons + response_yaml), FORMAT_VERSION, MAX_YAML_BYTES (64 KiB, else a `truncated: true` document)
  Db/Config.php              I/O: Config::load($path): ?array — reads config.php, null if missing/invalid, silent
  Db/Connection.php          I/O: Connection::open($cfg): PDO — utf8mb4, exceptions, 2 s connect timeout, no emulated prepares
  Db/AuditLog.php            I/O: AuditLog::tryWrite(?$configPath, $record): bool — one transaction, prepared statements, never throws
  Time/Zone.php              Zone::toUnix(y,m,d,h,i,$tz), Zone::isValid($tz)
  Geo/Geocoder.php           Open-Meteo search + disk cache + fallback; Geocoder::label()
  Geo/FallbackCities.php     Bundled major cities, search()
  Content/Signs.php          Signs::TEXT (per sign) and Signs::ROLES (sun/asc/moon copy)
  Content/Bodies.php         Bodies::INFO — glyph, title, tagline, meaning per planet/node
  Content/Traits.php         Keywords per sign/element/modality, complements, levels, aspects, biorhythm VERDICTS
  Content/TarotDeck.php      TarotDeck::CARDS — 22 Major Arcana with upright/reversed love text
templates/home.php           Page shell: hero, mode chooser, form for the mode, includes the result template; escapes via e()
templates/self-result.php    Self result: big three, planets, affinities, biorhythms, print button
templates/love-result.php    Love result: name %, synchrony, common values, tarot, print button
templates/partials/          person-fields.php (date/time/city fields per prefix), icons.php (static SVG), bio.php (curve chart)
migrations/001_create_magic_audit.sql   Idempotent schema for magic_audit and magic_audit_person (CREATE TABLE IF NOT EXISTS)
cache/                       Geocoding cache (writable, denied from web; not in web root)
tests/run.php                Dependency-free test runner (core checks), requires tests/cases/*.php
tests/cases/                 planets.php, bio.php, love.php, tarot.php, request.php, layering.php (ADR 0002 checks), audit.php (ADR 0003: Yaml, AuditRecord, DB failure isolation)
scripts/deploy.sh            Tests, then uploads committed files changed since last deploy via the sftp-upload skill
scripts/make-config.sh       Writes config.php (mode 600) from the database section of ~/.password; prints only "config.php written"
scripts/db-migrate.sh        Applies migrations/*.sql in order with the mysql client (password via a temp option file, never on the command line)
scripts/db-purge.sh          Manual retention: `--days N` deletes audit rows older than N days (cascade removes persons)
scripts/lib/dbcred.sh        Sourced helper: load_db_credentials, write_mysql_defaults, run_mysql (stderr hidden, only `mysql error <code> (<SQLSTATE>)`)
.deploy.local.example        Template for the gitignored .deploy.local (host, remote dir, DB_PASSWORD_SECTION); credentials in ~/.password
.deploy-state                (gitignored) last deployed commit SHA
.deploy-config-hash          (gitignored) sha256 of the last uploaded config.php
docs/                        architecture.md, code.md, roadmap.md, decisions/ (ADRs)
.claude/agents/              architect, implementer, reviewer, documenter, deployer
.claude/commands/            new-feature.md (workflow entry point)
CLAUDE.md                    Rules for AI agents
```

## Data shapes

```php
// Chart::compute(int $year, int $month, int $day, int $hour, int $minute, float $lat, float $lon, string $timeZone)
['sun' => $pos, 'moon' => $pos, 'ascendant' => $pos, 'utc' => 'Y-m-d H:i', 'polar' => bool]
// Chart::full(...same args) = compute + :
['planetsSupported' => bool,                       // 1800..2100
 'planets' => ['mercury' => ['position' => $pos, 'retrograde' => bool], ... 'pluto' => ...],   // [] when unsupported
 'node' => $pos]                                   // mean North Node, any year
// Chart::partial(y, m, d, ?h, ?mi, ?lat, ?lon, ?tz)
['sun' => ?$pos, 'moon' => ?$pos, 'ascendant' => ?$pos, 'approx' => ['sun' => bool, 'moon' => bool]]
// $pos (Zodiac::fromLongitude)
['id','name','symbol','element','modality','index','longitude','degree','minute']
// city (Geocoder / FallbackCities)
['name','admin','country','lat','lon','timeZone']

// Request::parse (Self) → ['input' => ['year','month','day','hour','minute','lat','lon','tz','city'] | null, 'errors', 'notes']
// Request::parseLove → ['a' => $person|null, 'b' => $person|null, 'errors', 'notes']; errors prefixed "You: " / "Loved person: "
$person = ['name' => string, 'date' => ?['year','month','day'], 'time' => ?['hour','minute'], 'place' => ?['lat','lon','tz','city']]

// SelfReading::build → ['chart' => Chart::full, 'refs' => ['sun','moon','ascendant'(,'venus','mars') => sign index],
//   'affinity' => SignAffinity::rank, 'bio' => Biorhythm::forPerson, 'today', 'notes']
// LoveReading::build → ['names','affinity' => NameAffinity::compute, 'charts' => ['a','b' => partial|null],
//   'common' => Common::between|null, 'bio' => Synchrony::pair|null, 'tarot' => Reading::draw, 'today', 'notes']
// NameAffinity::compute → ['counts' (A,M,O,R,E), 'slots', 'start', 'chain', 'percent', 'noLetters']
// Synchrony::pair → ['cycles' => [physical|emotional|intellectual => delta, sync, amplitude, flat, peaksTogether,
//   nextCombinedPeak/Trough, nextBothHigh/Low, curveA/B], 'overall' => float, 'band' => tune|complementary|apart]
// Reading::draw → list of ['date','card','reversed','meaning']

// AuditRecord::fromSelf($input, $selfView, $today) / fromLove($a, $b, $loveView, $today, ?$self = null) → record
['functionality' => 'self'|'love', 'on_date' => 'YYYY-MM-DD' (UTC reading date or on=), 'format_version' => 1,
 'persons' => list of ['role' => 'self'|'user'|'loved', 'name', 'birth_date' => ?'Y-m-d', 'birth_time' => ?'HH:MM:SS',
                       'place_label' => ?string(<=255), 'lat' => ?float(5 dp), 'lon' => ?float, 'tz' => ?string],
 'response_yaml' => string]
// Self: one person, role self (name '' : the Self form has no name field; time defaults to 12:00 when not given).
// Love: user = A, loved = B (B's null fields when not entered; time stored only if date+place are present); `self` only if passed.
// Yaml::dump(array): string — nulls/bools/ints/floats (4 dp), strings plain only if simple else double-quoted, empty arrays as [].
```

Tables (`migrations/001_create_magic_audit.sql`, InnoDB, utf8mb4):

```
magic_audit         id BIGINT UNSIGNED PK AI · created_at DATETIME (UTC, UTC_TIMESTAMP()) · functionality VARCHAR(16) 'self'|'love'
                    · on_date DATE · format_version TINYINT UNSIGNED (default 1) · response_yaml MEDIUMTEXT      (idx: created_at, functionality)
magic_audit_person  id BIGINT UNSIGNED PK AI · audit_id → magic_audit.id ON DELETE CASCADE · role VARCHAR(8) · name VARCHAR(40)
                    · birth_date DATE NULL · birth_time TIME NULL · place_label VARCHAR(255) NULL · lat/lon DECIMAL(8,5) NULL
                    · tz VARCHAR(64) NULL                                                   (unique: audit_id+role; idx: name)
```

YAML summary (`format_version` 1; keys are code constants). Self: `functionality`, `on_date`, `chart` (`utc`, `polar`, `sun`/`ascendant`/`moon` as `{sign, degree, minute}`, `planets_supported`, `planets.<body>` with `retrograde`, `north_node`), `affinity` (`most_affine`, `soulmate`), `biorhythm` (`physical`, `emotional`, `intellectual`), `notes`. Love: `functionality`, `on_date`, `name_affinity` (`percent`, `counts` a/m/o/r/e, `chain`), `charts.a|b` (`sun`, `moon`, `ascendant`, `sun_approx`, `moon_approx`; null when no chart), `common.score`, `synchrony` (`overall`, `band`), `tarot` (list of `date`, `card`, `reversed`), `notes`.

## Request flow

Self: `GET /?mode=self&date=1990-07-15&time=08:30&city=Rome&lat=41.9&lon=12.5&tz=Europe/Rome` (a missing `mode` with `date`/`city` is Self, so old links work)
→ `public/index.php` → `Request::mode`, `parseToday` → `Request::parse` (if `lat/lon/tz` missing, `Geocoder::search(city)[0]`) → `SelfReading::build` → `Chart::full` (`Zone::toUnix` → `Angles::julianDay` → `Sun/Moon/Ascendant/Planets/MeanNode`, `Zodiac::fromLongitude`) + `SignAffinity::rank` + `Biorhythm::forPerson` → `templates/home.php` → `self-result.php`.

Love: `GET /?mode=love&a_name=…&a_date=…&a_time=…&a_city=…&b_name=…[&b_date&b_time&b_city]` (prefixes `a_` = you, `b_` = loved person; each also accepts `_lat/_lon/_tz`)
→ `Request::parseLove` → `LoveReading::build` → `Chart::partial` ×2, `NameAffinity`, `Synchrony`, `Common`, `Tarot\Reading` → `love-result.php`.

Audit: after `templates/home.php` is rendered, and only when `$view !== null` (a result), `public/index.php` calls `ignore_user_abort(true)`, `set_time_limit(10)`, `fastcgi_finish_request()` (else `flush()`), builds the record (`AuditRecord::fromSelf|fromLove`) and calls `AuditLog::tryWrite(getenv('ARCANA_CONFIG') ?: ARCANA_ROOT.'/config.php', $record)`. Missing config, missing PDO, connection or SQL errors return false; only `audit: write failed <Class> <code>` (or `audit: build failed <Class>`) is logged. `ARCANA_CONFIG` exists so tests can point to another file.

`on=YYYY-MM-DD` overrides "today" (default: server UTC date). No `mode` and no input shows the mode chooser.

Autocomplete: `autocomplete.js` → `GET api/cities.php?q=par` → `Geocoder::search` → JSON.

## Recipes

- **Run locally:** `php -S localhost:8081 -t public` · **Test:** `php tests/run.php` (also loads `tests/cases/*.php`; no per-file runner, all checks run every time)
- **Reproducible readings:** add `&on=2026-10-09` to a URL to fix "today" (biorhythms, tarot).
- **Add a planet-like body:** compute it in `Chart::full` → copy in `Content\Bodies::INFO` → show in `templates/self-result.php` → reference-value test in `tests/cases/planets.php`.
- **Add a tarot card or change its text:** `Content\TarotDeck::CARDS` (the draw uses `count()`; update the golden values in `tests/cases/tarot.php`).
- **Tune affinity scoring:** constants in `Love\SignAffinity`; hand-computed expectations in `tests/cases/love.php`.
- **Add a field to the audit:** put it in `AuditRecord` (a YAML key in the `$doc` of `fromSelf`/`fromLove`, or a person field), bump `FORMAT_VERSION` if the YAML layout changes; for a new column add an idempotent `migrations/NNN_*.sql` (guarded `ALTER`) and extend the INSERT in `Db\AuditLog`; update `tests/cases/audit.php`.
- **Set up / migrate the database:** `scripts/make-config.sh` (or copy `config.php.example` to `config.php` and edit), then `scripts/db-migrate.sh`. If the database server refuses your machine, paste `migrations/001_create_magic_audit.sql` into the hosting panel's SQL tool.
- **Purge old audit rows (manual):** `scripts/db-purge.sh --days 90`.
- **Query the audit (mysql client, parameters are examples):**
  - latest results: `SELECT id, created_at, functionality FROM magic_audit ORDER BY id DESC LIMIT 20;`
  - people of a request: `SELECT role, name, birth_date, place_label FROM magic_audit_person WHERE audit_id = 42;`
  - by name: `SELECT a.id, a.created_at FROM magic_audit a JOIN magic_audit_person p ON p.audit_id = a.id WHERE p.name = 'Ada';`
  - erase a person on request: `DELETE FROM magic_audit WHERE id IN (SELECT audit_id FROM magic_audit_person WHERE name = 'Ada');` (the cascade removes the person rows; run `SELECT` first to check what matches).
- **Add a mode:** see architecture.md §7.
- **Change copy:** `src/Content/Signs.php`. **Change look:** `public/assets/styles.css` (tokens in `:root`).
- **Wrong sign?** Call `Chart::compute` from CLI with the same input; check `utc` first (time-zone issues are the usual cause), then compare longitudes with an ephemeris.
- **Geocoding stale/broken:** delete `cache/geo-*.json`.

## Conventions

PHP 8.1+, `declare(strict_types=1)`, namespace `Arcana\`, one class per file named after the file. Degrees at API boundaries, longitudes east-positive. No Composer/dependencies. No inline `<script>`/`style` (CSP). Escape every template output with `e()`.

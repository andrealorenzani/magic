# Code navigation guide

Where things are. For the *why*, read [architecture.md](architecture.md).

## Repository map

```
/.htaccess                   Only used when the web dir is the project root: 301 /public/… → /…, rewrites all else into public/, 404 for config.php, migrations/, scripts/
/config.php.example          Tracked template for the gitignored config.php (MySQL host, port, name, user, password)
/config.php                  (gitignored) real DB settings, project root, outside public/; made by scripts/make-config.sh
public/                      ← web root (the server's "web directory")
  consent.php                POST accept/withdraw the Terms and Conditions: sets/clears cookie magic_terms (Secure also behind a proxy), 303 to ./ (accept carries the sanitised original query; withdraw goes to ./?withdrawn=1)
  index.php                  Front controller: consent check (without cookie nothing is processed) → mode → Request::parse|parseLove → SelfReading|LoveReading::build → templates/home.php; sends no-store + `Vary: Cookie` on every response and noindex on results; reads `t=` and `noaudit`; builds the Share model (frozen/live link, QR); after rendering a result, flushes and writes the audit record (errors swallowed)
  api/cities.php             JSON city autocomplete endpoint (?q=…) backed by Geocoder
  assets/styles.css          All styling (dark/starry theme, element colours, bio chart classes, @media print)
  assets/autocomplete.js     Progressive enhancement: suggestions + fills hidden lat/lon/tz, one instance per [data-place] container
  assets/print.js            Un-hides the Print button and calls window.print()
  assets/share.js            Un-hides the Copy link / Share buttons (clipboard, Web Share); the section works without it
  .htaccess                  Apache hardening + CSP (incl. frame-ancestors, form-action, base-uri) + caching
src/
  autoload.php               PSR-4 style autoloader for namespace Magic\ (no Composer)
  bootstrap.php              Loads autoloader, defines MAGIC_ROOT and the e() escape helper
  Chart.php                  ★ Chart::compute (big three), Chart::full (+ planets, node), Chart::partial (optional time/place)
  Consent.php                Consent::COOKIE/VALUE/LIFETIME, given($cookies), safeQuery($q) — T&Cs cookie name and safe redirect query
  Http.php                   Pure helpers on a passed-in server array: isSecure (HTTPS or forwarded proto), basePath, origin (validated Host)
  Request.php                mode(), parseToday(), noAudit(), parseTarot() (validates `t`), parse() (Self), parseLove(), parsePerson(): validated input / errors / notes; lat/lon rounded to 5 dp
  SelfReading.php            Pure: SelfReading::build($input, $today) → Self view-model
  LoveReading.php            Pure: LoveReading::build($a, $b, $today, ?$tarotSlots = null) → Love view-model
  Astro/Angles.php           rad, norm360, julianDay, centuries, nutationLongitude, obliquity
  Astro/Sun.php              Sun::longitude($jd)
  Astro/Moon.php             Moon::longitude($jd)
  Astro/Ascendant.php        Ascendant::gmst($jd), Ascendant::longitude($jd, $lat, $lon)
  Astro/Zodiac.php           Zodiac::SIGNS, Zodiac::fromLongitude($lon)
  Astro/Planets.php          Mercury–Pluto (1800–2100, approximate): supports($jd), longitude($id,$jd), all($jd)
  Astro/MeanNode.php         MeanNode::longitude($jd) — mean North Node
  Bio/Biorhythm.php          dayNumber/date/dayOf, value, forPerson (3 cycles, peaks, 30-day curve)
  Bio/Synchrony.php          Synchrony::pair — per-cycle delta/sync/amplitude/combined peaks, overall, band
  Love/NameAffinity.php      Name affinity percentage: compute($nameA, $nameB)
  Love/SignAffinity.php      Sign-vs-sign scoring: rank($refs) → ranking, mostAffine (3), soulmate
  Love/Common.php            Common::between($chartA, $chartB) — shared Sun/Moon/Ascendant values
  Tarot/Reading.php          Reading::spread($seed, $today) — deterministic Past/Present/Future, 3 distinct cards; fromSlots() for a validated shared spread
  Share/ShareLink.php        Pure: self/love/liveSelf/liveLove (canonical query strings), tarotCode/parseTarot, MAX_URL_FOR_QR (520), trimCity
  Share/Qr.php               Pure QR code generator: encode($data): ?modules (null if over 520 bytes), path($modules) for SVG, capacity(); generated in pure PHP
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
  Content/Traits.php         Keywords per sign/element/modality, complements, levels, aspects, biorhythm VERDICTS, COMMON_VERDICTS, COMMON_MEANING, level pill labels
  Content/TarotDeck.php      TarotDeck::CARDS — 22 Major Arcana with upright/reversed love text (kept as the card's short essence line)
  Content/TarotSpread.php    ORDER, POSITIONS (title, question, intro), text($cardId, $position, $reversed) — throws on a missing key
  Content/TarotPast.php      TEXT: 22 cards x up/rev, Past position
  Content/TarotPresent.php   same, Present position
  Content/TarotFuture.php    same, Future position
templates/home.php           Page shell: hero, mode chooser, form for the mode, includes the result template; escapes via e()
templates/self-result.php    Self result: big three, planets, affinities, biorhythms, share, print button
templates/love-result.php    Love result: name %, compact synchrony, common values, Past/Present/Future spread, share, print button
templates/partials/          person-fields.php (date/time/city fields per prefix), icons.php (static SVG), bio.php (curve chart), terms.php (T&Cs text, used by the popup and the end-of-page section), share.php (Share section: links, QR, buttons), qr.php (qr_svg())
docker-compose.yml           Local run: web (PHP + Apache, bind mount, 127.0.0.1:8081) and db (MySQL 8.4, volume dbdata, migrations/ as initdb, not published)
docker/Dockerfile            php:8.3-apache + pdo_mysql + rewrite/headers/expires; Apache config allowing the root .htaccess
docker/config.php            DB settings for the Docker setup, read from the container environment (MAGIC_DB_*)
migrations/001_create_magic_audit.sql   Idempotent schema for magic_audit and magic_audit_person (CREATE TABLE IF NOT EXISTS)
cache/                       Geocoding cache (writable, denied from web; not in web root)
tests/run.php                Dependency-free test runner (core checks), requires tests/cases/*.php
tests/cases/                 planets.php, bio.php, love.php, tarot.php, request.php, layering.php (ADR 0002 checks), audit.php (ADR 0003: Yaml, AuditRecord, DB failure isolation), share.php (ADR 0004: QR structure, share-link round trips, noaudit)
scripts/docker-db.sh         Docker DB helper: migrate | shell | query "SQL" | audit [N] | reset (password stays in the container)
scripts/deploy.sh            Tests, then uploads committed files changed since last deploy via the sftp-upload skill
scripts/make-config.sh       Writes config.php (mode 600) from the database section of ~/.password; prints only "config.php written"
scripts/db-migrate.sh        Applies migrations/*.sql in order with the mysql client (password via a temp option file, never on the command line)
scripts/db-purge.sh          Manual retention: `--days N` deletes audit rows older than N days (cascade removes persons)
scripts/lib/dbcred.sh        Sourced helper: load_db_credentials, write_mysql_defaults, run_mysql (stderr hidden, only `mysql error <code> (<SQLSTATE>)`)
.deploy.local.example        Template for the gitignored .deploy.local (host, remote dir, DB_PASSWORD_SECTION); credentials in ~/.password
.deploy-state                (gitignored) last deployed commit SHA
.deploy-config-hash          (gitignored) content hash of the last uploaded config.php
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
//   'common' => Common::between|null, 'bio' => Synchrony::pair|null, 'tarot' => Reading::spread, 'tarotShared' => bool, 'today', 'notes']
// NameAffinity::compute → ['percent', 'noLetters', plus supporting values shown by the template]
// Synchrony::pair → ['cycles' => [physical|emotional|intellectual => delta, sync, amplitude, flat, peaksTogether,
//   nextCombinedPeak/Trough, nextBothHigh/Low, curveA/B], 'overall' => float, 'band' => tune|complementary|apart]
// Reading::spread → list of ['position' => past|present|future,'card','reversed','text']
// Share: ShareLink::love(...) → 'mode=love&a_name=…&on=YYYY-MM-DD&t=16u,5r,9u&noaudit'; t = three slots <0-21><u|r>, distinct numbers;
//   self → 'mode=self&date&time&city&lat&lon&tz&on&noaudit'; live links omit on and t. Invalid t: ignored, note shown.

// AuditRecord::fromSelf($input, $selfView, $today) / fromLove($a, $b, $loveView, $today, ?$self = null) → record
['functionality' => 'self'|'love', 'on_date' => 'YYYY-MM-DD' (UTC reading date or on=), 'format_version' => 2,
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

YAML summary (`format_version` 2; keys are code constants). Self: `functionality`, `on_date`, `chart` (`utc`, `polar`, `sun`/`ascendant`/`moon` as `{sign, degree, minute}`, `planets_supported`, `planets.<body>` with `retrograde`, `north_node`), `affinity` (`most_affine`, `soulmate`), `biorhythm` (`physical`, `emotional`, `intellectual`), `notes`. Love: `functionality`, `on_date`, `name_affinity` (`percent` and supporting values), `charts.a|b` (`sun`, `moon`, `ascendant`, `sun_approx`, `moon_approx`; null when no chart), `common.score`, `synchrony` (`overall`, `band`), `tarot` (list of `position`, `card`, `reversed`; version 1 rows had `date` instead of `position`), `notes`.

## Request flow

Self: `GET /?mode=self&date=1990-07-15&time=08:30&city=Rome&lat=41.9&lon=12.5&tz=Europe/Rome` (a missing `mode` with `date`/`city` is Self, so old links work)
→ `public/index.php` → `Request::mode`, `parseToday` → `Request::parse` (if `lat/lon/tz` missing, `Geocoder::search(city)[0]`) → `SelfReading::build` → `Chart::full` (`Zone::toUnix` → `Angles::julianDay` → `Sun/Moon/Ascendant/Planets/MeanNode`, `Zodiac::fromLongitude`) + `SignAffinity::rank` + `Biorhythm::forPerson` → `templates/home.php` → `self-result.php`.

Love: `GET /?mode=love&a_name=…&a_date=…&a_time=…&a_city=…&b_name=…[&b_date&b_time&b_city]` (prefixes `a_` = you, `b_` = loved person; each also accepts `_lat/_lon/_tz`)
→ `Request::parseLove` (+ `Request::parseTarot` for `t`) → `LoveReading::build` → `Chart::partial` ×2, `NameAffinity`, `Synchrony`, `Common`, `Tarot\Reading::spread` → `love-result.php`. Both result pages then include `partials/share.php`.

Consent: `public/index.php` reads `Consent::given($_COOKIE)`. Without the cookie `magic_terms` the query is ignored, no result is built and the terms popup is shown (the sanitised original query travels in a hidden field). The popup/section form posts to `public/consent.php`, which sets (accept) or clears (withdraw) the cookie and redirects with 303 to `./`. No consent means no result and no audit record. Every response carries `Cache-Control: private, no-store` and `Vary: Cookie`.

Audit: after `templates/home.php` is rendered, and only when `$view !== null` (a result) and the request has no `noaudit` parameter, `public/index.php` calls `ignore_user_abort(true)`, `set_time_limit(10)`, `fastcgi_finish_request()` (else `flush()`), builds the record (`AuditRecord::fromSelf|fromLove`) and calls `AuditLog::tryWrite(getenv('MAGIC_CONFIG') ?: MAGIC_ROOT.'/config.php', $record)`. Missing config, missing PDO, connection or SQL errors return false; only `audit: write failed <Class> <code>` (or `audit: build failed <Class>`) is logged. `MAGIC_CONFIG` exists so tests can point to another file.

`on=YYYY-MM-DD` overrides "today" (default: server UTC date). No `mode` and no input shows the mode chooser.

Autocomplete: `autocomplete.js` → `GET api/cities.php?q=par` → `Geocoder::search` → JSON.

## Recipes

- **Run locally:** `docker compose up --build` (page at http://localhost:8081, accept the T&Cs in the popup) or `php -S localhost:8081 -t public` · **Test:** `php tests/run.php` (also loads `tests/cases/*.php`; no per-file runner, all checks run every time)
- **Reproducible readings:** add `&on=2026-10-09` to a URL to fix "today" (biorhythms, tarot); add `&noaudit` to skip the audit write. Shared links carry both (plus `t=` in Love).
- **Add a planet-like body:** compute it in `Chart::full` → copy in `Content\Bodies::INFO` → show in `templates/self-result.php` → reference-value test in `tests/cases/planets.php`.
- **Add a tarot card or change its text:** `Content\TarotDeck::CARDS` for the card and its essence line, and its up/rev entries in `Content\TarotPast`, `TarotPresent`, `TarotFuture` (a missing key throws; update the goldens in `tests/cases/tarot.php`). `t=` accepts card numbers 0-21 only.
- **Make a new output shareable:** classify it as derived or encoded (CLAUDE.md rule); if encoded, add it to `Share\ShareLink` and `Request`, and a round-trip test in `tests/cases/share.php`.
- **Tune affinity scoring:** constants in `Love\SignAffinity`; expectations in `tests/cases/love.php`.
- **Add a field to the audit:** put it in `AuditRecord` (a YAML key in the `$doc` of `fromSelf`/`fromLove`, or a person field), bump `FORMAT_VERSION` if the YAML layout changes; for a new column add an idempotent `migrations/NNN_*.sql` (guarded `ALTER`) and extend the INSERT in `Db\AuditLog`; update `tests/cases/audit.php`.
- **Set up / migrate the database:** `scripts/make-config.sh` (or copy `config.php.example` to `config.php` and edit), then `scripts/db-migrate.sh`. If the database server refuses your machine, paste `migrations/001_create_magic_audit.sql` into the hosting panel's SQL tool.
- **Docker database:** `scripts/docker-db.sh migrate` (re-apply migrations), `audit [N]` (latest results), `query "SELECT ..."`, `shell`, `reset` (wipes the volume and starts again). The schema is applied automatically on the first start.
- **Change the T&Cs text:** `templates/partials/terms.php` (one place, used by the popup and the end-of-page section).
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

PHP 8.1+, `declare(strict_types=1)`, namespace `Magic\`, one class per file named after the file. Degrees at API boundaries, longitudes east-positive. No Composer/dependencies. No inline `<script>`/`style` (CSP). Escape every template output with `e()`.

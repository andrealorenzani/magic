# Code navigation guide

Where things are. For the *why*, read [architecture.md](architecture.md).

## Repository map

```
/.htaccess                   Only used when the web dir is the project root: 301 /public/… → /…, rewrites all else into public/, 404 for config.php, migrations/, scripts/
/config.php.example          Tracked template for the gitignored config.php (MySQL host, port, name, user, password)
/config.php                  (gitignored) real DB settings, project root, outside public/; made by scripts/make-config.sh
public/                      ← web root (the server's "web directory")
  consent.php                POST accept/withdraw the Terms and Conditions: sets/clears cookie magic_terms (Secure also behind a proxy), 303 to ./ (accept carries the sanitised original query; withdraw goes to ./?withdrawn=1; the `clean` action and ./?cleaned=1 still exist but no UI uses them)
  hidden.php                 POST only (needs the Terms cookie): validates the sender's details, returns JSON {ok, link, qr:{size,path}|null, tooLong} or {ok:false, error}; relative link and no QR if the origin is unknown; stores, logs and audits nothing
  index.php                  Front controller: consent check (without cookie nothing is processed) → mode → Request::parse|parseLove → SelfReading|LoveReading::build → templates/home.php; sends no-store + `Vary: Cookie` on every response and noindex on results; reads `t=` and `noaudit`; reads the clock once (`time()`), decodes a `c=` short code and merges it over the query (the code wins), resolves `h`/`import` and `nick` (hidden person: merged for parsing only, labelled with the nickname or "Your match", no share section, audited with the hidden marker unless `noaudit`; with `mode=self` a valid `h` only becomes `$pendingHidden`), handles `mode=friends` (panel only, no parse, no audit), computes the lock state (`$aKnown`, `$loveLink`), builds the Share model (frozen/live short links, QR); after rendering a result, flushes and writes the audit record (errors swallowed)
  api/cities.php             JSON city autocomplete endpoint (?q=…) backed by Geocoder
  assets/styles.css          All styling (dark/starry theme, element colours, bio chart classes, @media print)
  assets/autocomplete.js     Progressive enhancement: suggestions + fills hidden lat/lon/tz, one instance per [data-place] container
  assets/memory.js           Browser memory: the visitor's own details incl. the Self name (`localStorage` keys `magic.me.v1`, `magic.loved.v1`, `magic.friends.v1`), saved people, the Friends list (max 60, strict code shape, search/rename/remove/bulk remove, Compare links), Self buttons (Save the data, Clear data, Generate hidden data code which dispatches `magic:share-hidden`), unlocking of Soul Affinity/Friends, filling the carried `a_*` inputs, the shared confirm dialog, adding a pending/imported friend once; two non-identifying `sessionStorage` flags; the only file that touches storage; validates on read; no network, no HTML injection
  assets/print.js            Un-hides the Print button and calls window.print()
  assets/share.js            Un-hides the Copy link / Share buttons (clipboard, Web Share), one clipboard routine with fallbacks, click/keyboard copy on QR blocks ("Link copied"), the hidden-link flow (listens for `magic:share-hidden`, POSTs to `hidden.php`, builds the QR with DOM methods); never touches storage
  assets/import.js           Soul Affinity form: un-hides "Scan a QR code" when `BarcodeDetector` and the camera exist, fills the `import` input, stops the camera on success/Esc/Stop/page hide; no network, no storage
  assets/help.js             Un-hides the "?" buttons; toggles popovers (click/Enter/Space, one open at a time, Esc, outside click); no network, no storage
  .htaccess                  Apache hardening + CSP (incl. frame-ancestors, form-action, base-uri) + caching
src/
  autoload.php               PSR-4 style autoloader for namespace Magic\ (no Composer)
  bootstrap.php              Loads autoloader, defines MAGIC_ROOT and the e() escape helper
  Chart.php                  ★ Chart::compute (big three + midheaven), Chart::full (+ planets, node, houses), Chart::partial (optional time/place), Chart::longitudes (bodies a person has, for synastry)
  ChartWheel.php             Pure: ChartWheel::layout($chart) → drawing data for the wheel (SIZE, radii, MIN_SEPARATION), spread(), point()
  Consent.php                Consent::COOKIE/VALUE ('4')/ACTIONS (accept, withdraw, clean)/LIFETIME, given($cookies), safeQuery($q) — T&Cs cookie name and safe redirect query
  Http.php                   Pure helpers on a passed-in server array: isSecure (HTTPS or forwarded proto), basePath, origin (validated Host)
  Request.php                MODES (self, love, friends), mode(), nickname($q) → {nick, invalid} (local label for a hidden person), parseToday(), resolveToday() (reading day), dayBasis(), noAudit(), hiddenCode($q) → {code, person, invalid} (`h` wins over `import`), parseTarot() (validates `t`), parse() (Self, optional `name` → `input.name`), parseLove(), parsePerson() (incl. optional `pos_*` current position): validated input / errors / notes; lat/lon rounded to 5 dp
  SelfReading.php            Pure: SelfReading::build($input, $today, $dayBasis) → Self view-model
  LoveReading.php            Pure: LoveReading::build($a, $b, $today, ?$tarotSlots = null, $dayBasis = 'utc') → Love view-model; person B may carry `label` (shown instead of the name) and `anonymous` (no name shared) → `view['hidden']`
  Astro/Angles.php           rad, norm360, julianDay, centuries, nutationLongitude, obliquity
  Astro/Sun.php              Sun::longitude($jd)
  Astro/Moon.php             Moon::longitude($jd)
  Astro/Ascendant.php        Ascendant::gmst($jd), Ascendant::longitude($jd, $lat, $lon)
  Astro/Zodiac.php           Zodiac::SIGNS, Zodiac::fromLongitude($lon)
  Astro/Planets.php          Mercury–Pluto (1800–2100, approximate): supports($jd), longitude($id,$jd), all($jd)
  Astro/MeanNode.php         MeanNode::longitude($jd) — mean North Node
  Astro/Houses.php           Houses::midheaven($jd,$lon), midheavenFromRamc(), wholeSign($ascLon,$bodyLon) → house 1-12
  Astro/MoonPhase.php        MoonPhase::at($jd) → angle, illumination, phase; PHASES, MAIN_HALF_WIDTH (10°), name()
  Astro/Aspects.php          Aspects::between($lonA,$lonB) → type + orb or null; TYPES (orb limits pinned by tests)
  Earth/Distance.php         Distance::km(), miles() — straight-line distance
  Earth/Geography.php        Geography::between(), forSelf(), forLove(), formatOffset() — the "Distance and geography" model
  Sky/Today.php              Today::for($day, $sunSigns) → phase, illumination, Moon sign and reading; phaseAt($jd) (also "born under")
  Bio/Biorhythm.php          dayNumber/date/dayOf, value, forPerson (3 cycles, peaks, 30-day curve)
  Bio/Synchrony.php          Synchrony::pair — per-cycle delta/sync/amplitude/combined peaks, overall, band
  Love/NameAffinity.php      Name affinity percentage: compute($nameA, $nameB)
  Love/SignAffinity.php      Sign-vs-sign scoring: rank($refs) → ranking, mostAffine (3), soulmate
  Love/Common.php            Common::between($chartA, $chartB) — shared Sun/Moon/Ascendant values
  Love/Synastry.php          Synastry::between($lonsA, $lonsB, $limit) → rows, counts, approx; ORDER, PERSONAL, MAX_ROWS (12)
  Tarot/Reading.php          Reading::spread($seed, $today) — deterministic Past/Present/Future, 3 distinct cards from 78; fromSlots() for a validated shared spread
  Share/ShareLink.php        Pure: self/love/liveSelf/liveLove (canonical long query strings; Self adds `name` when given), codeQuery(), tarotCode/parseTarot (0-77), MAX_URL_FOR_QR (520), trimCity
  Share/ShareCode.php        Pure: VERSION 1, MAX_CHARS 400, MAX_LABEL_BYTES 32; VERSION_SELF_NAME 2, VERSION_HIDDEN 3; encodeSelf (version 2 only when a name is present, else 1)/encodeLove → code string, decode($code) → long-query array or null (strict, canonical only; rejects version 3), encodeHidden($person), decodeHidden($code) → person or null (version 3 only), extractHidden($text) → code from a pasted link or bare code, cutLabel()
  Share/TimeZoneTable.php    Append-only list of time zones used by short codes (never reorder or remove)
  Share/Qr.php               Pure QR code generator: encode($data): ?modules (null if over 520 bytes), path($modules) for SVG, capacity(); generated in pure PHP
  Audit/Yaml.php             Pure: Yaml::dump(array): string — small deterministic block-style emitter, strict quoting, fixed [a-z][a-z0-9_]* keys
  Audit/AuditRecord.php      Pure: fromSelf / fromLove → record (persons + response_yaml), FORMAT_VERSION (3), FORMAT_VERSION_HIDDEN (4: Love via a hidden link, YAML marker `loved_person_source: hidden_link`), MAX_YAML_BYTES (64 KiB, else a `truncated: true` document)
  Db/Config.php              I/O: Config::load($path): ?array — reads config.php, null if missing/invalid, silent
  Db/Connection.php          I/O: Connection::open($cfg): PDO — utf8mb4, exceptions, 2 s connect timeout, no emulated prepares
  Db/AuditLog.php            I/O: AuditLog::tryWrite(?$configPath, $record): bool — one transaction, prepared statements, never throws
  Time/Zone.php              Zone::toUnix(y,m,d,h,i,$tz), Zone::isValid($tz), dateAt($unix,$tz), offsetMinutes($unix,$tz)
  Geo/Geocoder.php           Open-Meteo search + disk cache + fallback; Geocoder::label(); prune() caps the cache (MAX_FILES 500, PRUNE_TO 400, once a day)
  Geo/FallbackCities.php     Bundled major cities, search()
  Content/Signs.php          Signs::TEXT (per sign) and Signs::ROLES (sun/asc/moon copy)
  Content/Bodies.php         Bodies::INFO — glyph, title, tagline, meaning per planet/node
  Content/Traits.php         Keywords per sign/element/modality, complements, levels, aspects, biorhythm VERDICTS, COMMON_VERDICTS, COMMON_MEANING, level pill labels
  Content/TarotDeck.php      TarotDeck::CARDS (22 Major Arcana), COUNT (78), card($n) → card shape for 0-77
  Content/TarotMinor.php     56 minor arcana: SUITS, RANKS, RANK_ESSENCE, RANK_TEXT, SUIT_TEXT, card(), text()
  Content/TarotSpread.php    ORDER, POSITIONS (title, question, intro), text($cardId, $position, $reversed) — throws on a missing key; minors delegate to TarotMinor
  Content/Daily.php          Moon phase names/symbols and the "Today's sky" sentences (MOOD, RELATION, INVITATION)
  Content/Houses.php         THEMES: title and one line for each of the 12 houses
  Content/Aspects.php        NAMES, MEANING, TONE, TONE_LABELS for synastry
  Content/Help.php           Help::TEXT (about 50 keys → title + text for the "?" popovers), DYNAMIC, keys(), get(), dynamicKeys()
  Content/TarotPast.php      TEXT: 22 cards x up/rev, Past position
  Content/TarotPresent.php   same, Present position
  Content/TarotFuture.php    same, Future position
templates/home.php           Page shell: hero, chooser (Self Discovery, Soul Affinity, Friends hidden codes; the last two locked by default), form for the mode, Friends panel, confirm dialog, includes the result template; escapes via e()
templates/self-result.php    Self result: big three, Midheaven, planets with houses, wheel, affinities, biorhythms, born under, Today's sky, distance, share, print button
templates/love-result.php    Love result: name %, compact synchrony, common values, synastry, Today's sky, distance, Past/Present/Future spread, share, print button
templates/partials/          help.php (help_button($key)), self-actions.php (Self buttons, status, hidden-code panel), person-carried.php (hidden `a_*` inputs of Soul Affinity), friends.php (Friends panel: search, bulk bar, row `<template>`, noscript note), confirm.php (the shared `<dialog>`), import.php ("Import from a user": nickname, scan, paste), hidden-person.php ("details loaded and hidden" block), person-fields.php (birth and optional current-city fields per prefix), icons.php (static SVG), bio.php (curve chart), terms.php (T&Cs text, used by the popup and the end-of-page section), share.php (Share section: buttons, QR, collapsed links), qr.php (qr_svg()), wheel.php (wheel_svg()), sky.php (Today's sky), synastry.php (aspect table), geo.php (distance card), memory.php (browser-memory bar and saved-people select)
docker-compose.yml           Local run: web (PHP + Apache, bind mount, 127.0.0.1:8081) and db (MySQL 8.4, volume dbdata, migrations/ as initdb, not published)
docker/Dockerfile            php:8.3-apache + pdo_mysql + rewrite/headers/expires; Apache config allowing the root .htaccess
docker/config.php            DB settings for the Docker setup, read from the container environment (MAGIC_DB_*)
migrations/001_create_magic_audit.sql   Idempotent schema for magic_audit and magic_audit_person (CREATE TABLE IF NOT EXISTS)
cache/                       Geocoding cache (writable, denied from web; not in web root)
tests/run.php                Dependency-free test runner (core checks), requires tests/cases/*.php
tests/cases/                 hidden.php (hidden code, import flow, hidden.php, consent, no-leak), flow.php (ADR 0007: chooser and locks, Self/Soul Affinity/Friends structure, pending friend, nicknames, Terms version, Friends hardening), ui.php (chooser, required marks, help coverage and wording, CSS regressions, script static checks), planets.php, bio.php, love.php, tarot.php, request.php, layering.php (ADR 0002 checks), audit.php (ADR 0003: Yaml, AuditRecord, DB failure isolation), share.php (ADR 0004: QR structure, share-link round trips, noaudit), position.php (ADR 0005: reading day, current position, distances), sky.php (houses, moon phase, aspects, synastry, wheel, daily copy), code.php (short codes, time-zone table, gate), memory.php (memory.js static checks, hooks in templates)
scripts/docker-db.sh         Docker DB helper: migrate | shell | query "SQL" | audit [N] | reset (password stays in the container)
scripts/deploy.sh            Tests, then uploads committed files changed since last deploy via the sftp-upload skill
scripts/make-config.sh       Writes config.php (mode 600) from the database section of ~/.password; prints only "config.php written"
scripts/db-migrate.sh        Applies migrations/*.sql in order with the mysql client (password via a temp option file, never on the command line)
scripts/db-purge.sh          Manual retention: `--days N` deletes audit rows older than N days (cascade removes persons)
scripts/lib/dbcred.sh        Sourced helper: load_db_credentials, write_mysql_defaults, run_mysql (stderr hidden, only `mysql error <code> (<SQLSTATE>)`)
.deploy.local.example        Template for the gitignored .deploy.local (host, remote dir, DB_PASSWORD_SECTION); credentials in ~/.password
.deploy-state                (gitignored) last deployed commit SHA
.deploy-config-hash          (gitignored) content hash of the last uploaded config.php
docs/                        architecture.md, code.md, features.md (visitor view), changelog.md, roadmap.md, decisions/ (ADRs)
.claude/agents/              architect, implementer, reviewer, documenter, deployer
.claude/commands/            new-feature.md (workflow entry point)
CLAUDE.md                    Rules for AI agents
```

## Data shapes

```php
// Chart::compute(int $year, int $month, int $day, int $hour, int $minute, float $lat, float $lon, string $timeZone)
['sun' => $pos, 'moon' => $pos, 'ascendant' => $pos, 'midheaven' => $pos, 'utc' => 'Y-m-d H:i', 'polar' => bool]
// Chart::full(...same args) = compute + :
['planetsSupported' => bool,                       // 1800..2100
 'planets' => ['mercury' => ['position' => $pos, 'retrograde' => bool], ... 'pluto' => ...],   // [] when unsupported
 'node' => $pos,                                  // mean North Node, any year
 'houses' => ['sun','moon','mercury'.. 'pluto','node' => int 1-12]]
// Chart::partial(y, m, d, ?h, ?mi, ?lat, ?lon, ?tz)
['sun' => ?$pos, 'moon' => ?$pos, 'ascendant' => ?$pos, 'midheaven' => ?$pos (with time and place), 'approx' => ['sun' => bool, 'moon' => bool]]
// Chart::longitudes(y,m,d,?h,?mi,?lat,?lon,?tz) → array<string,float> of the bodies that exist for the input (synastry)
// $pos (Zodiac::fromLongitude)
['id','name','symbol','element','modality','index','longitude','degree','minute']
// city (Geocoder / FallbackCities)
['name','admin','country','lat','lon','timeZone']

// Request::parse (Self) → ['input' => ['year','month','day','hour','minute','lat','lon','tz','city','now' => ?['lat','lon','tz','city']] | null, 'errors', 'notes']
// Request::parseLove → ['a' => $person|null, 'b' => $person|null, 'errors', 'notes']; errors prefixed "You: " / "Loved person: "
$person = ['name' => string, 'date' => ?['year','month','day'], 'time' => ?['hour','minute'], 'place' => ?['lat','lon','tz','city'], 'now' => ?['lat','lon','tz','city']]  // 'now' = optional current position (query keys pos_city, pos_lat, pos_lon, pos_tz with the person prefix)

// SelfReading::build → ['chart' => Chart::full, 'refs' => ['sun','moon','ascendant'(,'venus','mars') => sign index],
//   'affinity' => SignAffinity::rank, 'bio' => Biorhythm::forPerson, 'today', 'dayBasis' (utc|current_position|on), 'dayZone',
//   'wheel' => ChartWheel::layout, 'sky' => Today::for, 'moonAtBirth' => Today::phaseAt, 'geo' => Geography::forSelf|null, 'notes']
// LoveReading::build → ['names','affinity' => NameAffinity::compute, 'charts' => ['a','b' => partial|null],
//   'common' => Common::between|null, 'bio' => Synchrony::pair|null, 'tarot' => Reading::spread, 'tarotShared' => bool, 'today', 'dayBasis', 'dayZone',
//   'sky' => Today::for, 'synastry' => ['rows','total','counts','approx','available'], 'geo' => Geography::forLove|null, 'notes']
// NameAffinity::compute → ['percent', 'noLetters', plus supporting values shown by the template]
// Synchrony::pair → ['cycles' => [physical|emotional|intellectual => delta, sync, amplitude, flat, peaksTogether,
//   nextCombinedPeak/Trough, nextBothHigh/Low, curveA/B], 'overall' => float, 'band' => tune|complementary|apart]
// Reading::spread → list of ['position' => past|present|future,'card','reversed','text']
// Share: ShareLink::love(...) → 'mode=love&a_name=…&on=YYYY-MM-DD&t=16u,5r,9u&noaudit'; t = three slots <0-21><u|r>, distinct numbers;
//   self → 'mode=self&date&time&city&lat&lon&tz&on&noaudit'; live links omit on and t. Invalid t: ignored, note shown.
//   Primary link: ShareCode::encodeSelf|encodeLove(...) → '?c=<code>' (decode → the same long-query array; a `c=` merged with other keys, the code wins; bad code: ignored with a note).

// AuditRecord::fromSelf($input, $selfView, $today) / fromLove($a, $b, $loveView, $today, ?$self = null) → record
['functionality' => 'self'|'love', 'on_date' => 'YYYY-MM-DD' (UTC reading date or on=), 'format_version' => 3,
 'persons' => list of ['role' => 'self'|'user'|'loved', 'name', 'birth_date' => ?'Y-m-d', 'birth_time' => ?'HH:MM:SS',
                       'place_label' => ?string(<=255), 'lat' => ?float(5 dp), 'lon' => ?float, 'tz' => ?string],
 'response_yaml' => string]
// Self: one person, role self (name = the optional Self name, '' when empty; time defaults to 12:00 when not given).
// Love via a hidden link: both people stored in full, YAML starts with `loved_person_source: hidden_link`, format_version 4.
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

YAML summary (`format_version` 3; versions 1 and 2 stay readable; keys are code constants). Version 3 adds, for Self: `chart.midheaven`, `sky` (`moon_phase`, `moon_illumination`, `moon_sign`, `born_moon_phase`), `current_position` (`label`, `lat`, `lon`, `tz`, or null), `reading_day_basis`, `distance.birth_to_now_km`; for Love: `sky`, `synastry` (`available`, `approx`, `total`, `counts`, `tightest`), `positions.a|b`, `reading_day_basis`, `distance` (`between_km`, `a_birth_to_now_km`, `b_birth_to_now_km`). Self: `functionality`, `on_date`, `chart` (`utc`, `polar`, `sun`/`ascendant`/`moon` as `{sign, degree, minute}`, `planets_supported`, `planets.<body>` with `retrograde`, `north_node`), `affinity` (`most_affine`, `soulmate`), `biorhythm` (`physical`, `emotional`, `intellectual`), `notes`. Love: `functionality`, `on_date`, `name_affinity` (`percent` and supporting values), `charts.a|b` (`sun`, `moon`, `ascendant`, `sun_approx`, `moon_approx`; null when no chart), `common.score`, `synchrony` (`overall`, `band`), `tarot` (list of `position`, `card`, `reversed`; version 1 rows had `date` instead of `position`), `notes`.

## View keys and DOM hooks

- Share model built in `public/index.php`: `frozen`, `live`, `qr` (modules or null), `tooLong` for `templates/partials/share.php`.
- `memory.js` reads these `data-` attributes (rendered by `templates/home.php`, `partials/person-fields.php`, `partials/memory.php`, `self-actions.php`, `friends.php`, `confirm.php`): `data-memory="self|love"` on the form; `data-person="me|loved"` on a person fieldset; `data-carried` (hidden `a_*` fieldset); `data-memory-bar`, `data-memory-save`, `data-memory-status`, `data-saved`, `data-saved-select`, `data-saved-remove`; `data-memory-forget-on-submit` on the withdraw form; `data-forget-memory` on `#page` after a withdrawal or a clean. Locks: `data-needs-self`, `data-lock-text`, `data-unlock-text`, `data-love-locked`, `data-love-body`. Self actions: `data-self-actions`, `data-self-save`, `data-self-clear`, `data-self-hidden-code` (class `is-empty` + `aria-disabled` when nothing/incomplete is stored), `data-pending-friend`, `data-hidden-panel`, `data-hidden-qr`, `data-hidden-status`. Friends: `data-friends`, `data-friends-search`, `data-friends-count`, `data-friends-list`, `data-friends-row` (on the `<template>`), `data-friends-selectall`, `data-friends-remove-selected`. Dialog: `data-confirm`. Share: `data-qr-copy` (id of the link input), `data-copy-status`. Import: `data-import-scan`, `data-import-video`, `data-import-status`. Help: `.help__btn` (with `aria-controls`) and `data-help` on the popover. `memory.js` dispatches `magic:share-hidden` on `document` with the stored entry as `detail`. Storage: `localStorage` `magic.me.v1`, `magic.loved.v1`, `magic.friends.v1` (`{v:1, list:[{code, nick, t}]}`); `sessionStorage` `magic.justSaved`, `magic.friendHandled`. Place blocks carry `data-place` with `data-kind="birth|pos"` (`autocomplete.js` works per `[data-place]`).
- `share.js` hooks: `[data-copy]`, the link input `#share-link` inside `details.share__more`, QR blocks `details.share__qrbox` with `[data-qr-copy]`.
- Help keys: `field.*`, `share.*`, `self.*` (incl. `self.hidden_code`, `self.clear`, `self.save`, `self.reveal`), `friends.*`, `bio.*`, `love.*` (incl. `love.import`) in `Content\Help::TEXT`.
- Parameters: `h=<code>` (hidden person, version 3; implies Love), `import=<pasted link or code>` (converted to `h`; works without JavaScript), `nick=` (receiver's local nickname for a hidden person; never in codes, audit or share links), `name=` in Self, `mode=friends`, `cleaned=1` (kept, no UI reaches it).
- Love hidden result: view key `hidden` (true) and person B label "Your match"; templates never receive the real details.

## Request flow

Self: `GET /?mode=self&date=1990-07-15&time=08:30&city=Rome&lat=41.9&lon=12.5&tz=Europe/Rome` (a missing `mode` with `date`/`city` is Self, so old links work)
→ `public/index.php` → `Request::mode`, `parseToday` → `Request::parse` (if `lat/lon/tz` missing, `Geocoder::search(city)[0]`) → `SelfReading::build` → `Chart::full` (`Zone::toUnix` → `Angles::julianDay` → `Sun/Moon/Ascendant/Planets/MeanNode`, `Zodiac::fromLongitude`) + `SignAffinity::rank` + `Biorhythm::forPerson` → `templates/home.php` → `self-result.php`.

Love: `GET /?mode=love&a_name=…&a_date=…&a_time=…&a_city=…&b_name=…[&b_date&b_time&b_city]` (prefixes `a_` = you, `b_` = loved person; each also accepts `_lat/_lon/_tz`)
→ `Request::parseLove` (+ `Request::parseTarot` for `t`) → `LoveReading::build` → `Chart::partial` ×2, `NameAffinity`, `Synchrony`, `Common`, `Tarot\Reading::spread` → `love-result.php`. Both result pages then include `partials/share.php`.

Self with a pending friend: `GET /?mode=self&h=<code>[&nick=…]` shows a banner; Reveal/Save then adds the code to Friends once (only if not already stored). Friends: `GET /?mode=friends` renders `partials/friends.php`, built by `memory.js`.

Hidden: `GET /?h=<code>` (or `?import=<pasted link>`) → consent check → `Request::hiddenCode` → person merged into the query for parsing, `$hidden = true`, form shows `partials/hidden-person.php` and a hidden input `h` → `LoveReading::build` with B labelled → result without share section → audit with the hidden marker (unless `noaudit`). Creating the link: browser (`share.js`) → `POST hidden.php` → JSON.

Consent: `public/index.php` reads `Consent::given($_COOKIE)`. Without the cookie `magic_terms` the query is ignored, no result is built and the terms popup is shown (the sanitised original query travels in a hidden field). The popup/section form posts to `public/consent.php`, which sets (accept) or clears (withdraw, clean) the cookie and redirects with 303 to `./` (`./?withdrawn=1`, `./?cleaned=1`). No consent means no result and no audit record. Every response carries `Cache-Control: private, no-store` and `Vary: Cookie`.

Audit: after `templates/home.php` is rendered, and only when `$view !== null` (a result) and the request has no `noaudit` parameter (hidden-link requests are audited, with format 4), `public/index.php` calls `ignore_user_abort(true)`, `set_time_limit(10)`, `fastcgi_finish_request()` (else `flush()`), builds the record (`AuditRecord::fromSelf|fromLove`) and calls `AuditLog::tryWrite(getenv('MAGIC_CONFIG') ?: MAGIC_ROOT.'/config.php', $record)`. Missing config, missing PDO, connection or SQL errors return false; only `audit: write failed <Class> <code>` (or `audit: build failed <Class>`) is logged. `MAGIC_CONFIG` exists so tests can point to another file.

`on=YYYY-MM-DD` overrides "today" (default: the day at the user's current position, else the server UTC date). `?c=<code>` is decoded first and merged over the query. Gate and `noaudit` rules are unchanged. No `mode` and no input shows the mode chooser.

Autocomplete: `autocomplete.js` → `GET api/cities.php?q=par` → `Geocoder::search` → JSON.

## Recipes

- **Run locally:** `docker compose up --build` (page at http://localhost:8081, accept the T&Cs in the popup) or `php -S localhost:8081 -t public` · **Test:** `php tests/run.php` (also loads `tests/cases/*.php`; no per-file runner, all checks run every time)
- **Reproducible readings:** add `&on=2026-10-09` to a URL to fix "today" (biorhythms, tarot); add `&noaudit` to skip the audit write. Shared links carry both (plus `t=` in Love).
- **Add a planet-like body:** compute it in `Chart::full` → copy in `Content\Bodies::INFO` → show in `templates/self-result.php` → reference-value test in `tests/cases/planets.php`.
- **Add a tarot card or change its text:** `Content\TarotDeck::CARDS` for the card and its essence line, and its up/rev entries in `Content\TarotPast`, `TarotPresent`, `TarotFuture` (a missing key throws; update the goldens in `tests/cases/tarot.php`). Minor arcana (22-77) live in `Content\TarotMinor`; `t=` accepts card numbers 0-77.
- **Make a new output shareable:** classify it as derived or encoded (CLAUDE.md rule); if encoded, add it to `Share\ShareLink` and `Request`, and a round-trip test in `tests/cases/share.php`.
- **Tune affinity scoring:** constants in `Love\SignAffinity`; expectations in `tests/cases/love.php`.
- **Add a field to the audit:** put it in `AuditRecord` (a YAML key in the `$doc` of `fromSelf`/`fromLove`, or a person field), bump `FORMAT_VERSION` if the YAML layout changes; for a new column add an idempotent `migrations/NNN_*.sql` (guarded `ALTER`) and extend the INSERT in `Db\AuditLog`; update `tests/cases/audit.php`.
- **Set up / migrate the database:** `scripts/make-config.sh` (or copy `config.php.example` to `config.php` and edit), then `scripts/db-migrate.sh`. If the database server refuses your machine, paste `migrations/001_create_magic_audit.sql` into the hosting panel's SQL tool.
- **Docker database:** `scripts/docker-db.sh migrate` (re-apply migrations), `audit [N]` (latest results), `query "SELECT ..."`, `shell`, `reset` (wipes the volume and starts again). The schema is applied automatically on the first start.
- **Add or change a help text:** add the key to `Content\Help::TEXT` (20-240 characters, plain advice, no method talk), call `help_button('key')` where it belongs; `tests/cases/ui.php` fails if a key is unused, missing or too long.
- **Manual browser checks for ADR 0007:** (1) fresh browser: Soul Affinity and Friends greyed with the lock text, Generate and Clear grey; Save with an incomplete form says what is missing; after Save and reload the buttons and cards are active. (2) Reveal, edit the date, Reveal again: the dialog appears; Cancel keeps everything, Continue wipes friends and saved people. (3) Newcomer in a private window: open a hidden link, accept the Terms, go to Self Discovery from the locked notice, Reveal, find the friend in Friends with the typed nickname. (4) Friends: add three, search, rename, select all shown, bulk remove, single remove, Compare opens a result labelled with the nickname and no friend detail in the page source. (5) Keyboard-only walk of the dialog, list and checkboxes; layout at 360 px and 1280 px. (6) Storage blocked: Self works, Save/Clear/Friends explain it.
- **Check the Terms section is clickable (manual):** in the browser console run `document.elementFromPoint(x, y)` at the centre of `#terms summary`; it must return `SUMMARY`, not `FOOTER`. Repeat at 360 px and 1280 px.
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

## Where do I...

| Task | Go to |
|---|---|
| Change what counts as "today" | `Request::resolveToday`, `Zone::dateAt`; clock read in `public/index.php` |
| Add or change a current-position field | `Request::parsePerson`, `templates/partials/person-fields.php`, `tests/cases/position.php` |
| Change the distance card | `Earth\Geography`, `templates/partials/geo.php` |
| Change houses / Midheaven | `Astro\Houses`, `Chart::compute`/`full`, `Content\Houses` |
| Change the wheel drawing | `ChartWheel`, `templates/partials/wheel.php`, wheel classes in `styles.css` |
| Change moon phase or Today's sky text | `Astro\MoonPhase`, `Sky\Today`, `Content\Daily`, `templates/partials/sky.php` |
| Change synastry | `Astro\Aspects`, `Love\Synastry`, `Content\Aspects`, `templates/partials/synastry.php` |
| Change minor arcana text | `Content\TarotMinor`, goldens in `tests/cases/tarot.php` |
| Change the short link format | `Share\ShareCode` (new format = new `VERSION`, keep old codes decodable), `tests/cases/code.php` |
| Add a time zone to the code table | append to `Share\TimeZoneTable::ZONES` (never reorder), update the pinned checkpoint in `tests/cases/code.php` |
| Change what the browser remembers | `public/assets/memory.js`, `templates/partials/memory.php`, the Terms (`partials/terms.php`), `tests/cases/memory.php` |
| Add or change a help text | `Content\Help::TEXT`, `help_button()` in the template (`templates/partials/help.php`), `tests/cases/ui.php` |
| Change the Self buttons or confirm dialog | `templates/partials/self-actions.php`, `partials/confirm.php`, handlers in `public/assets/memory.js`, `tests/cases/flow.php`, `ui.php` |
| Change the Friends list or locks | `templates/partials/friends.php`, `person-carried.php`, `memory.js`, `Request::nickname`, `tests/cases/flow.php` |
| Change hidden sharing or import | `Share\ShareCode` (hidden code), `Request::hiddenCode`, `public/hidden.php`, `partials/import.php`, `partials/hidden-person.php`, `share.js`, `import.js`, `tests/cases/hidden.php` |
| Force everyone to re-accept the Terms | bump `Consent::VALUE` |
| Add an audit key | `Audit\AuditRecord`, bump `FORMAT_VERSION` if the layout changes, `tests/cases/audit.php` |
| Document a release | `docs/changelog.md` (Unreleased) |

## Conventions

PHP 8.1+, `declare(strict_types=1)`, namespace `Magic\`, one class per file named after the file. Degrees at API boundaries, longitudes east-positive. No Composer/dependencies. No inline `<script>`/`style` (CSP). Escape every template output with `e()`.

# Code navigation guide

Where things are. For the *why*, read [architecture.md](architecture.md).

## Repository map

```
/.htaccess                   Only used when the web dir is the project root: 301 /public/… → /…, rewrites all else into public/
public/                      ← web root (the server's "web directory")
  index.php                  Front controller: mode → Request::parse|parseLove → SelfReading|LoveReading::build → templates/home.php; sets noindex + no-store on results
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
cache/                       Geocoding cache (writable, denied from web; not in web root)
tests/run.php                Dependency-free test runner (core checks), requires tests/cases/*.php
tests/cases/                 planets.php, bio.php, love.php, tarot.php, request.php, layering.php (ADR 0002 checks)
scripts/deploy.sh            Tests, then uploads committed files changed since last deploy via the sftp-upload skill
.deploy.local.example        Template for the gitignored .deploy.local (host, remote dir); credentials in ~/.password
.deploy-state                (gitignored) last deployed commit SHA
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
```

## Request flow

Self: `GET /?mode=self&date=1990-07-15&time=08:30&city=Rome&lat=41.9&lon=12.5&tz=Europe/Rome` (a missing `mode` with `date`/`city` is Self, so old links work)
→ `public/index.php` → `Request::mode`, `parseToday` → `Request::parse` (if `lat/lon/tz` missing, `Geocoder::search(city)[0]`) → `SelfReading::build` → `Chart::full` (`Zone::toUnix` → `Angles::julianDay` → `Sun/Moon/Ascendant/Planets/MeanNode`, `Zodiac::fromLongitude`) + `SignAffinity::rank` + `Biorhythm::forPerson` → `templates/home.php` → `self-result.php`.

Love: `GET /?mode=love&a_name=…&a_date=…&a_time=…&a_city=…&b_name=…[&b_date&b_time&b_city]` (prefixes `a_` = you, `b_` = loved person; each also accepts `_lat/_lon/_tz`)
→ `Request::parseLove` → `LoveReading::build` → `Chart::partial` ×2, `NameAffinity`, `Synchrony`, `Common`, `Tarot\Reading` → `love-result.php`.

`on=YYYY-MM-DD` overrides "today" (default: server UTC date). No `mode` and no input shows the mode chooser.

Autocomplete: `autocomplete.js` → `GET api/cities.php?q=par` → `Geocoder::search` → JSON.

## Recipes

- **Run locally:** `php -S localhost:8081 -t public` · **Test:** `php tests/run.php` (also loads `tests/cases/*.php`; no per-file runner, all checks run every time)
- **Reproducible readings:** add `&on=2026-10-09` to a URL to fix "today" (biorhythms, tarot).
- **Add a planet-like body:** compute it in `Chart::full` → copy in `Content\Bodies::INFO` → show in `templates/self-result.php` → reference-value test in `tests/cases/planets.php`.
- **Add a tarot card or change its text:** `Content\TarotDeck::CARDS` (the draw uses `count()`; update the golden values in `tests/cases/tarot.php`).
- **Tune affinity scoring:** constants in `Love\SignAffinity`; hand-computed expectations in `tests/cases/love.php`.
- **Add a mode:** see architecture.md §7.
- **Change copy:** `src/Content/Signs.php`. **Change look:** `public/assets/styles.css` (tokens in `:root`).
- **Wrong sign?** Call `Chart::compute` from CLI with the same input; check `utc` first (time-zone issues are the usual cause), then compare longitudes with an ephemeris.
- **Geocoding stale/broken:** delete `cache/geo-*.json`.

## Conventions

PHP 8.1+, `declare(strict_types=1)`, namespace `Arcana\`, one class per file named after the file. Degrees at API boundaries, longitudes east-positive. No Composer/dependencies. No inline `<script>`/`style` (CSP). Escape every template output with `e()`.

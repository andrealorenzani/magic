# Code navigation guide

Where things are. For the *why*, read [architecture.md](architecture.md).

## Repository map

```
public/                      ← web root (DreamHost "web directory")
  index.php                  Main page controller: read $_GET → Request::parse → Chart::compute → templates/home.php
  api/cities.php             JSON city autocomplete endpoint (?q=…) backed by Geocoder
  assets/styles.css          All styling (dark/starry theme, element colours)
  assets/autocomplete.js     Progressive enhancement: suggestions + fills hidden lat/lon/tz
  .htaccess                  Apache hardening + CSP + caching
src/
  autoload.php               PSR-4 style autoloader for namespace Arcana\ (no Composer)
  bootstrap.php              Loads autoloader, defines ARCANA_ROOT and the e() escape helper
  Chart.php                  ★ Chart::compute(...) — single public API of the core
  Request.php                Request::parse($_GET, $geocoder) → validated input / errors / notes
  Astro/Angles.php           rad, norm360, julianDay, centuries, nutationLongitude, obliquity
  Astro/Sun.php              Sun::longitude($jd)
  Astro/Moon.php             Moon::longitude($jd) — periodic-term table
  Astro/Ascendant.php        Ascendant::gmst($jd), Ascendant::longitude($jd, $lat, $lon)
  Astro/Zodiac.php           Zodiac::SIGNS, Zodiac::fromLongitude($lon)
  Time/Zone.php              Zone::toUnix(y,m,d,h,i,$tz), Zone::isValid($tz)
  Geo/Geocoder.php           Open-Meteo search + disk cache + fallback; Geocoder::label()
  Geo/FallbackCities.php     Bundled major cities, search()
  Content/Signs.php          Signs::TEXT (per sign) and Signs::ROLES (sun/asc/moon copy)
templates/home.php           The HTML page (form + result cards); escapes via e()
cache/                       Geocoding cache (writable, denied from web; not in web root)
tests/run.php                Dependency-free test runner
docs/                        architecture.md, code.md, roadmap.md, decisions/ (ADRs)
.claude/agents/              architect, implementer, reviewer, documenter
.claude/commands/            new-feature.md (workflow entry point)
CLAUDE.md                    Rules for AI agents
```

## Data shapes

```php
// Chart::compute(int $year, int $month, int $day, int $hour, int $minute, float $lat, float $lon, string $timeZone)
// returns
['sun' => $pos, 'moon' => $pos, 'ascendant' => $pos, 'utc' => 'Y-m-d H:i', 'polar' => bool]
// $pos (Zodiac::fromLongitude)
['id','name','symbol','element','modality','index','longitude','degree','minute']
// city (Geocoder / FallbackCities)
['name','admin','country','lat','lon','timeZone']
```

## Request flow

`GET /?date=1990-07-15&time=08:30&city=Rome&lat=41.9&lon=12.5&tz=Europe/Rome`
→ `public/index.php` → `Request::parse` (validates; if `lat/lon/tz` missing, `Geocoder::search(city)[0]`) → `Chart::compute` → `Zone::toUnix` → `Angles::julianDay` → `Sun/Moon/Ascendant::longitude` → `Zodiac::fromLongitude` → `templates/home.php` (copy from `Content\Signs`).

Autocomplete: `autocomplete.js` → `GET api/cities.php?q=par` → `Geocoder::search` → JSON.

## Recipes

- **Run locally:** `php -S localhost:8081 -t public` · **Test:** `php tests/run.php`
- **Add a card (e.g. Mercury):** `src/Astro/Mercury.php` → call in `Chart::compute` → `Signs::ROLES['mercury']` → add to the role list in `templates/home.php` → reference-value test in `tests/run.php`.
- **Change copy:** `src/Content/Signs.php`. **Change look:** `public/assets/styles.css` (tokens in `:root`).
- **Wrong sign?** Call `Chart::compute` from CLI with the same input; check `utc` first (time-zone issues are the usual cause), then compare longitudes with an ephemeris.
- **Geocoding stale/broken:** delete `cache/geo-*.json`.

## Conventions

PHP 8.1+, `declare(strict_types=1)`, namespace `Arcana\`, one class per file named after the file. Degrees at API boundaries, longitudes east-positive. No Composer/dependencies. No inline `<script>`/`style` (CSP). Escape every template output with `e()`.

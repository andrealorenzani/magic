# 0002 — Two modes: Self discovery and Love

- Status: accepted (implemented 2026-10-09; see "Implementation notes")
- Date: 2026-10-09

## Context

The page took birth date, time and city and showed Sun, Ascendant and Moon (ADR 0001). The owner wants the home page to offer two modes:

- **A. Self discovery**: the same inputs, plus the other planetary signs, the signs with most affinity to the user and a "love of your life" sign, the three biorhythms, icons and a printable version.
- **B. Love**: name, birth date, place and time for the user and for a loved person (everything optional for the loved person except the name). It shows a name-based affinity %, a biorhythm synchrony, common values from Sun/Ascendant/Moon, a 3-day tarot reading and a printable version.

Constraints that stay: PHP 8.1+, no Composer, no build, pure core, CSP without inline script/style, escape with `e()`, GET-based shareable URLs, server-side geocoding with cache.

## Decision

1. No existing decision is broken. The astronomy stays in-house (planets are approximate: enough for the sign, not for fine positions), the tropical zodiac is used for everything, geocoding is called up to twice per request (disk cache), the page works without JS.
2. New **pure** namespaces `Magic\Love`, `Magic\Bio`, `Magic\Tarot`, plus two pure orchestrators `Magic\SelfReading` and `Magic\LoveReading` in `src/`. New pure astronomy: `Astro\Planets`, `Astro\MeanNode`. New copy in `src/Content/`.
3. The clock is I/O: `public/index.php` passes `today` (`Y-m-d`, UTC, optionally overridden by a validated `on=YYYY-MM-DD` parameter) into the pure builders. No pure class reads the clock.
4. Printing = `@media print` CSS plus a print button wired by `public/assets/print.js`; the button is hidden in markup and shown by the script, so without JS the browser's own Print still works.
5. Planets (Mercury to Pluto) are supported for the years **1800-2100**; outside that range the page shows only the big three. The mean North Node works for any year.

## Design

### Files added

```
src/Astro/Planets.php        Mercury..Pluto signs
src/Astro/MeanNode.php       mean North Node
src/Bio/Biorhythm.php        the three biorhythms of one person
src/Bio/Synchrony.php        biorhythm synchrony of two people
src/Love/NameAffinity.php    name affinity percentage
src/Love/SignAffinity.php    sign-vs-sign ranking, "love of your life" sign
src/Love/Common.php          shared traits of two people's Sun/Moon/Ascendant
src/Tarot/Reading.php        deterministic 3-day tarot draw
src/Content/Bodies.php       planet glyphs, titles, taglines, short meanings
src/Content/Traits.php       keywords per sign / element / modality
src/Content/TarotDeck.php    22 Major Arcana with love-oriented texts
src/SelfReading.php          builds the Self view-model
src/LoveReading.php          builds the Love view-model
templates/partials/{person-fields,icons,bio}.php
templates/{self-result,love-result}.php
public/assets/print.js
```

### Files changed

- `src/Chart.php`: `compute()` untouched; `Chart::full(...)` adds planets and node; `Chart::partial(...)` accepts optional time/place.
- `src/Request.php`: per-person validation factored into `parsePerson()`; `parseLove()` and `parseToday()` added; `parse()` keeps its behaviour.
- `public/index.php`: dispatch on `mode` (`self` | `love` | absent). Absent mode with `date`/`city` is Self (old URLs keep working); absent mode and no input shows the chooser only. Result pages send `X-Robots-Tag: noindex` and `Cache-Control: private, no-store`.
- `templates/home.php`: hero, mode chooser (plain links), the form for the selected mode, the result template.
- `public/assets/autocomplete.js`: binds every `[data-place]` container (one per person).
- `public/assets/styles.css`: icons, two-column person form, planet grid, bio chart classes, tarot card, print block.

### Request parameters

Self: `mode=self&date&time&city[&lat&lon&tz]`. Love: `mode=love` and for each person prefix `a_` (user) / `b_` (loved): `{p}name, {p}date, {p}time, {p}city, {p}lat, {p}lon, {p}tz`. Optional `on=YYYY-MM-DD`.

Validation lives in `Request`: valid date (1000-2100), `hh:mm` time, coordinate ranges, `tz` from the PHP identifier list (else the city text is geocoded), `mode` whitelisted, `on` valid (1900-2100) else ignored. Names: trimmed, 1-40 characters, no control characters, at least one letter.

Person A (the user): name, date, time and city required. Person B: name required; date, time, city optional but validated when non-empty. A time is used only when date and city are present; a city is geocoded only when date and time are present. Anything ignored produces a note.

Person shape:

```php
['name' => string,
 'date'  => ?array{year:int, month:int, day:int},
 'time'  => ?array{hour:int, minute:int},
 'place' => ?array{lat:float, lon:float, tz:string, city:string}]
```

### Chart additions (pure)

`Chart::full` returns `Chart::compute` plus `planetsSupported`, `planets` (each with `position` and `retrograde`; empty when unsupported) and `node`. `Chart::partial` returns `sun`, `moon`, `ascendant` (any may be null) and `approx` flags: with date, time and place it equals `compute`; otherwise there is no Ascendant, and Sun and Moon are approximate, flagged `approx` when the sign could differ depending on the birth time ("may be the adjacent sign depending on birth time"). A time without a place is ignored.

### Features

- **Planets**: sign, degree and a retrograde mark for Mercury to Pluto; approximate, which the UI says.
- **Sign affinity**: ranks the 11 other signs against the user's chart and returns the 3 signs with most affinity plus one "love of your life" sign, each with short reasons. Deterministic; presented as tradition, for wonder.
- **Biorhythms**: physical, emotional and intellectual values for today from the birth date, next peaks and troughs, a 30-day curve. For two people: a synchrony per cycle, the next combined peak and trough, the next day both are high or low, an overall score and a verdict band.
- **Name affinity**: a percentage from the two names, case and accent insensitive, same result in either order.
- **Common values**: what the two Sun/Moon/Ascendant signs share (sign, element, complementary elements, modality), with a score; only bodies both people have are compared.
- **Tarot**: three different Major Arcana for today, tomorrow and the day after, upright or reversed. Stateless: same input and day give the same cards.

### View-models

```php
SelfReading::build(array $input, string $today): array
// ['chart', 'refs', 'affinity', 'bio', 'today', 'notes']
LoveReading::build(array $a, array $b, string $today): array
// ['names', 'affinity', 'charts' => ['a','b'], 'common', 'bio' (null when B has no date), 'tarot', 'today', 'notes']
```

Both are pure; `public/index.php` only parses, calls a builder and includes the templates.

### UX

- Home: hero, two cards "Self discovery" and "Love", then the selected form. Mobile first; person columns side by side from 640px.
- Self result: big three, planets grid with retrograde badge, affinities and the "love of your life" card, biorhythm bars and 30-day chart with next peak/trough dates, accuracy note, Print button.
- Love result: name affinity card, biorhythm synchrony per cycle, common values, 3 tarot cards, Print button.
- Print: both results print on one or two A4 pages from the same URL; print-only header with names and date.
- Accessibility: charts are SVG with `role="img"` and an `aria-label`; the same numbers are present as text; colour is never the only carrier.
- The UI shows no explanation of how values are computed (project rule, see CLAUDE.md).

### Privacy

Names (and the loved person's birth data) are in the GET URL, so they can appear in access logs, browser history and shared links. Mitigations: `Referrer-Policy: same-origin`, `X-Robots-Tag: noindex`, `<meta name="robots" content="noindex">`, `Cache-Control: private, no-store`, a warning on the Love form. Only the city text goes to a third party. POST was rejected because it breaks shareable URLs; a "private mode" would need its own ADR. (Storage came later: ADR 0003.)

## Alternatives considered

- Full ephemeris libraries or binaries: more accurate but against the small, in-house, shared-hosting rule.
- Wider planet range: deferred; years before 1800 show the big three only.
- Literal simultaneous biorhythm peaks: misleading; replaced by a combined-curve view.
- Element-only sign scoring: too coarse; a richer, explainable score gives 12 distinct values.
- Random tarot with session storage: needs state and breaks shared URLs.
- PDF generation for print: needs a library; `@media print` is enough.
- Separate pages per mode: duplicates the shell; one front controller keeps old URLs valid.

## Risks

- Planet signs close to a sign boundary may be off; the UI says planets are approximate.
- Date-only Sun/Moon for the loved person can be the adjacent sign; flagged with `approx`.
- Names in URLs; name affinity, biorhythms, scores and tarot are entertainment (footer disclaimer).
- A lot of hand-written copy: the reviewer checks tone and length, no fake medical or fate claims.
- Print layout varies by browser; verify manually in Chrome and Firefox "Print to PDF".
- Invalid UTF-8 in names must be rejected by `Request`.

## Test plan

Dependency-free, `php tests/run.php`; checks live in `tests/cases/*.php`.

- **Planets**: reference-value checks at a fixed epoch and at known sign ingresses, retrograde flags on known dates, range gate (`supports`), `Chart::full` outside the range keeps the big three.
- **Mean node**: reference values.
- **Chart::partial**: date-only cusp day flagged `approx`, no Ascendant for date-only, equal to `compute` with full data.
- **Name affinity**: the documented example name pair, case/accent/order insensitivity, degenerate inputs (no letters), result always within 0-100.
- **Biorhythms**: day numbering and leap years, known values, peaks/troughs, curve length, identical and offset birth dates for synchrony.
- **Sign affinity**: hand-computed scores for a fixed chart, deterministic ranking, three most affine signs.
- **Common values**: one case per level, date-only partner, nothing comparable.
- **Tarot**: deterministic, three distinct cards, all cards have texts, golden values for a fixed input.
- **Request**: old queries without `mode` still parse; Love validation (missing name, loved person with only a name, ignored time note, 41-character name, name without letters, invalid mode, tampered `tz`, invalid `on`).
- **Layering**: the pure directories contain no I/O or clock reads; templates have no unescaped output or inline script/style.
- **Manual**: Self and Love flows, mobile viewport, print preview of both results, no CSP violations.

## Implementation notes

- Planets are supported for the whole year 2100 (the range ends at the start of 2101); the start is 1800-01-01.
- The verdict band is returned as `band` (`tune` | `complementary` | `apart`) by `Synchrony::pair`; its copy is `Content\Traits::VERDICTS`.
- "Biorhythms together" is shown per cycle plus the next day both curves are high or low; literal simultaneous peaks are reported only through `peaksTogether`.
- Request: person validation is `Request::parsePerson()` with an error prefix: errors read "You: ..." and "Loved person: ...".
- Bars are `<meter>` elements; chart legends use `.swatch` elements that print as solid/dotted/dashed black lines.
- `public/index.php` reads the clock (`gmdate`) and applies `on=`; all builders stay pure.
- The project was later renamed from Arcana to Magic (namespace `Magic\`); the "How this was computed" explanations were removed from the UI.

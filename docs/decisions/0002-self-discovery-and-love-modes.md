# 0002 — Two modes: Self discovery (all planets, affinities, biorhythms) and Love (name affinity, biorhythm synchrony, common signs, tarot)

- Status: accepted (implemented 2026-10-09; see "Implementation notes")
- Date: 2026-10-09

## Context

Today the page takes birth date, time and city and shows Sun, Ascendant and Moon (ADR 0001, D1-D9 in `docs/architecture.md`). The owner wants the home page to offer two modes:

- **A. Self discovery**: the same inputs, plus the other planetary signs, the signs with most affinity to the user and a "love of your life" sign, the three biorhythms, nice icons and a printable version.
- **B. Love**: name, birth date, place and time for the user and for a loved person (everything optional for the loved person except the name). It computes a name-based affinity %, a biorhythm synchrony, common values from Sun/Ascendant/Moon, a 3-day tarot reading and a printable version.

Constraints that stay: PHP 8.1+, no Composer, no build, no database (D2), pure core (D7), CSP without inline script/style, escape with `e()`, GET-based shareable URLs (D8), server-side geocoding with cache (D5).

## Decision

1. **No existing decision is broken.** D2 stays (nothing is stored), D3 is extended (more in-house astronomy, with a documented lower accuracy for planets), D4 tropical zodiac is used for everything, D5 is simply called up to twice per request (disk cache), D8 holds (everything works without JS; JS only adds autocomplete and the print button). Clarifications to write into `architecture.md` (documenter): names now travel in the GET URL (see Privacy), and planets are accurate to arcminutes, not 0.01 degrees.
2. New **pure** namespaces `Arcana\Love`, `Arcana\Bio`, `Arcana\Tarot`, plus two pure orchestrators `Arcana\SelfReading` and `Arcana\LoveReading` in `src/`. New pure astronomy: `Astro\Planets`, `Astro\MeanNode`. New copy in `src/Content/`.
3. The clock is I/O: `public/index.php` passes `today` (`Y-m-d`, UTC, optionally overridden by a validated `on=YYYY-MM-DD` query parameter, handy for tests and reproducible shared links) into the pure builders. No pure class calls `time()`/`date()`.
4. Printing = `@media print` CSS in `public/assets/styles.css` plus a print button (inline SVG printer icon) wired by `public/assets/print.js` (`window.print()`); the button is `hidden` in markup and un-hidden by the script, so without JS the browser's own Print still works.
5. Planet positions use the **JPL "approximate positions of the major planets" Keplerian elements (E. M. Standish, Table 1, valid 1800-2050)** with a Kepler solver. Supported years for planets: **1800-2100** (2050-2100 is extrapolation with slowly growing error). Outside that, planets are reported unavailable and the page shows only the big three (the Request limit of 1000-2100 is unchanged).

## Design

### Files to add

```
src/Astro/Planets.php        Keplerian ephemeris Mercury..Pluto (geocentric ecliptic longitude of date)
src/Astro/MeanNode.php       mean lunar (north) node longitude
src/Bio/Biorhythm.php        day numbers, cycle values, per-person peaks/troughs
src/Bio/Synchrony.php        pair metrics (see "Biorhythm affinity")
src/Love/NameAffinity.php    AMORE algorithm
src/Love/SignAffinity.php    sign-vs-sign scoring, ranking, "love of your life" sign
src/Love/Common.php          shared traits of two people's Sun/Moon/Ascendant
src/Tarot/Reading.php        deterministic 3-day draw
src/Content/Bodies.php       planet glyphs, titles, taglines, short meanings
src/Content/Traits.php       keywords per sign / element / modality / complementary pair
src/Content/TarotDeck.php    22 Major Arcana: id, number, name, upright, reversed (love-oriented, 1-2 sentences)
src/SelfReading.php          pure: builds the whole Self-discovery view-model
src/LoveReading.php          pure: builds the whole Love view-model
templates/partials/person-fields.php   date/time/city(+hidden lat/lon/tz) fields for a given prefix and required flag
templates/partials/icons.php           icon($name): static inline SVG strings (printer, heart, bolt, bulb); trusted constants only
templates/partials/bio.php             biorhythm block with inline SVG polylines
templates/self-result.php
templates/love-result.php
public/assets/print.js
```

### Files to change

- `src/Chart.php`: keep `compute()` untouched (existing tests keep passing). Add `Chart::full(...)` (same args as `compute`) and `Chart::partial(...)`, see below.
- `src/Request.php`: factor the per-person validation into `Request::parsePerson()`; `parse()` keeps its exact behaviour and messages (default = Self mode, un-prefixed keys). Add `Request::parseLove()` and `Request::parseToday()`.
- `src/Astro/Zodiac.php`: no change (`fromLongitude` is reused for planets).
- `public/index.php`: dispatch on `mode` (`self` | `love` | absent). Absent mode with `date`/`city` present = Self (old shared URLs keep working). Absent mode and no input = chooser only. Sends `X-Robots-Tag: noindex` and `Cache-Control: private, no-store` when a result is rendered.
- `templates/home.php`: hero, **mode chooser** (two large link cards `?mode=self`, `?mode=love`, plain `<a>`, no JS), the form for the selected mode, then `include` of the right result template; adds `<meta name="robots" content="noindex">` on result pages and a "Print" button area.
- `public/assets/autocomplete.js`: stop using fixed ids; bind every `[data-place]` container (one per person), looking up its inputs by `data-field="city|lat|lon|tz"`, list and status by `data-role`. Same behaviour as today per container. The existing ids in `home.php`/CSS must be migrated (implementer greps `styles.css` for `#city`, `#city-list`, `#status`).
- `public/assets/styles.css`: icon sizes, two-column person form (stacks under 640px), planet grid, bio chart classes (`.bio--physical`, `.bio--emotional`, `.bio--intellectual` set `stroke`; polylines use the `points` attribute, no `style=`), tarot card face (reversed = `transform: rotate(180deg)` via class), and an `@media print` block: white background, black text, hide `.stars`, forms, chooser, `.no-print`, buttons and footer links; `break-inside: avoid` on cards; print-only header showing names and date (`.print-only`, `display:none` on screen). Print colours must not rely on backgrounds (add borders).
- `tests/run.php`: add the checks of the Test plan (may be split into `tests/cases/*.php` required by the runner).
- Docs (documenter, after implementation): architecture.md (goal, diagram, privacy, extension points), code.md (map, data shapes, recipes), roadmap.md (Done + remaining ideas), README.md.

### Request parameters

Self: `mode=self&date&time&city[&lat&lon&tz]` (unchanged keys).
Love: `mode=love` and for each person prefix `a_` (user) / `b_` (loved): `{p}name, {p}date, {p}time, {p}city, {p}lat, {p}lon, {p}tz`. Optional `on=YYYY-MM-DD`.

Validation (all in `Request`): date `YYYY-MM-DD` valid and 1000-2100 (as today); time `hh:mm`; coordinates ranges; `tz` must be in `DateTimeZone::listIdentifiers()` else the city text is geocoded server-side (as today); `mode` whitelisted; `on` valid date 1900-2100 else ignored. Names: trimmed, 1-40 characters (counted with `preg_match_all('/./u')`), no control characters, must contain at least one `\p{L}`. Maximum 40 characters per name bounds every letter count at 80 (two digits).

Person A (user): name, date, time, city all required (same as today plus name). Person B: name required; date, time, city optional but validated when non-empty. Rules for B: a time is used only when date **and** city are present (the time zone is needed); a city is geocoded only when date and time are present. Anything ignored produces a note ("Birth time ignored: it needs a date and a place."). Geocoding at most twice per request (disk cache).

`Request::parseLove(array $q, Geocoder $g): array{a: ?array, b: ?array, errors: list<string>, notes: list<string>}`. Person shape:

```php
['name' => string,
 'date'  => ?array{year:int, month:int, day:int},
 'time'  => ?array{hour:int, minute:int},
 'place' => ?array{lat:float, lon:float, tz:string, city:string}]
```

`Request::parseToday(array $q, string $realToday): string`. (`$realToday` is passed in by `public/index.php`, which is the only place reading the clock.)

### Chart additions (pure)

```php
Chart::full(int $y,int $mo,int $d,int $h,int $mi,float $lat,float $lon,string $tz): array
// = Chart::compute(...) + [
//   'planetsSupported' => bool,           // 1800 <= year <= 2100
//   'planets' => ['mercury'=>['position'=>$pos,'retrograde'=>bool], ... 'pluto'=>...],  // [] when unsupported
//   'node'    => $pos,                    // mean north node, any year
//   'venus' etc. reachable via 'planets']
// retrograde = longitude(jd+0.5) - longitude(jd-0.5) (normalised to +-180) < 0.

Chart::partial(int $y,int $mo,int $d, ?int $h, ?int $mi, ?float $lat, ?float $lon, ?string $tz): array
// ['sun'=>?pos, 'moon'=>?pos, 'ascendant'=>?pos, 'approx'=>['sun'=>bool,'moon'=>bool]]
```

`partial` rules:
- hour, minute, lat, lon, tz all present: identical to `compute` (all three signs, `approx` both false).
- otherwise (date only, or time without place): `ascendant = null`; Sun and Moon are taken at 12:00 UTC of the date; `approx[body] = true` when the body's sign at 00:00 UTC differs from its sign at 24:00 UTC of that date (cusp day for the Sun; for the Moon this happens on roughly one date in two or three). The UI then says "may be the adjacent sign depending on birth time". A time without a place is ignored (Sun/Moon depend on the UTC instant, which needs the time zone).

### Planets (`Astro\Planets`)

API: `Planets::BODIES = ['mercury','venus','mars','jupiter','saturn','uranus','neptune','pluto']`; `Planets::supports(float $jd): bool` (JD of 1800-01-01 to 2100-12-31); `Planets::longitude(string $id, float $jd): float` (degrees, tropical, of date); `Planets::all(float $jd): array<string,float>`. Throws `InvalidArgumentException` for unknown id; callers check `supports`.

Method (Standish, "Keplerian Elements for Approximate Positions of the Major Planets", Table 1; elements and their rates per Julian century from J2000, `T = Angles::centuries($jd)`):

| body | a (AU) | da | e | de | I (deg) | dI | L (deg) | dL | varpi (deg) | dvarpi | Omega (deg) | dOmega |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Mercury | 0.38709927 | 0.00000037 | 0.20563593 | 0.00001906 | 7.00497902 | -0.00594749 | 252.25032350 | 149472.67411175 | 77.45779628 | 0.16047689 | 48.33076593 | -0.12534081 |
| Venus | 0.72333566 | 0.00000390 | 0.00677672 | -0.00004107 | 3.39467605 | -0.00078890 | 181.97909950 | 58517.81538729 | 131.60246718 | 0.00268329 | 76.67984255 | -0.27769418 |
| Earth-Moon bary | 1.00000261 | 0.00000562 | 0.01671123 | -0.00004392 | -0.00001531 | -0.01294668 | 100.46457166 | 35999.37244981 | 102.93768193 | 0.32327364 | 0.0 | 0.0 |
| Mars | 1.52371034 | 0.00001847 | 0.09339410 | 0.00007882 | 1.84969142 | -0.00813131 | -4.55343205 | 19140.30268499 | -23.94362959 | 0.44441088 | 49.55953891 | -0.29257343 |
| Jupiter | 5.20288700 | -0.00011607 | 0.04838624 | -0.00013253 | 1.30439695 | -0.00183714 | 34.39644051 | 3034.74612775 | 14.72847983 | 0.21252668 | 100.47390909 | 0.20469106 |
| Saturn | 9.53667594 | -0.00125060 | 0.05386179 | -0.00050991 | 2.48599187 | 0.00193609 | 49.95424423 | 1222.49362201 | 92.59887831 | -0.41897216 | 113.66242448 | -0.28867794 |
| Uranus | 19.18916464 | -0.00196176 | 0.04725744 | -0.00004397 | 0.77263783 | -0.00242939 | 313.23810451 | 428.48202785 | 170.95427630 | 0.40805281 | 74.01692503 | 0.04240589 |
| Neptune | 30.06992276 | 0.00026291 | 0.00859048 | 0.00005105 | 1.77004347 | 0.00035372 | -55.12002969 | 218.45945325 | 44.96476227 | -0.32241464 | 131.78422574 | -0.00508664 |
| Pluto | 39.48211675 | -0.00031596 | 0.24882730 | 0.00005170 | 17.14001206 | 0.00004818 | 238.92903833 | 145.20780515 | 224.06891629 | -0.04062942 | 110.30393684 | -0.01183482 |

The implementer MUST re-check these constants against the published table before relying on them (they are typed from memory of the public JPL document); the J2000 tests below are the safety net.

Per body: `omega = varpi - Omega`; `M = L - varpi` normalised to (-180, 180]; solve Kepler `E - e sin E = M` (radians) by Newton iteration from `E0 = M + e sin M`, stop at `|dE| < 1e-12` or 30 iterations; `x' = a (cos E - e)`, `y' = a sqrt(1-e^2) sin E`; rotate to the J2000 ecliptic frame:

```
x = (cos w cos O - sin w sin O cos I) x' + (-sin w cos O - cos w sin O cos I) y'
y = (cos w sin O + sin w cos O cos I) x' + (-sin w sin O + cos w cos O cos I) y'
```
(`w`=omega, `O`=Omega, `I`=inclination; z is not needed for longitude, and the inclination projection is what puts Pluto at ~250.6 rather than ~249.2 degrees heliocentric.) Geocentric vector = planet minus Earth (Earth from the Earth-Moon barycentre row); `lambda = atan2(y, x)`; then convert J2000 -> of date by adding `1.3969713 T + 0.0003086 T^2` degrees (general precession in longitude) and `Angles::nutationLongitude($T)`; normalise to [0, 360). Ignored (document in code comment): light-time (<= 0.01 degrees), annual aberration (~0.006), Earth-vs-barycentre offset (<= 0.005 degrees, Mercury/Venus worst case), latitude. Expected accuracy over 1800-2050: roughly 0.01 degrees for the inner planets and Mars and a few hundredths of a degree for Jupiter-Pluto (Standish quotes tens of arcseconds up to a couple of arcminutes for Jupiter/Saturn). More than enough for sign placement (only within ~0.05 degrees of a sign boundary the sign may be wrong, and the UI says planets are approximate). Retrograde flag from the numeric derivative above.

`MeanNode::longitude(float $jd): float`: Meeus 47.7, `125.04452 - 1934.136261 T + 0.0020708 T^2 + T^3/450000`, normalised. This is the mean **north** node; valid for any year. South node = +180.

Planet copy (`Content\Bodies`): per id `glyph` (Sun U+2609, Moon U+263D, Mercury U+263F, Venus U+2640, Mars U+2642, Jupiter U+2643, Saturn U+2644, Uranus U+2645, Neptune U+2646, Pluto U+2647, North Node U+260A), `title`, `tagline`, and one sentence of meaning per planet (sign-independent, to keep the copy small: 11 sentences).

### Sign affinity (`Love\SignAffinity`)

Transparent, deterministic, no randomness. Reference points of the user (`refs`): Sun, Moon, Ascendant, plus Venus and Mars when planets are supported (else they are dropped and weights renormalise). Candidates: the 11 signs other than the user's Sun sign. For each pair (reference sign R, candidate X) with `d = min((X-R) mod 12, (R-X) mod 12)`:

`pair(R, X) = 0.60 * Aspect[d] + 0.25 * Ruler(R, X) + 0.15 * Modality(R, X)`

- `Aspect` points (traditional aspect between signs): d0 same sign 70, d1 semi-sextile 35, d2 sextile 80, d3 square 40, d4 trine 100, d5 quincunx 30, d6 opposition 65. (Even distances = compatible elements, odd = clashing elements, so element compatibility is already inside this table.)
- `Ruler(R, X)`: traditional rulers Aries Mars, Taurus Venus, Gemini Mercury, Cancer Moon, Leo Sun, Virgo Mercury, Libra Venus, Scorpio Mars, Sagittarius Jupiter, Capricorn Saturn, Aquarius Saturn, Pisces Jupiter. Planetary friendship (classical table; friend 100, neutral 50, enemy 0; symmetrised by averaging the two directions; same ruler = 100):
  Sun: friends Moon, Mars, Jupiter; neutral Mercury; enemies Venus, Saturn.
  Moon: friends Sun, Mercury; neutral Mars, Jupiter, Venus, Saturn.
  Mars: friends Sun, Moon, Jupiter; neutral Venus, Saturn; enemy Mercury.
  Mercury: friends Sun, Venus; neutral Mars, Jupiter, Saturn; enemy Moon.
  Jupiter: friends Sun, Moon, Mars; neutral Saturn; enemies Mercury, Venus.
  Venus: friends Mercury, Saturn; neutral Mars, Jupiter; enemies Sun, Moon.
  Saturn: friends Mercury, Venus; neutral Jupiter; enemies Sun, Moon, Mars.
- `Modality(R, X)`: different modality 100 (they complete each other), same modality 50.

Two weightings over the available references:
- **Affinity** (balanced): Sun 3, Moon 3, Ascendant 2, Venus 2, Mars 1. `score(X) = sum(w_r * pair(r, X)) / sum(w_r)`.
- **Love** (heart-focused): Venus 3, Moon 3, Sun 2, Mars 2, Ascendant 1, plus **+10** if X is the user's Descendant sign (opposite the Ascendant, the traditional sign of the partner), capped at 100.

Ranking: sort by score descending; ties broken by higher Moon pair score, then lower zodiac index (stable and deterministic). `mostAffine` = top 3 by Affinity; `soulmate` = top 1 by Love ("the love of your life" sign; may or may not equal the affinity winner). Each entry carries `reasons`: the two best-contributing references with their aspect name and ruler relation, so the template can explain ("Moon in Cancer trines Pisces; rulers Moon and Jupiter are neutral"). Copy is light: this is presented as tradition, for wonder.

```php
SignAffinity::rank(array $refs): array   // $refs e.g. ['sun'=>0,'moon'=>3,'ascendant'=>6,'venus'=>1,'mars'=>11] (sign indexes 0-11)
// ['ranking' => list<['index'=>int,'sign'=>Zodiac::SIGNS[i],'affinity'=>float,'love'=>float,'reasons'=>list<array{ref:string,aspect:string,ruler:string}>]>,
//  'mostAffine' => list<entry> (3), 'soulmate' => entry, 'descendant' => ?int]
```
Scores are returned unrounded; templates show `round()`.

### Biorhythms (`Bio\Biorhythm`, `Bio\Synchrony`)

Day numbers are whole days since 1970-01-01: `Biorhythm::dayNumber(int $y,int $m,int $d): int` (days-from-civil algorithm, no calendar extension) and `Biorhythm::date(int $day): string` (`Y-m-d`). Birth **calendar date** only is used (time/place not needed, so it works for partial loved persons).

`Biorhythm::CYCLES = ['physical'=>23,'emotional'=>28,'intellectual'=>33]`. `Biorhythm::value(int $birthDay, int $day, int $period): float = 100 * sin(2 pi (day - birthDay) / period)`.

`Biorhythm::forPerson(int $birthDay, int $today, int $horizon = 30): array` returns per cycle `['period'=>int,'value'=>int (today, rounded),'trend'=>'rising'|'falling','nextPeak'=>'Y-m-d','nextTrough'=>'Y-m-d','curve'=>list<int> (horizon+1 rounded values, today..today+horizon)]`. Peak days are at `birthDay + P (k + 1/4)`, troughs at `birthDay + P (k + 3/4)`, rounded to the nearest day with half rounding up (`floor(x + 0.5)`), choosing the smallest k whose rounded day is >= today. Critical (zero-crossing) days are not reported.

**Biorhythm affinity (the honest metric).** For one cycle of period P, with birth day numbers bA, bB, let `delta` = (bB - bA) folded into (-P/2, P/2] by whole days. Both curves are the same sinusoid shifted by a constant `delta` days, so they are either always "similar" or always "out of step": exact simultaneous peaks happen only when `delta` is 0 (within about 1 day), and a person-to-person offset never changes with time. Therefore we report, per cycle:

- `delta` (days) and `peaksTogether = abs(delta) <= 1`.
- `sync` = `(1 + cos(2 pi delta / P)) / 2 * 100`, in 0..100. 100 = in phase, 50 = a quarter-cycle apart, 0 = exact opposites. (This is the rescaled correlation of the two curves over a full cycle.)
- `amplitude` = `100 * cos(pi delta / P)`: the combined curve is `A + B = 2 cos(pi delta/P) * sin(2 pi (d - bA - delta/2) / P)`, so this is how strong the shared swing is (a relative 0-100 of the maximum possible). `flat = amplitude < 10`: the two cancel each other out; the UI says "you balance each other" and does not advertise combined peaks.
- `nextCombinedPeak` / `nextCombinedTrough`: the next dates (>= today) where the **combined** curve is highest/lowest: `bA + delta/2 + P (k + 1/4)` and `+ 3/4`, rounded as above; null when `flat`. This is the "next date the two of you peak/dip together" that is always well defined.
- `nextBothHigh` / `nextBothLow`: first day within `horizon` (default 120 days) where both values are >= +50 (resp. <= -50): `['date'=>..., 'a'=>int, 'b'=>int]` or null (null is common when sync is low; the UI says "not in the next 4 months"). The threshold is the constant `Synchrony::THRESHOLD = 50`.
- `overall` = mean of the three unrounded `sync` values, plus a verdict band by `Content` copy: >= 75 in tune, 40-75 complementary, < 40 out of step. Each band's text must say that offsets are permanent and that "out of step" is not a verdict on the relationship.
- `curveA`, `curveB` (rounded, today..today+30) for the overlaid SVG.

`Synchrony::pair(int $bA, int $bB, int $today, int $horizon = 120): array{cycles: array<string, array>, overall: float}`.

Biorhythms are a popular but not scientifically validated theory; the footer/disclaimer says so (the site already says "for wonder").

### Name affinity (`Love\NameAffinity`)

```php
NameAffinity::compute(string $nameA, string $nameB): array
// ['counts'=>['A'=>int,'M'=>int,'O'=>int,'R'=>int,'E'=>int], 'slots'=>list<int> (5), 'start'=>int, 'chain'=>list<int>, 'percent'=>int, 'noLetters'=>bool]
NameAffinity::fromCounts(array $counts): array   // same result, testable directly
NameAffinity::normalize(string $name): string    // lowercase ASCII letters only
NameAffinity::step(int $value): int              // one pairwise-sum step
```

1. **Normalise**: concatenate both names; fold case and accents with a `strtr` table for precomposed Latin letters (grave/acute/circumflex/tilde/diaeresis/ring/macron/breve/ogonek/caron/stroke variants of a, e, o, r map to the plain letter; ae and oe ligatures map to two letters; ss for sharp s) and strip combining marks with `preg_replace('/\p{Mn}+/u', '', ...)`. No `intl`/`Normalizer`/`mbstring` dependency (not guaranteed on shared hosting). Letters other than A, M, O, R, E are ignored.
2. **Count** A, M, O, R, E in that order.
3. **Slots** (the "digit string"). `slot_i = c_i` when `c_i < 10`; when `c_i >= 10`, `slot_i = c_i mod 10` stays in its own slot and its **first (leading) digit** (`(int) substr((string) c_i, 0, 1)`) is added to the **preceding** slot (A has no predecessor, so A's carry goes to the **following** slot, M). Carries are computed from the original counts and summed, so a slot can exceed 9 (e.g. M=9 receiving 1 becomes 10); that is allowed because step 4 reads the slots as a polynomial, i.e. the same carry-overlapping convention the pair sums use. For counts >= 100 (unreachable with the 40-character name limit) middle digits are dropped; documented, never an error. Example A=3,M=2,O=12,R=2,E=1 -> 2 stays in O, 1 added to M (2+1=3) -> slots 3,3,2,2,1 -> 33221 (matches the owner's example).
4. **Start value** `V = sum slot_i * 10^(4-i)`. **Loop**: while `V > 100`: take the decimal digits `d_0..d_{n-1}` of V (n >= 3 here) and set `V = sum_{i=0}^{n-2} (d_i + d_{i+1}) * 10^(n-2-i)` (the pair sums overlap, e.g. 6+6=12 and 6+9=15 give 12*10+15 = 135). `chain` records the start and every intermediate V. The loop always terminates because the new value is at most V/5.
5. **Result**: `percent = V` (the first value <= 100). If the start value is already <= 100 it is the answer and `chain` has a single element (e.g. only one E in total -> 1%; a single O -> 100%). If no A/M/O/R/E letter exists, `percent = 0`, `noLetters = true` and the UI says "These names share no letters of AMORE".

Worked example (owner's): Andrea Lorenzani + Silvia Pellico -> A=4, M=0, O=2, R=2, E=3 -> 40223 -> digits 4,0,2,2,3 -> sums 4,2,4,5 -> **4245** -> 6,6,9 -> **669** -> 12,15 -> **135** -> 4,8 -> **48** (stop, 48 <= 100). Percent = **48**.

Reading note (document in the class docblock): "its first digit is added to the highest digit" is interpreted as above (the last digit stays in the letter's own slot, the first digit goes to the preceding slot), because that is the only reading that reproduces 33221 from 3,2,12,2,1 without ambiguity about which digit is "highest".

### Common values (`Love\Common`)

`Common::between(array $chartA, array $chartB): array` takes two arrays of the `Chart::partial` shape and returns `['items'=>list<...>, 'score'=>?int, 'basis'=>list<string>]` where an item is `['body'=>'sun'|'ascendant'|'moon','a'=>$pos,'b'=>$pos,'level'=>'sign'|'element'|'complement'|'modality'|'none','points'=>int,'traits'=>list<string>,'approx'=>bool]`. A body is compared only when both people have it (so date only -> Sun, and Moon from the date; date+time+city -> all three). Level, first match wins: same sign (100 points; traits = that sign's 4 keywords), same element (80; element keywords), complementary elements Fire-Air or Earth-Water (60; one line per pair), same modality (40; modality keywords), otherwise none (20; "different paths that can teach each other"). `score` = weighted mean of the points (Sun 2, Moon 2, Ascendant 1), rounded; null when nothing is comparable. `basis` lists what was used ("Sun, Moon") and what is missing and why. `approx` is true when either side's value is approximate in `partial`.

### Tarot (`Tarot\Reading`)

`Reading::draw(string $seed, string $today, int $days = 3): list<array{date:string, card:array (TarotDeck entry), reversed:bool, meaning:string}>`. `$seed` is built by `LoveReading`: `lower-folded nameA | nameB | dateA | dateB` (missing date = empty). For day index i (0..2), `date_i = today + i`: `h = hash('sha256', $seed . '|' . date_i . '|' . i)`; `card = hexdec(substr(h, 0, 8)) % 22`; `reversed = hexdec(substr(h, 8, 8)) % 2 === 1`. If `card` is already drawn on an earlier day of the reading, advance `(card + 1) % 22` until unused, so the three cards are always different. Same input, same day = same result on every reload; the reading rolls forward with the date. The shown meaning is the card's `upright` or `reversed` love text. The deck order is the standard: 0 The Fool, 1 The Magician, 2 The High Priestess, 3 The Empress, 4 The Emperor, 5 The Hierophant, 6 The Lovers, 7 The Chariot, 8 Strength, 9 The Hermit, 10 Wheel of Fortune, 11 Justice, 12 The Hanged Man, 13 Death, 14 Temperance, 15 The Devil, 16 The Tower, 17 The Star, 18 The Moon, 19 The Sun, 20 Judgement, 21 The World. Presented as entertainment.

### View-models and controller

```php
SelfReading::build(array $input, string $today): array
// ['chart'=>Chart::full(...), 'refs'=>..., 'affinity'=>SignAffinity::rank(...), 'bio'=>Biorhythm::forPerson(...), 'notes'=>list<string>]
LoveReading::build(array $a, array $b, string $today): array
// ['affinity'=>NameAffinity::compute(...), 'charts'=>['a'=>partial,'b'=>partial|date-less], 'common'=>Common::between(...)|null,
//  'bio'=>Synchrony::pair(...)|null (null when B has no date), 'tarot'=>Reading::draw(...), 'today'=>string, 'notes'=>list<string>]
```
Both are pure; `public/index.php` only does `Request::*`, calls a builder, then includes the templates.

### UX

- Home: hero, two cards "Self discovery" and "Love" (large tap targets, glyphs and SVG icons), then the selected form. Mobile first: single column; person columns side by side at >= 640px.
- Self result: big three as today; "The planets" grid (glyph, planet, sign, degree, "R" retrograde badge, element colour class) plus the mean node; "Your affinities" (top 3 signs with score bar and reasons, and the "love of your life" sign card); biorhythm block with three bars (today's value) and a 30-day SVG curve chart with legend and next peak/trough dates; note on accuracy; Print button.
- Love result: heart-percent card with the AMORE counts and chain (collapsible `<details>`), biorhythm synchrony (per cycle: sync bar, delta days, combined peak/trough dates, both-high/both-low, overlaid curves), common values (cards per body), 3 tarot cards with date, name, upright/reversed marker and text; Print button.
- Print: both results print on one or two A4 pages from the same URL; print-only header with names and date; no JS needed besides the button.
- Accessibility: charts are decorative SVG with `role="img"` and an `aria-label` summarising the numbers; the same numbers are present as text; colour is never the only carrier (labels on bars).

### Privacy (change to document)

Names (and the loved person's birth data) are in the GET URL, so they can appear in web-server access logs, the browser history and shared links. Mitigations: `Referrer-Policy: same-origin` already set; add `X-Robots-Tag: noindex`, `<meta name="robots" content="noindex">` and `Cache-Control: private, no-store` on result pages; no storage by the app; only the city text goes to a third party (D5). The Love form shows the sentence "Names and dates appear in the address bar; share the link only with people you trust." Moving to POST was rejected because it breaks D8 shareable URLs; it can be an option later (a "private mode" ADR).

## Alternatives considered

- **Swiss Ephemeris / VSOP87 full series / Meeus ch. 33**: more accurate but needs a binary or 1000s of coefficients; against D1/D3 ("small, in-house"). Keplerian elements are 9 rows of constants; accuracy is plenty for signs.
- **Standish Table 2 (3000 BC-3000 AD) with the extra b, c, s, f terms for Jupiter-Pluto**: would cover the full 1000-2100 range, but adds 4 terms x 4 planets of constants and a second table to verify. Deferred; cost is that years 1000-1799 show the big three only. Can be added later inside `Planets` without API change.
- **Literal simultaneous biorhythm peaks**: impossible except for offsets about 0; presenting them would mislead. Replaced by the combined-curve metric above.
- **Name affinity digit rules**: carrying to the "highest digit" (literal) is ambiguous; clamping a slot above 9 by digit-sum would lose information; the polynomial reading is consistent with the pair-sum rule.
- **Sign scoring by element only**: too coarse (12 signs collapse to 4 buckets); aspects + ruler friendship + modality gives 12 distinct, explainable values.
- **Random tarot with session storage**: needs state and breaks shared URLs; hash-seeded draw is stateless.
- **PDF generation for print**: needs a library; `@media print` is enough.
- **Separate pages per mode (`love.php`)**: slightly cleaner routing but duplicates the shell; one front controller with `mode` keeps old URLs valid.
- **Storing readings in MySQL**: not needed (D2).

## Risks

- Typing errors in the element table: mitigated by J2000 and ingress tests; implementer cross-checks the table with the public source.
- Planet signs near a boundary (within ~0.05 degrees) or Jupiter/Saturn extrapolated after 2050 may be off; the UI states "planets are approximate".
- Date-only Sun/Moon for the loved person can be the adjacent sign; flagged with `approx`.
- Names in URLs (see Privacy); name affinity, biorhythms, scores and tarot are entertainment: footer disclaimer.
- Hand-written scoring tables and 22 x 2 tarot texts plus 12 x 4 trait keywords are a lot of copy: reviewer checks tone and length, no fake medical or fate claims.
- `tests/run.php` grows; split into `tests/cases/` if it exceeds ~400 lines.
- Print layout varies by browser; verify manually in Chrome and Firefox "Print to PDF".
- `preg` with `/u` fails on invalid UTF-8: `Request` rejects names where `preg_match` returns false.
- Unknown: `intl` absence is handled; PHP `hash()`/`sha256` is core.

## Test plan

All tolerances in degrees unless stated. Add to `tests/run.php`; `php tests/run.php` must pass; existing 18 checks untouched.

**Planets at J2000 (JD 2451545.0, geocentric ecliptic longitude of date; at T=0 date = J2000):** Mercury 271.9, Venus 241.6, Mars 327.9, Jupiter 25.3, Saturn 40.4, Uranus 314.8, Neptune 303.2, Pluto 251.5, tolerance 0.5. These values were sanity-checked against recalled published ephemeris positions for 2000-01-01 12:00 (e.g. Mercury 1 Cap 53', Venus 1 Sag 34', Mars 27 Aqu 57', Jupiter 25 Ari 15', Saturn 10 Tau 24', Uranus 14 Aqu 49', Neptune 3 Aqu 11', Pluto 11 Sag 27') and a hand check of Pluto (M = 14.86 degrees, true anomaly 25.18, heliocentric about 250.6, plus about 0.9 geocentric correction = about 251.5). If a body is outside tolerance, check the table constants first, never widen the tolerance without an independent source.
**Ingress checks (circular difference, tolerance 0.5, at 12:00 UTC):** Jupiter 2024-05-25 -> 60 (Gemini ingress); Uranus 2025-07-07 -> 60; Neptune 2025-03-30 -> 0 (wrap-around!); Saturn 2025-05-25 -> 0.
**Retrograde flags:** Mercury 2025-03-25 true, Mercury 2025-06-01 false; Mars 2025-01-01 true; Jupiter 2025-01-01 true; Venus 2025-06-01 false.
**Kepler solver:** e = 0 gives E = M; e = 0.2, M = 1 rad satisfies `E - e sin E = M` to 1e-10.
**Support range:** `Planets::supports` true for 1800-01-01 and 2100-12-31, false for 1799-12-31; `Chart::full` for 1500 -> `planetsSupported=false`, `planets=[]`, big three still present.
**Mean node:** 2000-01-01 12:00 TT -> 125.04 (tol 0.01); Meeus example 1987-04-10 0h: 11.2531 degrees (tol 0.01, check against Meeus 47.b).
**Chart::partial:** 2000-03-20 date only -> Sun `approx=true` (equinox about 07:35 UTC), noon Sun sign Aries; 2000-07-15 date only -> Sun Cancer, `approx=false`; ascendant null for date-only; with full data equals `Chart::compute` exactly (Einstein case from the existing test: Pisces/Sagittarius/Cancer); `approx.moon` equals (sign at 00:00 UTC != sign at 24:00 UTC) computed independently with `Moon::longitude`.

**Name affinity (must pass):**
- `fromCounts(['A'=>4,'M'=>0,'O'=>2,'R'=>2,'E'=>3])`: slots 4,0,2,2,3; start 40223; chain exactly `[40223, 4245, 669, 135, 48]`; percent 48.
- `compute('Andrea Lorenzani', 'Silvia Pellico')`: counts A4 M0 O2 R2 E3, percent 48. Same with `'ANDRÉA  lorenzani'`, NFD-decomposed accents and mixed case (case/accent insensitivity), and swapped order of the two names (same result).
- `step`: `step(40223)=4245`, `step(4245)=669`, `step(669)=135`, `step(135)=48`.
- Carry: counts A3 M2 O12 R2 E1 -> slots 3,3,2,2,1, start 33221.
- Carry from A goes forward: A12 M0 O0 R0 E0 -> slots 2,1,0,0,0; chain `[21000, 3100, 410, 51]`; percent 51.
- Carry above 9 allowed: A0 M9 O15 R0 E0 -> slots 0,10,5,0,0; start 10500; chain `[10500, 1550, 705, 75]`; percent 75.
- Degenerate: all zero -> percent 0, `noLetters=true`; E=5 only -> chain `[5]`, percent 5; O=1 only -> start 100, chain `[100]`, percent 100; names with no letters (digits only) rejected by `Request`.
- Termination property: for 1000 pseudo-random count tuples (seeded `mt_srand(1)`) with each count 0-80 the result is an int in 0..100 and the chain strictly decreases after the first element.

**Biorhythms:**
- `dayNumber(1970,1,1)=0`, `dayNumber(2000,1,1)=10957`, leap-day check 2000-02-29 and 1900-03-01 (not a leap year, 1900-03-01 = -25508).
- Person born 1990-01-01, on 1990-01-08 (t=7): physical 94, emotional 100, intellectual 97 (sin(2pi*7/23)=0.9422, sin(2pi*7/28)=1, sin(2pi*7/33)=0.9718). t=0 gives 0/0/0; emotional t=21 gives -100; emotional t=14 gives 0.
- `forPerson` for birth 1990-01-01, today 1990-01-01: emotional peak 1990-01-08, trough 1990-01-22; physical peak 1990-01-07 (5.75 rounds to 6), trough 1990-01-18 (17.25 -> 17); intellectual peak 1990-01-09 (8.25 -> 8), trough 1990-01-26 (24.75 -> 25). Curve length 31 with first element 0.
- Synchrony, identical birth dates: all `sync=100`, `amplitude=100`, `peaksTogether=true`; with today 1990-01-01 emotional `nextBothHigh` 1990-01-04 (t=3, sin 0.6235; t=2 gives 0.434 < 0.5), `nextBothLow` 1990-01-18 (t=17, -0.6235; t=16 gives -0.434).
- Synchrony, A 1990-01-01 and B 1990-01-15 (delta 14 days): emotional delta 14, sync 0.0, amplitude 0, `flat=true`, combined peak null; physical delta -9, sync 11.2 (tol 0.1), amplitude 33.5 (tol 0.2); intellectual delta 14, sync 5.6 (tol 0.1); overall 5.6 (tol 0.1).
- Combined-curve dates, A 1990-01-01, B 1990-01-03 (delta 2), today 1990-02-01, emotional: next combined peak 1990-02-06 (bA + 1 + 7 = +8, then +28k >= +31 -> +36) and trough 1990-02-20 (+22 -> +50); physical sync = (1+cos(2pi*2/23))/2 (about 92.7, tol 0.2).
- B born 28 days after A: emotional sync 100.

**Sign affinity (hand-computed with all five references = Aries, candidates exclude Aries):** Leo 100, Sagittarius 100, Libra 59.0 (0.6*65 + 0.25*50 + 0.15*50), Cancer 50.25 (0.6*40 + 0.25*75 + 0.15*50), Taurus 48.5 (0.6*35 + 0.25*50 + 0.15*100); ranking starts Leo, Sagittarius (tie broken by lower index); Libra love score 69.0 (59 + Descendant bonus 10 when Ascendant is Aries); `mostAffine` has 3 entries; same input twice gives identical output; dropping Venus/Mars refs renormalises weights (all-Aries refs still give Leo 100). Ruler symmetry: `Ruler(a,b) == Ruler(b,a)` for all pairs; same ruler (Taurus/Libra) = 100.

**Common values:** Aries/Aries -> level sign 100; Aries/Leo -> element 80 with Fire keywords; Aries/Libra -> complement 60; Aries/Cancer -> modality 40; Aries/Taurus -> none 20. Date-only partner (no ascendant): items contain only sun and moon, `basis` explains. `score` null when nothing comparable.

**Tarot:** same (seed, today) gives identical output twice; three distinct cards; 22-card deck has unique ids and non-empty upright/reversed texts; changing `today` by one day changes the dates and shifts the draw; changing a name changes the draw (probabilistic: assert on a fixed pair of seeds whose result the implementer freezes as golden values on the first green run); never a card index outside 0-21.

**Request:** self mode: old queries without `mode` still parse as before (all existing request checks); love: missing `a_name` errors; B with only a name is valid with notes none; B time without date/city produces the "ignored" note; B with city and date but no time is valid, no geocoding; name of 41 characters rejected; name without letters rejected; invalid `mode` shows the chooser; tampered `b_tz` falls back to geocoding path; invalid `on` ignored.

**Layering/regression:** grep test or reviewer check that `src/Astro`, `src/Time`, `src/Chart.php`, `src/Love`, `src/Bio`, `src/Tarot`, `src/SelfReading.php`, `src/LoveReading.php` contain no `$_GET`, `echo`, `file_`, `curl`, `time()`, `date(` without an explicit timestamp argument, `new DateTime()` without argument.

**Manual:** `php -S localhost:8081 -t public`; Self mode with Rome 1990-07-15 08:30; Love mode with the Andrea Lorenzani / Silvia Pellico names (48%); loved person with only a name; mobile viewport 360px; Print preview of both results (no form, no stars, black on white, cards unbroken); CSP console shows no violations (no inline script/style; SVG uses attributes and classes only).

## Implementation notes

Deviations and interpretations found while implementing (the design above otherwise stands):

- Planet constants were checked against the J2000 and ingress tests. `Planets::JD_TO` is 2101-01-01 (JD 2488434.5), so the whole of 2100 is supported; `JD_FROM` is 1800-01-01.
- The verdict band is returned as `band` (`tune` | `complementary` | `apart`) by `Synchrony::pair`; its copy is `Content\Traits::VERDICTS`.
- "Biorhythms together" is interpreted as: per cycle, `sync`/`amplitude` of the offset, the next combined peak/trough, and the next day both curves are >= +50 / <= -50; `overall` is the mean sync. Literal simultaneous peaks are reported only through `peaksTogether`.
- Name affinity carry rule (">= 10 digit"): the leading digit of a count >= 10 goes to the preceding slot (to the following one for A), the count mod 10 stays; slots may exceed 9 and are read as a polynomial, as specified. Reproduces 33221 and the 48% example.
- Request: person validation is `Request::parsePerson()` (with an error prefix: errors read "You: ..." and "Loved person: ..."), `Request::parseLove()` returns `a`/`b`, `Request::mode()` and `Request::parseToday()` exist as designed.
- Bars are `<meter>` elements; chart legends use `.swatch` elements that print as solid/dotted/dashed black lines (no reliance on background colours).
- `public/index.php` reads the clock (`gmdate`) and applies `on=`; all builders stay pure.

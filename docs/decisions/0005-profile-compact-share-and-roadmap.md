# 0005 — Current position, browser memory, compact share code, and the derived-features batch

- Status: accepted (all five phases implemented 2026-10-09; see "As shipped" for the deviations)
- Date: 2026-10-09

## As shipped (deviations from the proposal)

- **Coordinates keep 5 decimals.** The owner overrode the 0.01-degree plan: `Request::COORD_DECIMALS` stays 5, and every statement below about a 0.01-degree grid, 2-decimal rounding and shorter places does not apply. Links are therefore a little longer than the size table suggested. The audit keeps 5 decimals too.
- **Terms cookie value is `2`** (`Consent::VALUE`): every visitor accepts the new Terms once (open decision 1 answered yes).
- **Moon-phase windows are 10 degrees** (`MoonPhase::MAIN_HALF_WIDTH`) around new, first quarter, full and last quarter.
- **Minor arcana are composed texts:** 84 rank texts, 12 suit texts, 28 essence lines and the names (open decision 4: composed, not 336 individual texts).
- **Compact code:** format version 1, capped at 400 characters, decoded strictly (a code that does not re-encode to itself is rejected). A `c=` code is merged with the other query keys and the code wins. Place labels are cut at 32 bytes in the code (known limit; the recipient sees the cut label).
- **Reading day in Love follows person A's (the user's) current position.**
- **Browser memory saves the user's own data automatically** on submit (open decision 3: automatic); data that arrived through a link is only saved with "Remember these details".
- Live tarot readings changed with the 78-card deck (open decision 5 accepted); frozen links with `t=` are unaffected.
- The proposed CLAUDE.md addition about browser storage is the owner's to apply.

**ARCHITECTURE CHANGE: yes** (adds components, changes the URL contract and the coordinate precision, raises the audit `format_version` to 3, introduces browser storage, partly supersedes ADR 0004).

## Existing decisions

| Decision | Effect |
|---|---|
| D1 (no Composer, no build, shared hosting) | Holds. All new code is plain PHP and plain JS files. |
| D2 / ADR 0003 (MySQL only for the audit) | Holds. No migration, no new table or column: the new data goes into the audit YAML (`format_version` 3). Browser `localStorage` is client-side memory of the visitor, not server persistence; it is not a database and not a "saved chart" on the server. It is documented in the Terms. |
| D3 (astronomy in-house) | Holds. New astronomical outputs (Midheaven, moon phase, aspects) are pure, with reference tests. |
| D4 (tropical) | Holds. |
| D5 (geocoding server-side, cached) | Holds. One more optional city per person goes through the same endpoint and cache. The cache gets a size cap. |
| D6 (time zones via `DateTimeZone`) | Holds. Also used now to find the local calendar day. |
| D7 (pure core, thin web layer) | Holds. New pure namespaces `Earth`, `Sky`, `Share\ShareCode`, `ChartWheel`; clock and `$_GET` stay in `public/index.php`. |
| D8 (progressive enhancement) | Holds. Everything works without JavaScript; `memory.js` only adds convenience. |
| D10 (shareable state lives in the URL) | Holds and is applied to the new state (see Privacy and sharing). |
| D9, ADR 0001-0003 | Unchanged. |
| **ADR 0004** | **Partly superseded.** (a) 0004 rejected "a compact binary/base64 payload"; it is now adopted, with the readable long form kept for old links. (b) Coordinates stay at 5 decimals (the 2-decimal plan was dropped, see "As shipped"). (c) The tarot code accepts card numbers 0-77 instead of 0-21 (old codes keep their meaning). (d) The share section layout changes. Everything else in 0004 stays. |

The T&Cs gate and `noaudit` keep their meaning: nothing is processed or audited without the cookie; `noaudit` (as a plain parameter or as a flag inside the compact code) skips the audit write.

## Summary

Assumptions:

1. The "current position" is optional for everyone and everywhere; old links and old API calls keep working.
2. The reading day is the calendar day at the user's current position when it is known (Self: the user; Love: person A, the user). Otherwise the UTC date as today. `on=` still wins.
3. The compact code is the primary share link; the readable long query stays accepted forever (it is also what the forms produce).
4. Coordinates keep 5 decimals at parse time for every flow, so a compact link and the page the sender saw are identical (the original 0.01-degree plan was overridden).
5. Browser memory saves automatically when the visitor submits a form they filled themselves; it never saves data that arrived through a shared link unless the visitor presses "Remember these details".

Open questions are listed at the end ("Open decisions").

## Roadmap triage (ideas from `docs/roadmap.md`)

Criteria: (1) needs no new input beyond the current position; (2) deterministic or derived from existing inputs, so it can be rebuilt from the URL or QR.

| Idea | Verdict | Reason |
|---|---|---|
| Chart wheel (SVG) | **Ship** (Self) | Pure drawing of data already computed; no input. |
| Houses and Midheaven | **Ship** Midheaven + whole-sign houses | Same inputs as the Ascendant; no choice offered, so no toggle. Placidus is skipped: undefined near the polar circles and it would call for a house-system selector. |
| Moon phase widget | **Ship** | Derived from the reading day (and from the birth instant for "born under"). |
| Daily horoscope | **Ship** as "Today's sky" | Derived from the reading day and the Sun sign(s); deterministic, no randomness, so `on=` reproduces it. |
| Full synastry | **Ship** (Love) | Derived from the two charts; uses what each person provided. |
| Client time zone for "today" | **Ship through A** | Done by the current position's time zone, not by the browser, so a link opens identically anywhere. |
| More tarot cards (minor arcana) | **Ship** | No input; the tarot code grows compatibly (regex already allows two digits). Composed texts keep the volume bounded. |
| Cap/cleanup of the geocoding cache | **Ship** | Housekeeping, no input. |
| Planets before 1800 | **Skip** | The current data source is only trustworthy for 1800-2100; an honest extension needs another data source. `Planets::supports` stays the gate. |
| Social image card | **Skip** | Needs image rendering (GD may be missing on shared hosting, no dependency allowed) and result pages sit behind the Terms gate and are `noindex`, so a crawler would never see the card. |
| Personal tarot in Self / "Draw again" | **Skip** | Needs 132 more texts for a different audience, or non-derived randomness that breaks "same reading on reload". Owner decision. |
| Count opens of shared links | **Skip** | Needs a marker column and migration; not derived. |
| Manual QR scan test, manual QA, owner review of the tarot texts | **Stays manual** | Process items; added to the test plan as manual checks. |
| MySQL saved charts / accounts, admin view, write rate limit, retention job, mode 600 for `config.php` | **Skip** | Accounts, admin or operations; out of scope. |
| Name field in Self, Italian and other languages, sidereal toggle | **Skip** | New input or toggle. |
| Private POST mode | **Skip** | New mode, contradicts shareable URLs. |
| PWA / offline | **Skip** | Out of scope. |

## A. Current position

### Behaviour

- New optional field "Current city" in Self, in Love for You (`a_`), and in Love for the loved person (`b_`), with the same autocomplete (hidden latitude, longitude, time zone). Hint: "Optional. Used for your local 'today' and for distances."
- Parameter names: prefix + `pos_city`, `pos_lat`, `pos_lon`, `pos_tz` (Self: `pos_city`; Love: `a_pos_city`, `b_pos_city`, ...). With a city text but no valid coordinates, the server resolves the text like it does for birth cities; if nothing is found, the current position is dropped with a note ("Current position ignored: we couldn't find ..."), never an error (it is optional).
- It is independent of the birth data (a loved person may have a current position without a birth date).
- Reading day: `on=` if valid; otherwise the calendar day, at the user's current position, of the real instant; otherwise the UTC date. The result shows one caption line when a position is used: "Today for you: YYYY-MM-DD (time zone X)". The same day is used for biorhythms, tarot, Today's sky and moon phase of both people in Love.
- Distances (pure `Earth\Distance`, `Earth\Geography`): shown as a small card "Distance and geography", clearly labelled "Approximate, in a straight line".
  - Self: birth place to current position (km and miles, time-zone difference).
  - Love: you to the loved person (both current positions known), plus for each person birth place to current position when both are known. A missing piece produces no row, and if there is nothing to show the card does not appear.
  - Time-zone difference is computed at 12:00 UTC of the reading day (so it reflects daylight-saving time on that day) and printed as "+5 h 30 min".
- Required? **No, in every place.** A visitor and an old link without it behave exactly as before. It is part of the form so it is discoverable.

### Code

- `src/Request.php`:
  - `const COORD_DECIMALS = 5;` all coordinates (`lat`, `lon`, birth and current) are rounded to it (unchanged from ADR 0004; the planned 2 decimals were not adopted).
  - `parsePerson()` also parses `pos_*` and returns `'now' => ?array{lat,lon,tz,city}` inside the person array; `parse()` adds the same `'now'` to `input`. No new error path.
  - `resolveToday(array $q, int $nowUnix, ?string $tz): string`: valid `on=` else `Zone::dateAt($nowUnix, $tz ?? 'UTC')`. `parseToday()` stays for old callers and tests.
- `src/Time/Zone.php`: `dateAt(int $unix, string $tz): string` (Y-m-d at that zone) and `offsetMinutes(int $unix, string $tz): int`. Pure.
- `public/index.php`: read `$nowUnix = time()` once (the only clock read); parse people first, then `$today = Request::resolveToday($q, $nowUnix, $userNow['tz'] ?? null)`. The "fixed day" note compares `$today` with the real day computed the same way. Hidden `on`/`noaudit` fields in the forms unchanged.
- `src/Earth/Distance.php` (new, pure): `km(float $lat1, float $lon1, float $lat2, float $lon2): float`, `miles(float $km): float`.
- `src/Earth/Geography.php` (new, pure): `between(?array $placeA, ?array $placeB, int $refUnix): ?array{km:int, miles:int, minutes:int, sameZone:bool}` (null when a place is missing); `forSelf(array $input, string $today)` and `forLove(array $a, array $b, string $today)` build the card model (`['pairs' => list<['label' => string, ...between()]>]` or null).
- `SelfReading::build` adds `'geo'`; `LoveReading::build` adds `'geo'`.
- `templates/partials/person-fields.php`: second `data-place` block with `data-kind="pos"` (birth block gets `data-kind="birth"`), no `required`. `templates/partials/geo.php` renders the card, used by both result templates.
- `public/assets/autocomplete.js`: unchanged (it already works per `[data-place]`).

### Audit

- `AuditRecord::FORMAT_VERSION = 3`; **no migration**: the YAML holds everything. New YAML keys (fixed names): Self `current_position` (`label`, `lat`, `lon`, `tz`, or null), `reading_day_basis` (`current_position` or `utc`), `distance` (`birth_to_now_km`, or null); Love `positions.a|b` (same shape), `reading_day_basis`, `distance` (`between_km`, `a_birth_to_now_km`, `b_birth_to_now_km`, each possibly null). The `magic_audit_person` columns keep describing the birth place only. `on_date` is the reading day (may differ from the UTC date of `created_at`).
- Rows of versions 1 and 2 stay readable; a reader branches on `format_version`.
- The Terms say that the current position is recorded (see Terms text).

## B. Browser memory

Purely client side: `public/assets/memory.js` (external file, deferred, listed after `autocomplete.js` in `home.php`). Nothing is sent anywhere except what the visitor's own form submit already sends. The file must contain no `fetch`, `XMLHttpRequest`, `sendBeacon`, `WebSocket`, `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write` or `eval` (a layering test greps for them).

### Markup (server-rendered, `hidden` until the script enables it)

- Forms carry `data-memory="self"` or `data-memory="love"`; each person fieldset carries `data-person="me"` (Self; Love You) or `data-person="loved"` (Love, loved person).
- `templates/partials/memory.php` (included in both forms): a bar `<div class="memory no-print" data-memory-bar hidden>` with the sentence "Your details are remembered in this browser only.", a button `data-memory-save` ("Remember these details", hidden unless needed) and a button `data-memory-forget` ("Forget my data").
- Love form only: `<div class="saved" data-saved hidden>` with a labelled `<select data-saved-select>` ("Saved people", first option "Choose...") and a button `data-saved-remove` ("Remove from this browser").
- The withdraw form in the Terms section gets `data-memory-forget-on-submit`.

### Storage

| Key | Content |
|---|---|
| `magic.me.v1` | the visitor's own data |
| `magic.loved.v1` | the list of saved loved people |

`magic.me.v1`:

```json
{"v":1,"t":1760000000000,"name":"Ann","date":"1990-07-15","time":"08:30",
 "city":"Rome, Lazio, Italy","lat":"41.9","lon":"12.5","tz":"Europe/Rome",
 "pos":{"city":"Milan, Lombardy, Italy","lat":"45.46","lon":"9.19","tz":"Europe/Rome"}}
```

`magic.loved.v1`: `{"v":1,"list":[{"id":"silvia","t":1760000000000,"name":"Silvia","date":"","time":"","city":"","lat":"","lon":"","tz":"","pos":{"city":"","lat":"","lon":"","tz":""}}]}`.

Rules:

- Every value is a string; `t` is a number (milliseconds); `v` must be exactly `1`. Version 2 and later keys are different keys, so a future layout never has to read this one. Unknown `v`: ignored (not deleted, not used).
- Limits on write and on read: name 40 characters, city 80, tz 64, lat/lon 12, date and time as `YYYY-MM-DD` / `hh:mm`; `me` at most 2 KB serialized; the loved list at most 20 entries and 16 KB serialized (oldest by `t` dropped first); `id` = lower-cased name (one entry per name, saving the same name replaces it). A stored value larger than 32 KB is treated as corrupt and ignored.
- On read every field is validated with the same patterns the server uses (date, time, coordinates, `tz` characters `[A-Za-z0-9_+/-]`); an invalid field becomes `""`; a non-object or a failed `JSON.parse` yields "nothing saved".
- Output to the page only through `element.value = ...` and `element.textContent = ...` (option labels, status text); `createElement` for options; never `innerHTML`.
- Every `localStorage` access (including reading the `window.localStorage` property, which throws in some privacy modes) is inside `try/catch`; availability is probed once with a write/remove test. If it fails, nothing is shown (the bar stays hidden) and the page behaves as before. A quota error on write is swallowed.
- `storage` events from other tabs are ignored.

### Behaviour

1. On load, per fieldset: if all its fields are empty (a fresh visit) it is **prefilled** from storage (`me` for `data-person="me"`; for Love, the loved person is not prefilled automatically, the visitor picks one from the select). In Self, the saved name is ignored (the form has no name field). Prefill sets visible and hidden fields (lat, lon, tz, and the current position).
2. If the fieldset arrived **filled by the server** (a result or a shared link), its values are compared with the stored entry. Equal: it is the visitor's own data and automatic saving stays on. Different or nothing stored: it may be somebody else's data, so nothing is saved automatically and the "Remember these details" button appears.
3. On submit, with automatic saving on, `me` is written from the You/Self fields, and (Love) the loved person is added to or updated in the list when a name is present. Saving never blocks or delays the submit.
4. Selecting a saved person fills the loved person's fields (clearing what the entry lacks); "Remove from this browser" deletes that entry from the list.
5. "Forget my data" removes both keys and every key starting with `magic.`, empties the select, and writes "Removed from this browser." into a `role="status"` element. Submitting the withdraw-acceptance form also forgets. The visible form fields are not cleared.

### Terms text (`templates/partials/terms.php`, owner approves)

Add: "If your browser allows it, this page remembers, in your browser only, the details you enter for yourself and a short list of the people you looked up (names, birth dates and times, cities and current cities), so you do not have to type them again. This stays on your device, is not sent to the site by this feature, and is sent only when you submit a form, like anything you type. Use 'Forget my data' to erase it; withdrawing your acceptance erases it too." And in the audit paragraph: "... and the place where you are now, if you enter it, ...".

## C. Derived features

### C1. Midheaven, whole-sign houses, wheel (Self)

- `Astro\Houses` (new, pure): `midheaven(float $jd, float $lon): float`; `midheavenFromRamc(float $ramcDeg, float $obliquityDeg): float` (public, for reference tests); `wholeSign(float $ascLon, float $bodyLon): int` (1-12).
- `Chart::compute` gains `'midheaven' => $pos` (`Zodiac::fromLongitude`); `Chart::full` gains `'houses' => ['sun' => int, ... , 'node' => int]` (house of each body, whole-sign from the Ascendant sign); `Chart::partial` gains `'midheaven'` with time and place. At latitudes where the Ascendant is flagged `polar`, houses and wheel carry the same "approximate" note.
- Content: `Bodies::INFO['midheaven']` (glyph "MC", title, tagline, meaning), `Signs::ROLES['midheaven']`, `Content\Houses::THEMES` (12 titles + one line each). Planet cards show "House N - <theme title>".
- `ChartWheel` (new, pure, `src/ChartWheel.php`): `layout(array $chart): array` returns for the sign ring, house numbers, axes and bodies their angles and SVG coordinates, with the Ascendant on the left, and glyphs of crowded bodies spread apart by at least 7 degrees on the drawing (the true position is unchanged and listed in the table next to it). `templates/partials/wheel.php` defines `wheel_svg()` in the style of `bio_svg`: `role="img"`, `<title>`, colours through CSS classes (`.wheel__sign--fire`, ...), every text through `e()`, no `style=` attribute.
- Self only; Love gets no wheel (Love has the synastry list).

### C2. Moon phase and "Today's sky"

- `Astro\MoonPhase` (new, pure): `at(float $jd): array{angle:float, illumination:float, phase:string}`; `phase` is one of `new`, `waxing-crescent`, `first-quarter`, `waxing-gibbous`, `full`, `waning-gibbous`, `last-quarter`, `waning-crescent`.
- `Sky\Today` (new, pure): `for(string $day, array $sunSigns): array` where the instant is 12:00 UTC of the reading day (so the same `on=` always gives the same card). Returns the phase, illumination in percent, the Moon sign of the day and, per Sun sign given, a relation level (reuse the existing sign-relation levels of `Love\Common`/`Traits::LEVELS`; expose the classifier as a public static if it is private) and the composed reading.
- Content `Content\Daily`: 12 strings (mood of the Moon in each sign), 5 strings (relation of the day's Moon sign to your Sun sign, one per level), 8 strings (invitation per phase), 8 phase names. The reading is the three sentences joined; no randomness.
- Self: block "Today's sky" (phase + illumination, Moon sign, reading) and, in the big-three area, a line "Born under a <phase>" (phase at the birth instant).
- Love: compact "Today's sky" (phase, Moon sign, one relation sentence per person).

### C3. Full synastry (Love)

- `Astro\Aspects` (new, pure): `between(float $lonA, float $lonB): ?array{type:string, orb:float}`; types `conjunction`, `sextile`, `square`, `trine`, `opposition`; orb limits are constants at the top (conjunction 8, opposition 8, trine 7, square 7, sextile 5) and pinned by a test.
- `Chart::longitudes(y, m, d, ?h, ?mi, ?lat, ?lon, ?tz): array<string,float>` returns what exists for a person: with time and place, Sun, Moon, Mercury-Pluto (1800-2100 only), Ascendant, Midheaven; with a date only, Sun and the planets at 12:00 UTC of the date (Moon, Ascendant and Midheaven omitted; the entry is marked `approx`); outside 1800-2100 planets are omitted.
- `Love\Synastry::between(array $lonsA, array $lonsB): array`: all body pairs found in both, keeping aspects where at least one body is Sun, Moon, Venus, Mars or the Ascendant; sorted by orb then fixed order; at most 12 shown; plus counts (harmonious: trine, sextile; tense: square, opposition; neutral: conjunction). View key `synastry`: `['rows' => ..., 'counts' => ..., 'approx' => bool, 'available' => bool]`; when the loved person has no date, `available` is false and a placeholder card invites to add the date.
- Content: `Content\Aspects`: 5 names, 5 meaning sentences, 3 tone labels (reuse `Bodies::INFO` taglines for the bodies). A row reads "<A>'s Venus trine <B>'s Mars (orb 2.1) - <meaning>".
- Template `templates/partials/synastry.php` in `love-result.php`, as a section with a semantic `<table>` (not a `<details>`), after "In common".

### C4. Minor arcana (78 cards)

- Numbering: 0-21 the major arcana (unchanged), 22-35 Wands, 36-49 Cups, 50-63 Swords, 64-77 Pentacles, each suit Ace-10, Page, Knight, Queen, King.
- `Content\TarotDeck`: `CARDS` stays as the 22 major cards; new `card(int $n): array` returns the same shape (`id`, `number`, `name`, `upright`, `reversed`) for 0-77, plus `arcana` (`major`|`minor`) and `suit`. Minor names and ids come from code (`Content\TarotMinor::SUITS`, `RANKS`).
- Position texts for minors are composed: `TarotMinor::RANK_TEXT[rank][position][up|rev]` (14 x 3 x 2 = 84 strings) followed by `TarotMinor::SUIT_TEXT[suit][position]` (4 x 3 = 12 strings), plus 28 essence lines for the card caption (14 ranks x up/rev), plus 18 name strings. About 142 strings in `Content\TarotMinor`; `TarotSpread::text()` delegates to it for cards 22-77 and still throws on a missing key.
- `Tarot\Reading::spread` draws among 78 cards. **Behaviour change:** live (no `t`) readings for the same people and day can differ from before; frozen links with `t=` are unaffected. The old golden is re-frozen.
- `ShareLink::parseTarot` accepts numbers 0-77 (`TarotDeck::COUNT - 1`); all other rules unchanged.
- Template: the art block uses the rank in the `tarot__num` slot and a suit class (`tarot__art--wands`, ...); reversed styling unchanged.

### C5. Geocoding cache cap

- `Geo\Geocoder`: `const MAX_FILES = 500; const PRUNE_TO = 400; const PRUNE_EVERY = 86400;` and `public static function prune(string $dir, int $now): int` (returns files removed). After a successful cache write `search()` calls it at most once a day (marker file `cache/.pruned`, its mtime is the throttle). It deletes expired `geo-*.json` first, then the oldest by mtime until at most `PRUNE_TO` remain. It touches only `geo-*.json`, never throws, and ignores errors.

## D. Compact share code

### URL contract

- Share links become `<origin><base>/?c=<code>`. `<code>` is base64url (alphabet `A-Z a-z 0-9 - _`, no padding) of a fixed binary payload.
- The readable long query (ADR 0004) is still accepted and unchanged in meaning (new optional keys: `pos_*`); `ShareLink::self/love/liveSelf/liveLove` stay (used by the forms' round trip tests and by the tests of `ShareCode`).
- `index.php` decodes `c` right after the consent check: `ShareCode::decode()` returns the equivalent long-query array (same keys as above, all strings, `noaudit` as `''`), which is merged over the request: `$q = $decoded + $q` (decoded values win; a stray `c` is removed). Everything downstream (`Request::*`, Terms gate, audit) is unchanged. An invalid or unknown-version code is ignored with the note "The short code in this link is not valid, so it was ignored." (nothing else changes; any readable parameters present still work). `Consent::safeQuery` already carries the `c` characters through the gate (no character it rejects appears in base64url).
- The Share section offers two codes built from the validated model (never from the raw query): **frozen** (flags: `on` and, in Love, `t`) and **live** (no `on`, no `t`). Both carry `noaudit`. The frozen/live distinction is kept: the live link opens with the recipient's "today" (a live link that carries a current position uses that zone's day).

### Code format (version 1)

The code carries a version, the mode, the `noaudit` flag, optional reading day and tarot spread, then the people. It is fixed-format and versioned; the byte-level layout is not documented here on purpose, the source (`src/Share/ShareCode.php`) and its golden tests are the reference.

Rules as shipped:

- Self needs a date, time and place; Love A the same; Love B may have a name only, a name and date, or everything.
- A code longer than 400 characters, with trailing data, or whose re-encoding of the decoded model does not give the same string (non-canonical) is invalid (strict canonical decoding).
- After decoding, everything is validated again with the same rules as `Request`; anything invalid makes `decode()` return null.
- The decoded model is written back as strings, so `Request` validates it again.
- `noaudit` as a plain parameter keeps working next to `c`.

### Time-zone table (backward compatibility)

- `src/Share/TimeZoneTable.php`: `const ZONES = [...]`, a fixed list of IANA identifiers, generated once at implementation from `DateTimeZone::listIdentifiers()`, sorted alphabetically, committed as source. Count must be below 1023.
- **Append-only.** Never reorder, delete or replace an entry: an index must mean the same zone for ever. New zones are appended at the end. A zone missing from the table still works through the literal escape (index 1023), so the table can lag behind the tz database without breaking anything. A zone in the table that the server's tz database no longer lists is rejected by `Zone::isValid` like any other invalid zone.
- The code version does not change when the table grows (indexes are stable by construction). A test pins the table: its current length, its first and last entries, and a checkpoint list `[count => sha1 of the first count entries]`; adding entries appends a checkpoint, and the test fails if an older checkpoint no longer matches.

### API (`src/Share/ShareCode.php`, pure)

```php
ShareCode::VERSION = 1; ShareCode::MAX_CHARS = 400;
ShareCode::encodeSelf(array $input, string $today, bool $frozen): string
ShareCode::encodeLove(array $a, array $b, ?array $tarotSpread, string $today, bool $frozen): string
ShareCode::decode(string $code): ?array   // long-query array, or null
ShareLink::codeQuery(string $code): string // 'c=' . $code
```

Coordinate rounding lives in `Request` (5 decimals), so `decode(encode(x))` gives an identical model. Labels are cut to 32 bytes in the code (the readable long link kept 48 encoded bytes); a label cut for the link is shown cut to the recipient, as it already was in 0004.

### Expected sizes

Shipped codes are a little longer than first estimated because places keep 5 decimals. A typical Love link stays well below the 520-byte QR limit; above it only the link is shown, as before.

### Share section (`templates/partials/share.php`, `styles.css`, `share.js`)

- Heading "Share this reading". Always visible: Copy link and Share buttons (as now), and the QR. Inside one native `<details class="share__more">` (collapsed by default; summary "Show the link"): the labelled read-only input with the frozen link, the live link, the privacy sentence. The print stylesheet opens the details (as for the synchrony details).
- QR: rendered with the same inline SVG (viewBox includes the 4-module quiet zone, unchanged). CSS: `.share__qr svg { width: clamp(112px, 38vw, 150px); height: auto; }` (about 150 px is 3.3 CSS pixels per module at version 7 including the quiet zone, versus 320 px before); print size about 3 cm. Quiet zone, black on white and `shape-rendering="crispEdges"` are unchanged.
- `share.js`: Copy reads the input value even while it is hidden; the "select the text" fallback first sets `details.open = true`.

## Files

**Add**

```
src/Earth/Distance.php          src/Earth/Geography.php
src/Astro/Houses.php            src/Astro/MoonPhase.php          src/Astro/Aspects.php
src/Sky/Today.php               src/Love/Synastry.php            src/ChartWheel.php
src/Share/ShareCode.php         src/Share/TimeZoneTable.php
src/Content/Daily.php           src/Content/Houses.php           src/Content/Aspects.php
src/Content/TarotMinor.php
public/assets/memory.js
templates/partials/memory.php   templates/partials/geo.php       templates/partials/wheel.php
templates/partials/synastry.php templates/partials/sky.php
tests/cases/position.php  tests/cases/sky.php  tests/cases/code.php  tests/cases/memory.php
docs/features.md  docs/changelog.md            (documenter)
```

**Change**

```
src/Request.php        COORD_DECIMALS=2, pos_* parsing ('now'), resolveToday(); parseTarot bound
src/Time/Zone.php      dateAt(), offsetMinutes()
src/Chart.php          midheaven, houses, longitudes()
src/SelfReading.php    geo, sky, moonAtBirth, houses
src/LoveReading.php    geo, sky, synastry
src/Tarot/Reading.php  78-card draw
src/Content/TarotDeck.php, TarotSpread.php, Bodies.php, Signs.php   card(), minor delegation, midheaven copy
src/Share/ShareLink.php  parseTarot bound (0-77), codeQuery()
src/Audit/AuditRecord.php  FORMAT_VERSION 3, new YAML keys
src/Geo/Geocoder.php   prune()
public/index.php       nowUnix, ShareCode::decode merge, resolveToday, share model with codes
public/assets/share.js, styles.css (wheel, geo, sky, synastry, memory, small QR, details)
templates/home.php     memory bar/saved select, memory.js, position fields
templates/partials/person-fields.php, share.php, terms.php
templates/self-result.php, love-result.php
tests/run.php          require the new case files; existing cases updated
```

## Behaviour impact (for `docs/features.md`)

- New optional "Current city" in Self and Love; reading day follows it; caption "Today for you ...".
- New result blocks: Midheaven and house of each body and a chart wheel (Self); "Today's sky" with moon phase and a daily reading (Self, compact in Love); "Born under a ..." (Self); "Distance and geography" (when positions are known); "Synastry" (Love).
- Tarot uses the full 78-card deck; live readings can differ from before for the same day.
- Share: short link, collapsed link box, small QR; old links keep working.
- Remembered details, saved people, "Forget my data".

## Privacy and sharing

- **Audit now stores** (YAML only): the current positions (label, coordinates, time zone), the day basis, distances, the moon phase of the day, the synastry counts and tightest aspects. Nothing new in the SQL columns. Not stored: browser memory (it never reaches the server except as the form data the visitor submits).
- **Browser storage:** described in B; named in the Terms; erased by "Forget my data" and by withdrawing acceptance; not read or written by any server code.
- **Share classification** (rule D10):

| Output | Derived or encoded | Where |
|---|---|---|
| Current positions (A and B) | input, encoded | `c` (`hasNow` blocks) or `pos_*` |
| Reading day | encoded | `on` inside `c` (frozen link); live link follows the position's zone |
| Tarot cards (0-77) and orientation | encoded | `t` inside `c` |
| Midheaven, houses, wheel, moon phase, Today's sky, daily reading, synastry, distances, time-zone difference | derived from the people, positions and `on` | recomputed |
| Browser memory | never shared | stays in the browser |
| `noaudit` | encoded | flag bit in `c`, or the plain parameter |

- The compact code is not encryption: anyone can decode it. The Share section keeps the warning that links contain names and birth details.
- Terms gate: `c` is carried through the gate by `Consent::safeQuery` like any query; no result and no audit without consent.

## Terms text

See B. The wording extends what is recorded (current position) and what the browser keeps, so the owner approves it, and see "Open decisions" about asking for acceptance again.

## Alternatives considered

- **Server-stored short codes in MySQL:** shortest links but stores personal data again; rejected earlier, rejected again (the request says no database).
- **Compressing the long query (deflate + base64):** shorter than the long form but unpredictable and needs the extension; a fixed layout is smaller for short inputs, testable and has no dependency.
- **A 0.01-degree grid for all coordinates:** proposed for shorter links; the owner chose to keep 5 decimals.
- **Hash of the time zone instead of a table:** collisions and no reverse; the append-only table with a literal escape is exact.
- **Browser time zone for "today":** differs between a sender and a recipient and cannot be rebuilt from a link; the position's zone can.
- **Saving shared-link data automatically in the browser:** would store another person's data without the visitor's action; replaced by the explicit "Remember these details" button.
- **Placidus houses:** see the triage.
- **Rendering the full 336 texts for minor arcana:** replaced by 142 composed strings; quality is a content decision for the owner.

## Risks

- (Dropped) the 0.01-degree grid risk does not apply: coordinates keep 5 decimals.
- A longer-lived compact code format: a wrong layout would break shared links silently. Mitigations: version nibble, canonical re-encoding check, golden codes in tests, pinned zone table.
- Browser memory on shared computers: mitigated by the visible "Forget my data", no save of foreign link data, and the Terms sentence.
- Re-drawing among 78 cards changes live readings for the same day (frozen links are safe).
- Moon phase and aspects depend on the accuracy of Sun and Moon, which are very accurate; synastry with a date-only person is approximate and labelled.
- Content volume (about 200 new strings, 142 of them tarot) is the largest cost; the owner reviews the copy.
- Time zone table drift: a new tz database may list zones that are not in the table; handled by the literal escape.
- More geocoding calls per Love request (up to four cities); all cached, and failures only drop an optional position with a note.

## Test plan

All in `php tests/run.php`; no database needed. Existing cases to update: `request` (`pos_*`), `share`, `audit` (version 3 goldens, new keys), `tarot` (78 cards, new goldens), `layering` (new directories), `planets`/`run.php` for the Midheaven addition.

**Reading day and position** (`tests/cases/position.php`)
- `Zone::dateAt` at 2026-10-09 23:30 UTC: Europe/Rome 2026-10-10; America/Los_Angeles 2026-10-09; Pacific/Auckland 2026-10-10. At 2026-10-09 00:30 UTC: America/Los_Angeles 2026-10-08. At 2026-10-09 10:30 UTC: Pacific/Kiritimati 2026-10-10. `offsetMinutes` for Asia/Kolkata is 330.
- `resolveToday`: a valid `on` wins over the zone; an invalid `on` falls back to the zone's day; no zone gives the UTC date.
- `Request::parse` and `parseLove` with `pos_*`: valid, geocoded from text, unknown city (note, person still valid), array values, over-long values, position without birth data for the loved person, lat/lon out of range.
- Distances (tolerance 1 percent unless noted): Rome (41.9028, 12.4964) to London (51.5074, -0.1278) about 1434 km; Paris to New York about 5837 km; (0,0) to (0,1) 111.2 km (+-0.1); (0,0) to (0,90) 10007.5 km (+-1); antipodal points 20015 km (+-2); identical points 0 and the "same place" label. Time-zone difference Rome to New York on 2026-10-09: 6 h; Rome to Kolkata on 2026-10-09: 3 h 30 min.
- Missing positions give no card; the card appears with only one valid pair.

**Houses, wheel, moon, aspects** (`tests/cases/sky.php`)
- `midheavenFromRamc` with obliquity 23.4393: RAMC 0, 90, 180, 270 give 0, 90, 180, 270 degrees; RAMC 45 gives 47.46 (+-0.01). For lat 0 and RAMC 0: Midheaven 0, Ascendant 90. The Midheaven of the existing 1990-07-15 08:30 Rome chart is cross-checked once against an independent ephemeris by the implementer, pinned to +-0.1 degree, and the source named in a comment.
- `wholeSign`: Ascendant in Gemini, Sun in Aries is house 11; same sign is house 1; Cancer house 2; Taurus house 12.
- `MoonPhase::at` at known events (UTC): new moon 2000-01-06 18:14 (illumination below 1 percent, phase `new`); full moon 2000-01-21 04:40 (above 99 percent, `full`); new moon 2024-04-08 18:21; first quarter 2024-04-15 19:13 (50 percent +-2, `first-quarter`); full moon 2024-04-23 23:49 (`full`). A day after the full moon the phase is `waning-gibbous`. Illumination is within 0 and 1 for 1000 sampled days.
- `Sky\Today` is deterministic; all 12 x 12 sign pairs have a non-empty reading; 12 + 5 + 8 strings present, none empty, all unique within their group.
- `Aspects::between`: 0 vs 120 is a trine with orb 0; 0 vs 124 trine orb 4; 0 vs 95 square orb 5; 0 vs 172 opposition orb 8; 359 vs 1 conjunction orb 2; 0 vs 100 none; the orb constants are pinned.
- `Synastry`: a chart against itself gives every shared body as a conjunction with orb 0; counts add up; the list is capped at 12 and sorted; a date-only partner has no Moon, Ascendant or Midheaven rows and `approx` true; no date gives `available` false; outside 1800-2100 only Sun, Moon and angles are used.
- `ChartWheel::layout`: the Ascendant is at the left; no two glyphs closer than the minimum separation, for 1000 random charts; deterministic; every coordinate finite. SVG partial: one `<svg`, no `style=`, no `<script`, texts escaped.
- Midheaven appears in Self output and in the audit YAML.

**Minor arcana** (`tests/cases/tarot.php`)
- `TarotDeck::card(n)` for 0-77: unique ids, names and numbers; 22 major + 56 minor; 4 suits x 14 ranks.
- `TarotSpread::text` for all 78 cards x 3 positions x 2 orientations is a non-empty string (40-300 characters); a missing key throws; `Reading::spread` returns three distinct cards for 500 seeds; the old `t=16u,5r,9u` still means The Tower, The Hierophant, The Hermit; `t=22u,40r,77u` is valid; `t=78u,...` and `t=100u,...` are invalid.

**Geocoding cache cap**
- `Geocoder::prune` in a temporary directory with 520 `geo-*.json` files of increasing mtimes plus a foreign file: at most 400 geo files remain, the newest are kept, the foreign file is untouched, expired files go first, a second call within a day does nothing through `search()`'s throttle.

**Compact code** (`tests/cases/code.php`)
- Golden codes pin the format (see `tests/cases/code.php`).
- Round trip `decode(encode(x))` equals the canonical long-query array and `Request` of both models are identical, for Self and Love models: complete, B with name only, B with name + date, B complete, with and without current positions, frozen and live, tarot with minor cards, names with `&`, `=`, `#`, `%`, quotes, accents and emoji, 40-character names of 4-byte characters, labels longer than 32 bytes (cut on a character boundary), a zone outside the table (literal escape), the first and last table zones, extreme coordinates (-90, 90, -180, 180, 0, -0.004), dates 1000-01-01 and 2100-12-31, 00:00 and 23:59. The built readings (`SelfReading::build`, `LoveReading::build`) of both models are identical.
- A frozen link opened with `c` shows the same biorhythm values, tarot and Today's sky as the page it came from.
- Invalid codes give `null` and never throw: empty, 401 characters, characters outside the alphabet, `=` padding, unknown version nibble, truncated at every byte, trailing non-zero bits, trailing whole bytes, bad month/day, date out of range, time over 1439, coordinates over range, tz index 1022 beyond the table, tz literal with a bad character, name length 0 and 161, invalid UTF-8 name, control character in a label, hasBirth without hasDate for B, duplicate tarot cards, card 78, Self without date, non-canonical re-encoding. A page request with a bad `c` returns 200 with the note and no exception; with a bad `c` plus a valid readable query the readable query is used.
- Backward compatibility: every long-form query that worked before (Self, Love, Love with `t`, with `on`, with `noaudit`, without `mode`) still parses to the same model, unchanged.
- Time-zone table: length below 1023, no duplicates, every entry accepted by `Zone::isValid` on the test machine (or listed as legacy), first and last entry and the checkpoint hashes pinned.
- Share section: `c` link length for the typical Love fixture is below 200 characters; the QR version for it is at most 7; `noaudit` flag makes `Request::noAudit` true after decoding (subprocess with a closed database port and an empty error log: no audit attempt); the gate keeps `c` in `next` and the result appears after accepting.
- Template: the share input and live link are inside `<details class="share__more">` without `open`; the QR is outside it; the CSS has the small QR rule; no inline style or script.

**Browser memory** (`tests/cases/memory.php`, static checks plus a manual list; there is no JS runner)
- `memory.js` contains none of: `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval(`, `fetch(`, `XMLHttpRequest`, `sendBeacon`, `WebSocket`, `new Function`; contains the keys `magic.me.v1` and `magic.loved.v1`, a `try`/`catch` around each storage access, and the limits (20 entries, 16384, 40, 80).
- Templates: forms have `data-memory`, bar and select start `hidden`, no inline script or handler attributes; the Terms partial contains the browser-storage sentence.
- Manual (browser): fresh visit prefills nothing; submit Self then reload Love shows the same You data; save a loved person, pick and remove; a shared link does not overwrite stored data until "Remember these details" is pressed; "Forget my data" and withdrawing acceptance empty both keys (DevTools); private window or blocked storage keeps the page normal with no bar; corrupt JSON in the key is ignored; an entry with `<script>` as name is shown as text; 25 saved people keep 20.

**Audit** (`tests/cases/audit.php`)
- Goldens for format 3: Self with and without a current position, Love with positions and synastry counts; no new SQL columns; the YAML emitter still quotes labels; a record with positions stays below the YAML limit.

**Layering and templates**
- Add `src/Earth`, `src/Sky`, `src/ChartWheel.php`, `src/Share/ShareCode.php`, `src/Share/TimeZoneTable.php` and the new `Astro`/`Love`/`Content` files to the pure scan (no `$_GET`, `$_SERVER`, files, curl, echo, clock). New templates: every output through `e()`, no inline script or style.

**Manual**: scan the new QR with two phones for the Self, typical Love and largest Love links (record versions); keyboard walk through the collapsed share box and the saved-people select; print preview to PDF in two browsers (wheel, synastry, details open); owner reviews the minor arcana and daily texts.

## Implementation phases (to keep each review small)

1. Position, `Request` quantisation, reading day, geography, audit v3.
2. Compact code, time-zone table, share section.
3. Browser memory and Terms text.
4. Midheaven, houses, wheel, moon phase, Today's sky.
5. Synastry, minor arcana, cache cap.

## What `docs/features.md` must contain (documenter)

A player-facing description, no mechanics: the two modes; every result block per mode with what the visitor sees and which inputs it needs (a table: block, mode, needs); optional fields (loved person's partial data, current city) and what changes when they are given; the reading day rule (position's day, `on=`, UTC fallback); sharing (frozen vs live, collapsed link, QR, old links still work, `noaudit`); remembered details and "Forget my data"; the Terms gate and what is recorded; entertainment disclaimers; known limits (planets 1800-2100, approximate Ascendant at polar latitudes, date-only partners approximate). It never says how anything is calculated.

## What `docs/changelog.md` must contain (documenter)

Newest first, one section per release with date, "Added / Changed / Fixed / Removed / Privacy / Migration" lines, each linking its ADR. Backfill from `git log --date=short` and ADRs 0001-0005: v0.1 PHP rewrite (ADR 0001); v0.3 two modes (ADR 0002); the audit trail and Terms consent (ADR 0003, commit "Add MySQL audit trail ..."); the rename to Magic, Docker run and removal of algorithm references (commits "Rename to Magic ...", "Remove algorithm references ..."); v0.6 sharing, tarot spread, `noaudit`, gate fixes (ADR 0004, `format_version` 2); the workflow change; this ADR as v0.7 (current position, compact links, browser memory, Midheaven/houses/wheel, moon phase and Today's sky, synastry, 78-card tarot, cache cap; audit `format_version` 3; Terms text change). The documenter rule: every change to `src/`, `public/`, `templates/` or `migrations/` appends an entry under "Unreleased" in the same commit. No host, domain or credentials appear in either file.

## Proposed CLAUDE.md addition (owner)

> **Browser storage** only through `public/assets/memory.js`, with documented keys, validation on read, no network calls and no HTML injection, and its content named in the Terms.

## Open decisions for the owner

1. **Ask for acceptance again?** The Terms now record the current position and describe browser memory. Answered: yes, `Consent::VALUE` is `2`.
2. **0.01 degree grid** for all coordinates. Answered: no, keep 5 decimals.
3. **Automatic save** into the browser on submit (shipped) or an explicit "Remember" button only.
4. **Minor arcana copy**: 142 composed strings (recommended) or the full 336 individual texts.
5. **Live tarot readings change** for the same people and day after the deck grows to 78 cards; confirm this is acceptable.

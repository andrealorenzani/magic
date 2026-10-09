# 0006 — Menu, hidden-details sharing and import, help popovers, Self name, QR copy, Terms and wheel fixes

- Status: accepted (phases A+B shipped in v0.8; deviations listed in "As shipped" below, which wins over the original text)
- Date: 2026-10-09

**ARCHITECTURE CHANGE: yes** (new endpoint `public/hidden.php`, new URL parameters `h` and `import`, new share-code versions, new action in `consent.php`, new JS files, a rule on audit skipping, a Terms change).

## As shipped (amendments, these override the text below)

1. **Opening a hidden link IS audited (owner decision).** The request is recorded as a Love result with both people's full data (the receiver as `user`, the shared person as `loved`), the YAML gets the marker `loved_person_source: hidden_link`, and `AuditRecord::FORMAT_VERSION_HIDDEN` (4) is used as `format_version`. The receiver's `noaudit` still skips the write. Everything the original text says about "never audited" (Existing decisions row on ADR 0003, Summary 4, Privacy "Audited", Alternatives, Risks, Open decision 1, tests) is superseded. The result page still has no share section and no share link, and still does not display the shared person's details. The Terms say the shared person's details are recorded when the link is opened.
2. **Terms cookie value is `3`**; everyone accepts again once (open decision 2 resolved as recommended).
3. **Coordinates keep 5 decimals** (unchanged).
4. **Self name and share codes:** the Self code uses version 2 only when a name is present, otherwise version 1 (unchanged bytes). The hidden code is version 3.
5. **`import` works without JavaScript:** a pasted link or code in the Love form is turned into `h` by `index.php`. The import fieldset has its own second submit button, so pasting a link can be submitted from inside the fieldset.
6. **"Share my Self Discovery hidden data"** goes grey (`is-empty`, `aria-disabled`) unless the stored own entry is complete (date, time and a city with coordinates and zone); clicking it then leads to Self discovery.
7. **`hidden.php`** answers with a relative link and no QR when the origin cannot be determined from the request.
8. **Cleaning browser data needs JavaScript** (the confirmation panel and the storage erase live in `memory.js`); the Terms withdraw button remains the no-JS way to remove the cookie.
9. `Request::hiddenCode` returns `code`, `person` and `invalid`. `LoveReading` person B keys `label`, `anonymous` produce `view['hidden']`.

## Context

Round 4 of the owner's requests: a "name" field in Self; a much smaller and clearer chart wheel; a small explanatory popup for every field and result item; clickable and collapsible QR blocks with "Link copied" feedback; Love form cleanup (required marks, no hint) plus a way to import another person's **hidden** data (QR scan or pasted link) without showing it; a menu above the mode chooser with "Clean browser data" and "Share my Self Discovery hidden data"; and a root-caused bug: the Terms section cannot be expanded because the footer (`position: relative`, `public/assets/styles.css:69`) paints over `#terms`, whose `.notice` has `margin: -24px auto 40px` (line 70), so `elementFromPoint` on the summary returns the FOOTER.

The hidden data is real personal data of a third person (name, birth date and time, birth place). "Hidden" can only mean hidden from the receiver's screen: the link or QR necessarily contains the data.

## Existing decisions

| Decision | Effect |
|---|---|
| D1 (no Composer, no build, shared hosting) | Holds. Plain PHP and plain JS files only. |
| D2 / ADR 0003 (MySQL only for the audit) | Holds. No migration, no new table or column. The new endpoint stores and logs nothing. |
| D3, D4, D5, D6 | Unchanged. |
| D7 (pure core) | Holds. New pure code lives in `Share\ShareCode`, `Request`, `LoveReading`, `Content\Help`; I/O only in `public/`. |
| D8 (progressive enhancement) | Holds. Pasting a link and the Self name work without JavaScript; menu, help popovers, scan and copy are enhancements. The one exception is "Share my hidden data", which needs JS (the data lives in browser storage); without JS its link goes to Self discovery. |
| D10 (shareable state in the URL) | Holds. The imported person travels in the result URL as `h`. Deliberately, the **share links of such a result are not offered** (see Privacy). |
| D12 (compact code) | Extended with two new code versions (below); version 1 codes stay byte-identical and valid. |
| D13 (browser storage only through `memory.js`) | Holds. The menu actions that touch storage live in `memory.js`; network and clipboard work lives in `share.js`, which never touches storage (checked by a test). |
| ADR 0003 audit rule "every Self/Love result is audited unless `noaudit`" | **Extended, not reversed:** a request that imports hidden details is never audited (same effect as `noaudit`). |
| ADR 0005 "browser memory is not sent to the site by that feature" | Still true for memory itself. "Share my hidden data" is a separate, explicit user action that posts the data once to `hidden.php`; the Terms say so. |
| Terms gate, `noaudit`, 5-decimal coordinates | Unchanged. |

## Summary

Assumptions:

1. The hidden link is `<origin>/?h=<code>`. `h` is a new compact code (version 3) carrying one person: name (may be empty), date, time, birth place, optional current position. It never contains a reading day, tarot or `noaudit`.
2. Opening `?h=` goes through the Terms gate (the query is carried by `Consent::safeQuery`; base64url characters are safe), then lands in **Love mode** with the shared person loaded but not shown. The receiver enters their own data (or it is prefilled from browser memory) and submits; the result shows match results only.
3. The sender's link/QR is created by a **server round trip** (`public/hidden.php`, POST), not by a JavaScript encoder (reasoning in D). The server stores nothing, logs nothing, writes no audit record.
4. A request that imports hidden details is **not audited at all** (neither the receiver nor the shared person). The result page has **no share section** and no live/frozen link.
5. In an imported result the shared person is labelled "Your match" everywhere; their name is used only for the name-affinity percentage (if a name was shared). Their birth data is used only for results.
6. The Terms gain a paragraph and `Consent::VALUE` becomes `3` so everyone re-accepts (open decision 2).

## Decision

### A. Self name (optional)

- Self form gets a "Name (optional)" field, 40 characters max. If given, it must pass the same name rules as Love; empty is allowed. It is remembered in browser memory like the other fields (the existing `magic.me.v1` entry already has `name`; the special case in `memory.js` that ignored it for Self is removed).
- Carried by links: `ShareLink::self/liveSelf` add `name=` when non-empty; the compact code gets **version 2** (= version 1 plus a Self name). The encoder emits version 1 when there is no name, so existing codes and goldens do not change; the decoder accepts 1 and 2. Love codes are unchanged.
- Audit: `AuditRecord::fromSelf` already stores `$input['name']` when present; `Request::parse` now puts it in `input`. No format change, no migration. The Self result heading shows the name when given ("Self discovery for Ann").

### B. Hidden-details code and import

**Code.** `ShareCode` gets `encodeHidden(array $person): string` and `decodeHidden(string $code): ?array` (version 3, one person, strict canonical decoding, 400 characters max, name length 0..160 bytes, same validation as `Request`). `decode()` (the `c=` path) rejects version 3 and `decodeHidden()` rejects versions 1 and 2, so the two parameters cannot be confused. `ShareCode::extractHidden(string $text): ?string` pulls a code out of a pasted full link (the `h=` value in its query) or a bare code, tolerating surrounding whitespace; anything else gives null. The byte layout is not documented here; the source and golden tests are the reference.

**Parameters (new URL contract).**

| Parameter | Meaning |
|---|---|
| `h=<code>` | The hidden person (version 3 code). Wins over any `b_*` keys in the same query (those are dropped). |
| `import=<text>` | A pasted link or code from the Love form (works without JavaScript). `public/index.php` runs `extractHidden` on it, turns it into `h`, and forgets `import`. |
| `h` without `mode` | Means `mode=love`. |

An invalid `h`/`import` is ignored with the note "The hidden details in this link are not valid, so they were ignored." and the normal Love form is shown.

**Form behaviour.** When `h` is valid, `public/index.php` merges the decoded person into the query as `b_*` keys for parsing only, and sets `$hidden = true`. `templates/home.php` then, in the Love form: does not render the loved-person fieldset or any of its values (no `b_name`, `b_date`, `b_time`, `b_city`, `b_lat`... inputs; `$love['b']` is left blank); renders `<input type="hidden" name="h" value="<canonical code>">` and a block "Details shared with you are loaded and hidden. You will only see the match results." with a link "Remove them" (`./?mode=love`). The code is in the page source and in the URL; that is not encryption (see Privacy).

**Import control (end of the Love field list).** A `<fieldset class="import">` after the loved-person fieldset, legend "Import hidden details (optional)": a help button; a text input `name="import"` ("Paste a link or code"); a "Scan a QR code" button that `assets/import.js` un-hides only when `BarcodeDetector` and `navigator.mediaDevices.getUserMedia` exist; a `<video>` (muted, playsinline) shown only while scanning; a `role="status"` line. Scanning fills the `import` input with the decoded text (no navigation; the visitor still presses the submit button) and stops all tracks on success, on Esc, on a "Stop scanning" button and on page hide. Camera or detector errors give a plain sentence and leave pasting available. Text: "Someone shared their hidden details with you? Scan their QR code or paste their link. Their name, birth date, time and city are not shown on your screen; you only see the match results. The link itself contains these details, so only use links from someone you trust."

**Result page for an imported person.**

- `LoveReading::build` reads two optional keys of person B: `label` (display name, "Your match") and `anonymous` (no name shared). `view['names']['b']` is the label; the real name never reaches a template. Name affinity uses the real name when shared; when `anonymous`, the name block shows the existing "nothing to compare in these names" state. Any name-affinity supporting detail that would reveal letters of the name is not rendered when `view['hidden']` is true. `view['hidden']` is true for imported persons.
- Not rendered: the loved person's name, birth date, time, birth city, coordinates, time zone and current city (form, headings, captions, alt text, `<title>`, chips, tables, distance card labels: where `partials/geo.php` prints B's place, it prints "your match" instead; the implementer checks).
- Match results (signs, aspects, scores, biorhythm curves, tarot, distances) are shown. Honest limit: results derive from the data, so a determined person can infer some of it; the UI claims only "not shown".
- Share section: **not rendered**. A note replaces it: "This reading includes details shared privately with you, so it has no share link." No frozen link, live link, QR or copy button. The "fixed to a past day" note shows no live link either.
- `public/index.php` computes `$noAudit = $noAudit || $hidden` after decoding, so the audit is skipped.
- `memory.js`: no loved-person fieldset exists in this state and the saved-people select is not rendered, so nothing about the shared person can be saved; the receiver's own entry is saved as before.

**The link the receiver sees:** after submitting, the address bar contains `h=<code>` next to the receiver's own `a_*` data. That is unavoidable with GET and stateless pages (D8, D10); it is kept in history and can appear in server access logs. The Terms and the import fieldset say so. The visible URL is not rewritten.

### C. Menu (above the Self/Love chooser)

A `<details class="menu no-print">` ("Menu") in `templates/home.php` before `.chooser`, containing:

1. **Clean browser data** (button hidden until `memory.js` runs). Click opens an in-page panel (a labelled group, not `alert`/`confirm`): "This forgets the details remembered in this browser (yours and your saved people) and your acceptance of the Terms. The Terms popup will appear again." with "Yes, clean" and "Cancel". "Yes, clean" runs `forgetAll()` (every `magic.*` key, plus the session flag) and then submits a POST form to `consent.php` with `action=clean`. Keyboard: focus moves to the panel heading on open and back to the trigger on cancel; Esc cancels.
2. **Share my Self Discovery hidden data**: an `<a href="?mode=self" data-menu-share-hidden>`. `memory.js` marks it `aria-disabled="true"` with class `is-empty` (grey) when the stored own entry has no date or no city; clicking then follows the link to Self discovery. When the entry is complete, `memory.js` prevents the default action and dispatches a `magic:share-hidden` event on `document` with the validated entry as `detail`. `share.js` listens, posts it (see D), shows the panel (QR in an open `<details>`, read-only link input, "Copy link" button, status line) and copies the link.

`consent.php` gets a third action `clean`: the same cookie clearing as `withdraw`, redirect `303` to `./?cleaned=1`. `index.php` shows, in the gate, "Your browser data was cleaned and your acceptance withdrawn." and puts `data-forget-memory` on `#page` for both `?withdrawn=1` and `?cleaned=1` (so storage is cleared even if the first step could not run). The Terms cookie is `HttpOnly`, so it can only be cleared by this server round trip. The memory script's early return when storage is unavailable moves after the menu handlers so the cookie clean still works.

### D. How the hidden link is created (recommendation and reasoning)

**Chosen: a small server endpoint, `public/hidden.php`, POST only.** The browser posts the validated memory entry (`name`, `date`, `time`, `city`, `lat`, `lon`, `tz`, `pos_city`, `pos_lat`, `pos_lon`, `pos_tz`) as `application/x-www-form-urlencoded`; the server answers JSON `{"ok":true,"link":"...","qr":{"size":N,"path":"..."}|null,"tooLong":bool}` or `{"ok":false,"error":"terms|method|data|server"}`. Rules:

- Requires the consent cookie (else 403) so the Terms gate is respected; GET gives 405; always `Cache-Control: private, no-store`, `Vary: Cookie`, `X-Content-Type-Options: nosniff`; body size capped.
- Validates with the existing `Request::parsePerson` rules (date, time and a birth place with coordinates; no geocoding call here: if coordinates or zone are missing the answer is `data` and the UI says "Open Self discovery and submit once, then try again").
- Stores nothing, writes **no audit record**, logs nothing, does not echo the input.
- The link uses the same origin/base logic as `index.php` (`Http::origin`, `Http::basePath`); the QR uses `Share\Qr` (pure). The QR reaches JS as a module path and size; JS builds the SVG with `createElementNS`/`setAttribute` (no `innerHTML`), mirroring `qr_svg()`.

Why not a JavaScript encoder: it would duplicate the code layout and the append-only time-zone table in a second language (the canonical-form check would make any drift invalid) and a JS QR generator is a large file. Why not a form POST returning a page: the clipboard write would run without a user gesture. Why POST and not GET: the data would otherwise enter the sender's URL and logs. Trade-off: the sender's data crosses to the server once; the Terms say it is not stored, logged or audited.

**Clipboard (one function in `share.js`, used by every QR/copy action).** In order: `navigator.clipboard.write` with a `ClipboardItem` holding a promise (keeps the user gesture across the async fetch), `navigator.clipboard.writeText`, and, where the Clipboard API is missing or refused (for example a non-secure page), selecting the visible read-only input and `document.execCommand("copy")`. If every step fails, the input's `<details>` is opened, the text is selected and the status says "Press Ctrl+C (or long-press) to copy." Success shows "Link copied" in a `role="status"` element for a few seconds.

### E. QR blocks: click to copy and collapsible

- Every QR block (Self and Love share, the hidden-menu panel) is wrapped in `<details class="share__qrbox" open><summary>QR code</summary>...</details>`; print keeps it open (as for `.share__more`).
- The QR wrapper has `data-qr-copy="<id of the link input>"`. `share.js` gives it `role="button"`, `tabindex="0"`, an `aria-label` "Copy link (QR code)", and handles click, Enter and Space with the function above and a status line. Without JS the QR is just a picture.

### F. Love form cleanup and required marks

- Remove the hint "Only the name is required. Add the birth date ... Ascendant." from the loved-person fieldset (the `hint` key in `home.php`).
- Required marks: `<span class="req" aria-hidden="true">*</span>` in each required label and a visible `<p class="hint">* required</p>` once per form. Required: Self date, time, birth city; Love You: name, date, time, birth city; loved person: name only. The native `required` attribute remains the accessible signal. `person-fields.php` gets a `nameRequired` flag because Self now has an optional name.

### G. Help popovers (what each field and result means)

- `templates/partials/help.php` defines `help_button(string $key)` (guarded like `qr_svg`) rendering `<span class="help"><button type="button" class="help__btn" hidden aria-expanded="false" aria-controls="help-N" aria-label="What is this: TITLE">?</button><span id="help-N" class="help__pop" hidden data-help="KEY">TEXT</span></span>`, everything through `e()`. `N` is a per-page counter. An unknown key throws in tests and renders nothing in production.
- `public/assets/help.js` (no storage, no network, no `innerHTML`): un-hides the buttons; click/Enter/Space toggles the popover and `aria-expanded`; opening one closes the others; Esc closes and returns focus to the button; a click or tap outside closes. The popover is in normal flow (`display: block` under its label or heading), never absolutely positioned, so it cannot overflow a 360 px screen and needs no inline style. Hit area at least 44 px via padding; printing hides all of it. Without JS the buttons stay hidden (the page is as it was).
- Help buttons sit **outside** `<label>` elements (beside the label text in a wrapper) so a tap never focuses the input.
- Content file `src/Content/Help.php`: `Help::TEXT` is `array<string, array{title:string, text:string}>`; `text` is 1-2 plain sentences, 20-240 characters, no method talk. About **50 keys, ~8 KB of copy**; the owner reviews the wording. Dynamic keys are listed in `Help::DYNAMIC` and expanded by the test.

| Group | Keys |
|---|---|
| Fields | `field.name`, `field.date`, `field.time`, `field.city`, `field.pos_city`, `field.import` |
| Menu and share | `menu.clean`, `menu.share_hidden`, `share.link`, `share.qr`, `share.live` |
| Self | `self.sun`, `self.ascendant`, `self.moon`, `self.midheaven`, `self.houses`, `self.wheel`, `self.node`, `self.retrograde`, `self.planet.mercury`, `.venus`, `.mars`, `.jupiter`, `.saturn`, `.uranus`, `.neptune`, `.pluto`, `self.affinity.signs`, `self.affinity.soulmate`, `self.born_under`, `self.sky`, `self.geo` |
| Biorhythms (both modes) | `bio.physical`, `bio.emotional`, `bio.intellectual` |
| Love | `love.name_affinity`, `love.sync`, `love.sync_overall`, `love.common`, `love.common_level`, `love.synastry`, `love.aspect`, `love.tarot.past`, `love.tarot.present`, `love.tarot.future`, `love.tarot_reversed`, `love.match_hidden` |

### H. Terms and Conditions cannot be expanded (root cause fix)

- `public/assets/styles.css`: remove the negative top margin of `.notice` (`margin: 0 auto 40px`), reduce the footer's bottom padding to compensate, and give `.notice` `position: relative; z-index: 1` so nothing can paint over it again. Both measures, not one.
- Gate popup readability: the box becomes `display: flex; flex-direction: column; max-height: calc(100dvh - 32px)`; the Terms text sits in `<div class="gate__terms" tabindex="0" role="region" aria-label="Terms and Conditions text">` with `overflow-y: auto; flex: 1 1 auto`; the actions bar is no longer `position: sticky` and sits below the scroll region, so it can never overlap the text at 360 px. The end-of-page Terms stay a native `<details>` (expandable).
- Regression test (static CSS) plus a manual real-browser check (below). No browser automation is added to the repo (no dependencies allowed).

### I. Chart wheel

- Render about 240 px on desktop and `min(100%, 260px)` on small screens (was 420 px); `.chartwheel__layout` first column `minmax(0, 260px)`. The figure is wrapped in an open `<details class="chartwheel__more">` ("Chart wheel") so it can be collapsed; open in print.
- Disc fill lighter than the panel (about `#2b2768` against the panel `#17153a`), ring and divider strokes bright (new token about `--wheel-line: #8f88e0`, width 1.5), house numbers about `#d9d5fa` and heavier; sign glyphs, body glyphs, house numbers and axis labels enlarged **in SVG units** so they render at least 12 px at 260 px (about 24 for signs, 22 for bodies, 18 for house numbers and axis labels, because the 416-unit viewBox is scaled down). The mobile media-query font overrides go away.
- `ChartWheel::MIN_SEPARATION` (degrees) is raised so crowded glyphs still do not overlap at the bigger glyph size; the exact value follows from the test below. Print keeps black on white at about 9 cm.
- `wheel_svg()` markup keeps `role="img"`, `<title>` and classes only.

## Design: files

### Add

```
src/Content/Help.php                 TEXT (about 50 keys), DYNAMIC
templates/partials/help.php          help_button($key)
templates/partials/menu.php          the Menu (clean confirmation panel, hidden-share link, hidden-share panel)
templates/partials/import.php        the Import fieldset (paste, scan, status) for the Love form
templates/partials/hidden-person.php the "details loaded and hidden" block (replaces the loved fieldset)
public/hidden.php                    POST endpoint for the hidden link (no storage, no audit)
public/assets/help.js                popovers
public/assets/import.js              QR scan with BarcodeDetector
tests/cases/hidden.php               hidden code, import flow, endpoint, no-leak
tests/cases/ui.php                   menu, required marks, help coverage and content, CSS regressions, JS static checks
```

### Change (function level)

- `src/Share/ShareCode.php`: `encodeSelf` writes version 2 only when `$input['name']` is non-empty; `read()` accepts versions 1 and 2 and the Self name; new `encodeHidden`, `decodeHidden`, `extractHidden`; `decode()` rejects version 3.
- `src/Share/ShareLink.php`: `self`, `liveSelf` add `name`.
- `src/Request.php`: `parse()` returns `input.name` (Love name rules; empty allowed); `mode()` treats `h`/`import` without a mode as `love`; new `Request::hiddenCode(array $q): array{code:?string, invalid:bool}` resolving `h`/`import` (pure, uses `ShareCode`).
- `src/LoveReading.php`: `build` honours person keys `label`/`anonymous`; adds `view['hidden']`.
- `src/ChartWheel.php`: `MIN_SEPARATION` and glyph-size-dependent radii.
- `src/Audit/AuditRecord.php`: comment only (Self name now filled); no layout change.
- `src/Consent.php`: `VALUE = '3'` (open decision 2); constant `ACTIONS = ['accept','withdraw','clean']`.
- `public/consent.php`: `clean` action (clears cookie, redirects to `./?cleaned=1`).
- `public/index.php`: decode `h`/`import` after `c`, merge into the query, drop client `b_*` when hidden, set `$hidden`, `$noAudit |= $hidden`, `$share = null` and no live link in `$fixedDay` when hidden, `cleaned` flag for the gate message and `data-forget-memory`, `$love['b']` left blank when hidden.
- `templates/home.php`: menu partial above the chooser; Self name field; Love: no hint, required marks and `* required`, loved fieldset or hidden-person block, import fieldset; gate message for `cleaned`; `gate__terms` wrapper; script tags `help.js`, `import.js`.
- `templates/partials/person-fields.php`: `nameRequired`, `name` for Self, `req` marks, help buttons beside labels.
- `templates/partials/share.php`: QR in `<details class="share__qrbox" open>` with `data-qr-copy` and a status element; `love-result.php` renders the hidden-mode note where the Share section would be.
- `templates/self-result.php`, `templates/love-result.php`, `partials/sky.php`, `partials/geo.php`, `partials/synastry.php`, `partials/wheel.php`: help buttons per the key table; Self heading with name; masked labels and hidden mode in Love; wheel inside `<details>`.
- `templates/partials/terms.php`: new paragraph (see Privacy).
- `public/assets/memory.js`: menu clean (panel, `forgetAll`, form submit), share-state marking and the `magic:share-hidden` event, Self-name special case removed, early return moved after the menu handlers.
- `public/assets/share.js`: single clipboard function, QR click/keyboard copy, status text, hidden-link flow (fetch to `hidden.php`, SVG built with DOM methods).
- `public/assets/styles.css`: `.notice`, `.gate*`, `.wheel*`, `.chartwheel__layout`, `.menu`, `.help*`, `.req`, `.share__qrbox`, QR focus style, `.import`, `.is-empty`.
- `tests/run.php`: require the new case files; update `request`, `share`, `code`, `memory`, `layering`, `sky` cases (including the existing test harness gaining POST support for `hidden.php` and `consent.php`).
- `docs/` (documenter, after implementation): see "What the docs must say".

## Data shapes

```php
// Person for the hidden code (long-query keys, unprefixed), all strings:
['name' => '' | 1..40 chars, 'date' => 'YYYY-MM-DD', 'time' => 'HH:MM', 'city' => string (<=32 bytes in a code),
 'lat' => '12.34567', 'lon' => '...', 'tz' => IANA, 'pos_city?', 'pos_lat?', 'pos_lon?', 'pos_tz?']
ShareCode::encodeHidden(array $person): string           // version 3
ShareCode::decodeHidden(string $code): ?array            // the same keys or null
ShareCode::extractHidden(string $text): ?string          // code from a pasted link or a bare code
Request::hiddenCode(array $q): array{code: ?string, invalid: bool}
// Love person B for an import: parsePerson result + ['label' => 'Your match', 'anonymous' => bool]
// LoveReading view: 'names' => ['a' => ..., 'b' => 'Your match'], 'hidden' => bool
// hidden.php: {"ok":true,"link":string,"qr":{"size":int,"path":string}|null,"tooLong":bool} | {"ok":false,"error":"terms|method|data|server"}
// magic:share-hidden detail: {name,date,time,city,lat,lon,tz,pos:{city,lat,lon,tz}} (validated strings)
```

DOM hooks added: `data-menu-clean`, `data-menu-clean-panel`, `data-menu-clean-yes`, `data-menu-clean-no`, `data-menu-share-hidden`, `data-hidden-panel`, `data-hidden-qr`, `data-hidden-status`, `data-qr-copy`, `data-copy-status`, `data-import-scan`, `data-import-video`, `data-import-status`, `data-help` (on the popover), `.help__btn`.

## Behaviour impact (for `docs/features.md`)

- New "Menu" at the top: Clean browser data (with in-page confirmation) and Share my Self Discovery hidden data (grey, and leads to Self discovery, when nothing is stored).
- Self: optional Name; it appears in the heading, in links and in the stored record.
- Love: no hint under the loved person; required fields marked `*` with the legend; "Import hidden details" (scan or paste); a received hidden person is not shown and the result shows only match results labelled "Your match", with no share section and nothing recorded.
- Every field and result item has a "?" explanation.
- QR codes can be clicked (or activated by keyboard) to copy the link, show "Link copied", and collapse.
- The chart wheel is smaller, clearer and collapsible.
- The Terms section expands; the Terms popup text scrolls and the Accept button never covers it.
- Known limits: hidden means "not shown on the receiver's screen", not secret; the link and the receiver's address bar contain the data; the scan needs a browser with `BarcodeDetector`, pasting always works.
- Terms version 3: everyone accepts again once.

## What the docs must say (documenter)

- `docs/changelog.md`, under "Unreleased" (mandatory, in the same commit as the code), with the ADR link: **Added** Menu (clean browser data, share hidden data), hidden-details link/QR (`h`), import by scan or paste (`import`), `public/hidden.php`, optional Self name (code version 2), help popovers (`help.js`, `Content\Help`), click-to-copy and collapsible QR blocks, required marks and `* required` legend, `consent.php` action `clean`; **Changed** wheel smaller/brighter/collapsible, Love hint removed, gate text scrolls, `ChartWheel::MIN_SEPARATION`, `Consent::VALUE` 3, copy fallback fix; **Fixed** the Terms section could not be expanded (footer painted over it), the sticky accept bar overlapped the Terms text; **Privacy** hidden imports are never audited and have no share link, `hidden.php` stores and logs nothing, the link contains the data; **Migration** visitors re-accept the Terms, no database migration.
- `docs/features.md`: the Behaviour impact list above, a "Menu" section, a "Hidden details" section (what the sender is told, what the receiver sees, honest limits), the results table row for help, the Terms version.
- `docs/architecture.md`: decision D14 (hidden details, `h`, no audit, no share), `hidden.php` in §3, §5 privacy (new endpoint, `h` in URL and logs, Terms), §7 row "Another hidden or private output", ADR list, testing paragraph.
- `docs/code.md`: repository map, data shapes, DOM hooks, the manual `elementFromPoint` recipe, "Where do I..." rows (help copy: `Content\Help`; hidden code: `ShareCode`).
- `docs/roadmap.md`: mark the Self name item done.
- No host, domain, provider or credentials in any of them.

## Privacy and sharing

**Stored.** Nothing new on the server. The Self name goes into the existing audit person row and YAML for normal Self results. `hidden.php` receives the sender's details in a POST body, keeps nothing, logs nothing and writes no audit record. Browser memory keeps the same single own entry (now with the Self name); the shared person is never saved in the receiver's browser (no fieldset exists in that state).

**Audited.** Normal Self/Love results as before (Self now with the optional name). **Any request that imports hidden details is not audited**, neither the receiver nor the shared person, because the shared person never consented to a record and the receiver's input exists only to be matched against it. Creating a hidden link is not audited. Opening a hidden link records nothing about the owner.

**Shown.** The receiver never sees the shared person's name, date, time, city, coordinates, time zone or current city; only match results and the label "Your match". The sender is told, in the menu panel and in the QR block: "Anyone who gets this link or QR code can read your name, birth details and place from it. The other person's screen will not show them, but they are inside the link. Share it only with someone you trust." The receiver is told on the import fieldset that links contain the details.

**Share URL classification (rule D10).**

| Output | Derived or encoded | Where |
|---|---|---|
| Shared person's details | input, encoded | `h` (version 3 code), visible in the receiver's address bar; **not offered as a share link** |
| Receiver's own details | input | `a_*` as always |
| Self name | input, encoded | `name` / code version 2 |
| Match results | derived | recomputed |
| Share of an imported result (frozen, live, QR, copy) | **not offered** | replaced by a note; the fixed-day note gives no live link |
| Menu state, help popovers, QR collapse state | UI only | not shared |

Reason for disabling the share of an imported result: a share link would put the friend's data into a third party's hands without the friend choosing it. Re-sharing is only possible by the original owner creating a new hidden link.

**Terms paragraph (owner approves):** "You can create a link or QR code that holds your own details (name, birth date, time and place) so that another person can compare themselves with you. To create it, your browser sends those details once to this site, which does not store, log or record them. The page of the person who opens it does not display your details, but they are inside the link and can be read by anyone who decodes it, so share it only with people you trust. Opening such a link is not recorded. Links hold the details in the address, so they can also appear in browser history and in server access logs." The sentence "Opening a link that someone shared with you is not recorded again" stays.

**Terms gate.** `h` passes the gate in `next`; no processing and no audit before acceptance; a link that cannot be kept shows the existing sentence.

## Alternatives considered

- **JavaScript QR and code encoder:** no server round trip, but duplicates the layout and the append-only zone table and adds a large QR file; rejected.
- **Hidden link as a `c=` mode:** one parameter, but `c` is merged over the whole query and carries `noaudit`, reading day and tarot; a separate `h` keeps its semantics narrow (never audited, never shareable) and easy to test.
- **Hide the import fields with CSS only (still posting `b_*`):** the data would be in the DOM and the URL in clear form; rejected for the opaque `h` code.
- **Rewrite the visible URL with `history.replaceState` to drop `h`:** breaks reload and back; rejected.
- **POST form for importing:** hides `h` from history and logs but breaks stateless pages (D8, D10); open decision 4.
- **Auditing the receiver only:** feasible (`AuditRecord::fromLove` without the loved person) but needs a new record shape for a request that is meaningless without the friend; rejected, open decision 1.
- **`confirm()`/`alert()` for cleaning:** forbidden by the request; inline panel used.
- **Absolutely positioned popovers:** overflow on small screens and need computed positions; in-flow blocks used.
- **Reuse `withdraw` for cleaning:** works, but the gate message would be wrong; a separate `clean` action costs a few lines.

## Risks

- A third party's personal data is encoded in a link the receiver can decode; mitigated only by honest wording and the sender warning, not by technology.
- The receiver's address bar and history hold `h`; server logs may hold it too (same exposure class as the existing names in URLs).
- Results can allow inferences about the hidden person (signs, biorhythm curve phase). Accepted and stated.
- Clipboard behaviour differs across browsers; the fallback chain and manual checks cover Chrome, Firefox, Safari (iOS) and a non-secure context.
- `BarcodeDetector` is missing in Firefox and some Safari versions; pasting always works.
- The empty-name case: a Self user with no name shares an anonymous person; the name block then shows "nothing to compare".
- Bumping `Consent::VALUE` makes every visitor accept again (intended).
- Help copy (about 50 texts) is a content workload and must stay free of method talk; a test greps banned words.
- Raising `MIN_SEPARATION` can move crowded glyphs further from their true position; the table next to the wheel still lists exact positions.
- Hidden mode adds branches in `home.php` and `love-result.php`; the no-leak test renders the page and greps for every distinctive value.

## Tests (`php tests/run.php`; no database needed)

**Share codes** (`tests/cases/code.php`, `hidden.php`)
- Version 1 goldens unchanged (existing). New goldens for version 2 (Self with a name, with accents, with 40 four-byte characters) and version 3 (name, no name, with and without current position).
- Round trips: `decode(encodeSelf(x))` equals the long-query array including `name`; `decodeHidden(encodeHidden(p))` equals `p`; readings built from both models are identical.
- Cross rejection: `decode()` rejects a version 3 code; `decodeHidden()` rejects versions 1 and 2; both reject empty, 401 characters, bad alphabet, padding, truncation at every byte, trailing data, non-canonical encodings, bad date/time/coordinates/zone, control characters, invalid UTF-8, name over 160 bytes, a hidden code carrying unexpected flags; never throwing.
- `extractHidden`: bare code; full link with other parameters before and after; `h=` followed by a fragment; whitespace; garbage and over-long text give null.
- Old links without `name` and old `c=` codes keep their meaning and bytes.

**Request / flow** (`request.php`, `hidden.php`)
- Self name: absent, empty, valid, 40 characters, 41, control characters, no letter; `input.name` set only when valid; the audit person for a named Self contains the name, an unnamed one has `''`.
- `h` without `mode` is Love; a valid `h` drops client `b_*` keys; an invalid `h` gives the note and the normal form; `import` with a pasted link equals `h`; both present: `h` wins.
- Page render with an imported person (distinctive values, for example name "Zerbinetta", city "Reykjavik", date 1987-11-23, time 04:17, zone Atlantic/Reykjavik, latitude 64.14): the HTML contains none of them, no `name="b_`, no `Share this reading`, no `share-link`, no QR, no live link in the fixed-day note; it contains `name="h"`, the label "Your match", the results and the hidden note. The Love form without `h` has the `import` field.
- Audit skipped for an `h` request: subprocess with a closed database port and an empty error log shows no write attempt; a normal Love request still attempts one.
- Name affinity: a shared name gives the same percentage as the normal path; `anonymous` shows the "nothing to compare" state; the supporting name detail is absent in hidden mode.
- Gate: `h` and `import` survive `Consent::safeQuery`, the gate's `next` field and the redirect, and the result appears after accepting.
- `hidden.php`: no cookie gives 403; GET gives 405; missing coordinates gives `data`; a valid POST returns a link whose `h` decodes to the posted person, a QR path or `tooLong`, and no-store headers; the response does not contain the posted name or city in clear; no audit attempt and empty error log; an oversize body is rejected.
- `consent.php`: `clean` clears the cookie (expired `Set-Cookie`) and redirects to `./?cleaned=1`; `?cleaned=1` shows the gate message and `data-forget-memory`; `accept` and `withdraw` unchanged; `Consent::VALUE` is `3` and a cookie of `2` no longer passes.

**Templates and CSS** (`ui.php`)
- The menu comes before `.chooser`; has both items; the clean panel and the hidden-share panel start `hidden`; no inline script, handler or style anywhere (the existing scan is extended to the new partials).
- Love form: the old hint sentence is gone; required labels carry `*` (Self: date, time, city; Love: name, date, time, city for You, name for the loved person); the `* required` legend appears once per form; Self has an optional name input; the import fieldset follows the loved-person fieldset and precedes the memory bar.
- Help: for rendered Self, Love and hidden-Love pages, every `data-help` key exists in `Help::TEXT`; every key of `Help::TEXT` (dynamic ones expanded) appears on at least one rendered page; each text is 20-240 characters, unique, free of banned words (for example "algorithm", "formula", "calculat", "ephemeris", "sidereal time", "VSOP"); each button has `aria-controls` pointing at an existing id and `aria-expanded="false"`; ids are unique.
- QR: every QR is inside `details.share__qrbox` with `open` and `data-qr-copy` pointing to an existing input id.
- CSS regressions: `.notice` has no negative margin and has `position: relative` with a `z-index`; `.gate__actions` is not `position: sticky`; `.gate__terms` has `overflow-y: auto`; `.wheel` width at most 260 px; the wheel disc fill is not `#0f0d2c` and differs from the panel colour; the wheel stroke colour differs from `--line`; contrast ratios from the hex values: house numbers and glyphs on the disc at least 4.5, strokes on the disc at least 3; wheel font sizes at least the values above.
- `ChartWheel::layout`: the separation test of `sky.php` is rerun with the new `MIN_SEPARATION` and glyph size (1000 random charts, no overlap at the new size); output deterministic.

**JavaScript static checks** (no JS runner; `node --check` on every asset when node exists)
- `memory.js`: still no `fetch(`, `XMLHttpRequest`, `sendBeacon`, `WebSocket`, `innerHTML`, `eval(`, `cookie`, no URL literals; keeps the key and limit checks; contains `magic:share-hidden` and `data-menu-clean`; every storage call still sits in a `try`.
- Only `memory.js` mentions `localStorage` or `sessionStorage`; `share.js`, `help.js` and `import.js` mention neither.
- `share.js`: contains `navigator.clipboard`, `ClipboardItem`, `execCommand("copy")`, `isSecureContext`, the strings "Link copied" and "Press Ctrl+C", `data-qr-copy`, `role`, `keydown`; no `innerHTML`, `eval(`, `document.write`; the only `fetch(` target is the relative `hidden.php`; the SVG is built with `createElementNS`.
- `help.js`: contains `Escape` and an outside-click handler; no `innerHTML`, no network, no storage.
- `import.js`: feature-detects `BarcodeDetector` and `getUserMedia`; stops tracks (`.stop()`); no network, no storage, no `innerHTML`.

**Manual (real browser; recorded as a recipe in `docs/code.md`)**
- Terms expandable: in DevTools run `document.elementFromPoint(x, y)` at the centre of `#terms summary` and expect `SUMMARY` (not `FOOTER`); click it, the text opens; repeat at 360 px and 1280 px.
- Gate at 360 px: the text scrolls inside its region, the Accept button is always visible and never over the text.
- Clipboard: Chrome desktop, Firefox, Safari iOS and a non-secure origin: "Link copied" or the Ctrl+C fallback; QR click and Enter/Space; collapse and print preview (QR open).
- Hidden flow end to end with two browsers: grey button with empty storage leads to Self; with data the panel opens and copies; scan the QR with a phone; the receiver passes the gate, sees no friend data (view-source and address bar show only the opaque code), the result shows "Your match", no share section, nothing new in the audit table.
- Scan on Chrome Android (permission prompt, stop on Esc), paste on every browser; Firefox shows no scan button.
- Menu clean with the keyboard only; after "Yes, clean" the Terms popup returns and DevTools shows no `magic.*` keys and no `magic_terms` cookie.
- Wheel at 360 px and 1280 px: legible house numbers and glyphs, collapse works, print preview.
- Help popovers: Tab/Enter/Esc, touch tap and outside tap, screen reader announces label and expanded state.

## Open decisions for the owner

1. **Audit of an import.** Recommended: no audit at all for requests with hidden details. Alternative: audit only the receiver (needs a new record shape).
2. **Terms version.** Recommended: bump `Consent::VALUE` to `3` (new data flow to `hidden.php`, `h` in URLs). Alternative: keep `2` and only change the text.
3. **Empty Self name when sharing.** Recommended: allowed; the name block then shows "nothing to compare". Alternative: require a name before the share button works.
4. **`h` in the receiver's address bar and history.** Recommended: accept it (stateless GET, same class as existing names in URLs). Alternative: a POST import, which breaks reload and the stateless model.
5. **Server round trip to create the hidden link** (recommended) versus a JavaScript encoder and QR generator (no data sent, much more code, two copies of the code layout).
6. **Help copy**: about 50 texts to review; wording is the owner's.
7. **Wheel**: confirm about 240-260 px and the collapsible block.

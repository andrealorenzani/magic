# 0007 — Self Discovery first, Soul Affinity, Friends hidden codes (UX rework, Menu removed)

- Status: accepted
- Date: 2026-10-10

**ARCHITECTURE CHANGE: yes** (new `mode=friends` and URL parameter `nick`, a new browser-storage key, Self Discovery becomes a precondition of the other sections, the Menu is removed, a Terms change). No decision of D1-D14 is reversed; D8 is narrowed (see "Existing decisions").

## Context

The owner wants the page to read as a path: **1. Self Discovery** (who am I), **2. Soul Affinity** (me and another soul), **3. Friends hidden codes** (people who shared their hidden data with me). Today "You" is typed again in Love, the hidden-link actions live in a Menu above the chooser, and an imported hidden person has no name the visitor can refer to ("Your match"). ADR [0006](0006-menu-hidden-import-hints-and-fixes.md) built the hidden link (`h`, `import`, `hidden.php`), the Menu, browser memory (ADR [0005](0005-profile-compact-share-and-roadmap.md)) and the audit rule for hidden opens; this ADR reuses all of that and changes the structure around it.

## Existing decisions

| Decision | Effect |
|---|---|
| D1 (no Composer, no build, shared hosting) | Holds. Plain PHP and JS. |
| D2 / ADR 0003 (MySQL only for the audit) | Holds. No migration, no table or column, no audit format change. |
| D7 (pure core) | Holds. No change to `Astro`, `Time`, `Chart`, readings. Only `Request` (pure) gains a nickname rule; I/O stays in `public/`. |
| D8 (progressive enhancement) | **Narrowed, not reversed.** Self Discovery, shared links (`c=`), and `import` of a pasted link keep working without JavaScript. Two things need JavaScript because the data lives in browser storage: starting Soul Affinity from the stored Self data (without JS only a link carrying "You", such as the one offered after "Reveal my sky", works) and the whole Friends section (it shows a "needs JavaScript" note). |
| D10 (shareable state in the URL) | Holds. No new shareable output (see Privacy and sharing). |
| D12 (compact code) | Unchanged. Soul Affinity results are the existing Love results; their `c=` codes and long links stay valid. |
| D13 (browser storage only through `memory.js`) | Holds and is extended with one key; all new storage code stays in `memory.js`. |
| D14 / ADR 0006 | Kept: `h`, `import`, `hidden.php`, code version 3, no share section for a hidden result, audit of hidden opens (format 4), `noaudit`. **Superseded parts:** the Menu (removed), "Clean browser data" with the Terms cookie withdrawn (replaced by "Clear data", which leaves the cookie alone), the "Forget my data" button of the memory bar, and the label "Your match" as the only label. |
| ADR 0005 "data that arrived through a link is not saved without an action" | Holds for people typed or linked as readable data. A hidden code is opaque and is stored only at the moment the visitor submits or saves (an explicit action), see "Friends". |
| Terms gate, `noaudit`, 5-decimal coordinates | Unchanged. |

## Summary

Assumptions and defaults chosen:

1. **URLs stay.** `mode=self` is Self Discovery, `mode=love` is Soul Affinity (only the visible name changes, so every old link, `c=` code and audit `functionality` stays valid), and a new `mode=friends` is the Friends section (no result, no audit). Chooser order: Self Discovery, Soul Affinity, Friends hidden codes. With no `mode` the page still shows only the chooser, with a "Start here" badge on Self Discovery.
2. **"Unlocked" means the stored Self entry is complete** (birth date, time and a birth city with coordinates and zone; the name is optional). Soul Affinity **and** Friends are greyed until then (Friends is gated too because a friend can only be added after Self; open question 2). A greyed card still links to Self Discovery.
3. **Person A in Soul Affinity** is never typed: the form carries hidden `a_*` inputs. They come from the request when present (a shared link, or the link built right after "Reveal my sky") or are filled by `memory.js` from the stored Self entry. Nothing else about A changes, so results, share links and the audit are byte-identical to today's Love results.
4. **Clear data does not withdraw the Terms.** It erases only what the page keeps in the browser (all `magic.*` keys) and reloads Self Discovery. The Terms stay withdrawable from the Terms section. The `clean` action of `consent.php` stays, unused by the UI (cleanup is open question 5).
5. **Changing one's own data** (a different name, date, time or birth place from the stored Self entry) shows a confirmation dialog: continuing erases everything stored, friends' codes included, then stores the new Self entry. Changing only the current city does not ask.
6. **Nickname** = a short label the receiver types (`nick`, same name rules as other names). It replaces "Your match" for that hidden person on screen and in the Friends list. It is local to the receiver: never in a hidden code, never audited, never in a share link.
7. **Friends** are stored as `{code, nickname, added}`. The browser never decodes a code, so it can never display the real name or data; the server validates a code only when it is used.
8. **Terms version:** `Consent::VALUE` becomes `4` (new stored data kind: other people's codes and nicknames). Open question 1 offers the alternative of keeping `3`.
9. **Landing from a hidden link** (also for someone who never used the service): `/?h=<code>` goes through the existing Terms gate (the query is carried by `Consent::safeQuery`), then shows Soul Affinity locked with a button to Self Discovery that carries `h`; after Self is saved or revealed the code lands in Friends.

Open questions are at the end.

## Decision

### A. Page structure

`templates/home.php` loses the Menu. The chooser (`nav.chooser`) has three cards. Soul Affinity and Friends cards carry `data-needs-self`. Each is rendered **locked by default** (class `is-locked`, `aria-disabled="true"`, the text "Self Discovery fields are required to unlock this section.") with the unlocked text in a second element that stays `hidden`: Soul Affinity "Name affinity, biorhythm synchrony, common signs and a tarot spread."; Friends "Hidden codes your friends shared with you." Rendered unlocked by the server when the request already proves Self (a validated Self input on this page, or valid `a_*` in a Soul Affinity request); `memory.js` unlocks them when the stored Self entry is complete and (re)locks them otherwise. A locked card's link goes to `?mode=self`. A returning visitor may see the locked state for an instant before the script runs (risk).

Server-built unlocked Soul Affinity link: after a Self result, the card links to `?mode=love` plus the validated `a_*` of that result, so the path works without JavaScript.

### B. Self Discovery (`mode=self`)

Two columns inside the panel (stack under 720 px): **left** the existing fields (half width), **right** `partials/self-actions.php` with four buttons in this order:

| Button | Behaviour | Enabled when |
|---|---|---|
| **Generate hidden data code** | Dispatches the existing `magic:share-hidden` event with the stored entry; `share.js` and `hidden.php` are unchanged; the existing panel (QR, link, copy, warning) opens under the buttons | grey (`is-empty`, `aria-disabled`) until a complete Self entry is stored |
| **Clear data** | Opens the confirmation dialog; on confirm erases all `magic.*` keys and loads `./?mode=self` | grey until anything is stored |
| **Reveal my sky** | The normal submit (GET, report); stores the Self entry first | always |
| **Save the data** | Stores the Self entry without navigating, no report; status "Saved in this browser." Needs the required fields and a city picked from the suggestions (coordinates present); otherwise a status asks for them. Hidden until `memory.js` runs | JS and storage available |

"Reveal my sky" and "Save" use the same store routine. After a revealed result the page is rebuilt by the server with resolved coordinates, so the routine runs once more on that load (guarded by the existing just-submitted flag) to store the now-complete entry; this is what turns the two grey buttons on after the first Reveal.

The change-confirmation dialog (decision 5) intercepts both Reveal and Save when the stored Self entry exists and differs. Cancel keeps the form as typed and submits nothing.

The menu help keys become `self.hidden_code`, `self.clear`, `self.save`, `self.reveal`. The memory bar of the Self form keeps only its status line and the sentence "Your details are remembered in this browser only."

**Pending friend.** `/?mode=self&h=<code>[&nick=…]` (or the redirect target of a landing) renders, when `h` is valid, a banner "A friend shared hidden data with you. Fill in Self Discovery and press Reveal my sky or Save the data: it will be added to Friends hidden codes." with an optional "Nickname for this friend" input, and the form carries hidden `h` (and `nick`). An invalid `h` gives the existing note and no banner. Self parsing, the Self result, its share links and its audit never read `h` or `nick`.

### C. Soul Affinity (`mode=love`)

- Heading "Soul Affinity". No "You" fieldset: `partials/person-carried.php` renders a hidden fieldset (`data-person="me" data-prefix="a_" data-carried hidden`) with only hidden `a_*` inputs, prefilled from the request.
- Two columns (stack under 720 px). **Left: "Other soul's info"** (renamed from "The person you love"; fields as today, the saved-people list stays here). **Right: "Import from a user"** (`partials/import.php`): a "Nickname (optional)" input (`nick`), the paste field, the camera button ("Scan a QR code", same `import.js`), and the submit "Explore with these details". Same text rules as today for the pasted link.
- Locked state (no `a_*` in the request and no complete stored Self): a notice "Self Discovery fields are required to unlock this section." with a link to `?mode=self` (carrying `h` and `nick` when a valid hidden code is present), and the form wrapper `hidden`. `memory.js` shows the wrapper and fills the carried inputs when Self is complete. Without JavaScript the notice shows a `noscript` hint.
- A request whose `a_*` differ from the stored Self (a shared link from someone else) is kept as is, and a line "These results use the details from the link." with a button "Use my Self Discovery data" is shown.
- Hidden mode: the left column is the existing "details loaded and hidden" block, now titled with the nickname when given, otherwise "Your match". The result labels that person with the nickname. A `nick` without a hidden person is ignored.
- On submit with a hidden code (the `import` field filled, or hidden `h` present) `memory.js` adds the code to Friends (decision "Friends"). A code that the server then rejects is not removed automatically; the invalid-details note appears and the Friends row can be removed by hand.

### D. Friends hidden codes (`mode=friends`)

A panel with no server result (`Request::MODES` gains `friends`; `index.php` parses nothing, builds no view, writes no audit). `partials/friends.php` holds the static structure, a `<template>` for one row and a `noscript` note; the list is built by `memory.js` with `createElement` and `textContent` only.

- **Row:** checkbox, nickname (an editable text field, saved on change; empty shows "Unnamed friend"), the date it was added, **Compare** (a link built by the script: `./?mode=love&h=<code>&nick=<nick>` plus the stored Self as `a_*`, so the result opens at once) and **Remove**.
- **Search** by nickname: a text input filters the visible rows as the visitor types (case-insensitive, accent-insensitive where the browser supports it) and shows "Showing N of M".
- **Bulk remove:** "Select all shown", "Remove selected (n)" (disabled when none); both Remove actions ask for confirmation through the shared dialog.
- **Add** happens in two places, both only on an explicit submit or Save: the Soul Affinity import submit, and Self Reveal/Save when a pending `h` is present. Adding an existing code updates its nickname (a code is stored once).
- Empty state: "No hidden codes yet. When a friend shares a hidden link or QR code with you, add it in Soul Affinity (Import from a user) and it appears here."
- Locked like Soul Affinity.

### E. Shared confirmation dialog

`partials/confirm.php` renders one `<dialog data-confirm>` with a title, a text, "Yes, continue" and "Cancel" buttons (static, no inline script). `memory.js` offers one routine that sets the texts with `textContent`, opens it (`showModal()` where available, the `open` attribute otherwise), returns the choice, and closes on Esc or Cancel. Used by Clear data, changing Self, and the Friends removals. Texts:

- Clear data: "This erases everything this browser remembers: your Self Discovery data, saved people and the hidden codes your friends shared. It cannot be undone."
- Change of Self: "Changing your own data erases all previous data in this browser, including the hidden data shared by friends. Continue?"
- Remove friends: "Remove N hidden code(s) from this browser?"

### F. Storage (`memory.js`, documented keys)

| Key | Content |
|---|---|
| `magic.me.v1` | unchanged |
| `magic.loved.v1` | unchanged |
| `magic.friends.v1` | `{"v":1,"list":[{"code":"…","nick":"…","t":1760000000000}]}` |

Rules: `v` exactly `1`; `code` must be 1-400 characters of the URL-safe alphabet `A-Z a-z 0-9 - _`; `nick` at most 40 characters without control characters (may be empty); `t` a finite number; one entry per code; at most 60 entries and 40 KB serialized (oldest dropped first on write); a stored value over 48 KB is corrupt and ignored; every access inside `try/catch`; output only through `value`/`textContent`; no `innerHTML`; no network. Clear data and any Self change erase every `magic.*` key through the existing erase routine. The script extracts a code from a pasted link or bare code with a simple pattern for the key name `h` and the alphabet above, without decoding; the server remains the authority.

### G. Request and server

- `Request::MODES` gains `friends`. `Request::mode()` unchanged otherwise (`h` or `import` without a mode is still `love`).
- New pure `Request::nickname(array $q): array{nick: ?string, invalid: bool}`: the `nick` value under the existing name rules (1-40 characters, no control characters, valid UTF-8, at least one letter). Absent or empty is `nick: null`. Invalid gives the note "The nickname was not valid, so it was ignored." (no nickname used).
- `public/index.php`: read `nick`; when `$hidden`, set the person B `label` to the nickname else "Your match"; when `mode=self` and `h` is valid, set `$pendingHidden` (code, nickname) for the template; compute `$aKnown` for the lock state and the server-built Soul Affinity link; handle `mode=friends` (no parse, no audit, no `X-Robots-Tag` needed beyond the existing no-store). Self requests never use `h` for anything else. `unset($q['nick'])` after reading, as for `h`/`import`.
- The hidden-open mask (no detail of the shared person may reach the page) applies unchanged; only the receiver's own nickname is printed, always through `e()`.
- `Consent::VALUE` becomes `'4'`.
- `consent.php`, `hidden.php`, `Share\*`, `Audit\*`, `Db\*`, readings: unchanged.

### H. Terms (`templates/partials/terms.php`, owner approves)

Replace the sentence on "Forget my data" and extend the browser-memory paragraph: "...a list of the hidden codes other people shared with you, each with a nickname you choose. A hidden code is stored as it was received; the page never shows the details inside it. Use 'Clear data' to erase all of this; withdrawing your acceptance erases it too." The hidden-link paragraph is unchanged. The Love wording becomes "Soul Affinity (Love)".

## Changes

### Add

```
templates/partials/self-actions.php   right column of Self: the four buttons, status line, hidden-code panel (moved from menu.php)
templates/partials/person-carried.php hidden a_* inputs for Soul Affinity
templates/partials/friends.php        Friends panel: search, bulk bar, row <template>, empty state, noscript note
templates/partials/confirm.php        the shared <dialog>
tests/cases/flow.php                  the structure, lock, pending-friend and nickname checks below
```

### Change (file by file)

- `templates/home.php`: remove `partials/menu.php`; chooser with three cards and lock markup; `$title` for friends; Self form two-column layout with `self-actions.php` and the pending-friend banner and hidden `h`/`nick`; Soul Affinity form with the carried fieldset, "Other soul's info", the import column, lock notice and wrapper; Friends panel; include `confirm.php`; copy changes ("Love" to "Soul Affinity" in headings, `<title>`, description).
- `templates/partials/import.php`: legend "Import from a user", `nick` input, new help key, remains the right column.
- `templates/partials/hidden-person.php`: title uses the nickname when given.
- `templates/partials/memory.php`: remove the "Forget my data" button; the saved-people list stays for Soul Affinity.
- `templates/partials/menu.php`: deleted.
- `templates/partials/terms.php`: see H.
- `templates/love-result.php`: headings say Soul Affinity; person B label comes from the view as today (now possibly a nickname).
- `src/Request.php`: `MODES`, `nickname()`.
- `src/Consent.php`: `VALUE = '4'`.
- `src/Content/Help.php`: remove `menu.clean`, `menu.share_hidden`; add `self.hidden_code`, `self.clear`, `self.save`, `self.reveal`, `field.nick`, `friends.list`, `friends.search`, `love.import`; update copy that says "Love" where it names the section.
- `public/index.php`: see G.
- `public/assets/memory.js`: `friends` storage (read, write, add, rename, remove, search helpers), the Self actions (store routine, Save, Clear, Generate button states and event), the lock/unlock of cards and the Soul Affinity wrapper, filling and protecting the carried inputs (prefill only, never saved back, never trigger "Remember"), the change-confirmation, the shared dialog routine, the Friends list rendering and Compare links, adding a friend on submit/Save. The `initMenu` code is removed.
- `public/assets/share.js`: hook names unchanged (`data-hidden-panel`, `data-hidden-qr`, `data-hidden-status`, `data-hidden-link`, `data-hidden-copy`); only the panel's place in the DOM moves.
- `public/assets/import.js`: unchanged except the "loved name required" toggle also covers the nickname field being independent.
- `public/assets/styles.css`: `.chooser__card.is-locked`, two-column `.split` layouts, `.selfactions`, `.friends*`, `dialog.confirm`, "Start here" badge; remove `.menu*`.
- `docs/` is updated by the `documenter` afterwards (see Behaviour impact).

## Data shapes

```php
Request::nickname(array $q): array{nick: ?string, invalid: bool}
// Person B for a hidden result: parsePerson result + ['label' => <nick or 'Your match'>, 'anonymous' => bool]   (label printed with e())
// index.php template variables added: $pendingHidden (?array{code:string, nick:?string}), $aKnown (bool), $loveLink (?string)
```
```
magic.friends.v1  {"v":1,"list":[{"code":"<=400 chars A-Za-z0-9_-","nick":"<=40 chars or empty","t":<ms>}]}   (<=60 entries, <=40 KB)
URL: ?mode=friends                         the Friends panel
URL: ?mode=love&h=<code>&nick=<text>&a_*   Soul Affinity with a hidden person; nick optional
URL: ?mode=self&h=<code>&nick=<text>       Self Discovery with a pending friend
```
DOM hooks added: `data-needs-self`, `data-lock-text`, `data-unlock-text`, `data-love-locked`, `data-love-body`, `data-carried`, `data-self-actions`, `data-self-save`, `data-self-clear`, `data-self-hidden-code`, `data-pending-friend`, `data-friends`, `data-friends-search`, `data-friends-count`, `data-friends-list`, `data-friends-row` (on the `<template>`), `data-friends-selectall`, `data-friends-remove-selected`, `data-confirm`. Removed: `data-menu*`.

## Behaviour impact (for `docs/features.md`)

- No Menu. "Self Discovery" is the first step; "Soul Affinity" (was Love) and "Friends hidden codes" are greyed with "Self Discovery fields are required to unlock this section." until Self Discovery has been revealed or saved in this browser.
- Self Discovery: fields on the left, buttons on the right (Generate hidden data code, Clear data, Reveal my sky, Save the data) with their grey states and the two confirmation cases.
- Soul Affinity: no "You" part (it uses your Self Discovery data); "Other soul's info" on the left, "Import from a user" (nickname, scan, paste) on the right; the result calls an imported person by their nickname.
- Friends hidden codes: list, nickname editing, search, remove and bulk remove, Compare; needs JavaScript; all data stays in the browser.
- A hidden link works for newcomers: Terms popup, then Self Discovery, then the code is added to Friends.
- Terms version 4: everyone accepts again once. "Forget my data" is replaced by "Clear data". Known limits: the Friends list and Soul Affinity from stored data need JavaScript and browser storage; nicknames and codes exist only in this browser.

## Privacy and sharing

- **Stored (server):** nothing new. No migration, no new column. The nickname is never sent for storage; it travels only as the `nick` query value of a request the visitor submits, and it is not in the audit record or the YAML.
- **Stored (browser, only via `memory.js`):** `magic.friends.v1` (codes and nicknames) next to the two existing keys. A code is third-party personal data in encoded form; the Terms name it; Clear data, a Self change confirmation and withdrawing acceptance erase it.
- **Audited:** unchanged. Soul Affinity results are audited exactly as Love results (`functionality` stays `love`); hidden opens keep format 4 and the `hidden_link` marker, unless `noaudit`. Self results never include `h` or `nick`. `mode=friends` writes nothing.
- **Shown:** the real details inside a friend's code are never displayed (the browser cannot decode them; the server masks them as today). Only the nickname the receiver typed is shown, escaped.
- **Shareable (rule D10):** no new shareable output. A hidden result still has no share section; a Soul Affinity result without a hidden person shares through the existing `c=` code, which already encodes both people. `h`, `nick` and the Friends list are explicitly not shareable and are never put in a share link or QR (a test checks the share links of Self and Soul Affinity results opened with `h`/`nick` present). The hidden code the sender generates carries no nickname.
- **Terms gate:** `h` and `nick` pass through `Consent::safeQuery` like any query; no processing or audit before acceptance.
- The Terms cookie is not touched by Clear data.

## Alternatives considered

- **Tabs on one page without navigation:** would need all three sections rendered and a client-side router; the server-rendered `mode` pages already give shareable URLs and a working no-JS path.
- **Keeping a visible "You" fieldset in Soul Affinity:** contradicts the request and duplicates Self data; carried hidden inputs keep the server contract unchanged.
- **Server-side lookup of A (cookie or session):** stores personal data on the server; rejected (D2).
- **Decoding codes in JavaScript to show real names in Friends:** defeats the purpose of hidden data and duplicates the code layout; rejected, nicknames only.
- **Storing the nickname inside the code:** the sender cannot know the receiver's label; local only.
- **Clear data also withdrawing the Terms:** harsher, forces re-acceptance; withdrawal remains in the Terms section.
- **Adding a friend on page load of a hidden link:** would resurrect removed friends on reload and save without an action; submit/Save only.
- **A separate `friends.js` for the list:** it would need storage access or a storage API; one file keeps the "only `memory.js` touches storage" test simple (the file grows; internal sections keep it readable).
- **`confirm()` for the popups:** unstyled and blockable; the native `<dialog>` is used instead.

## Risks

- `memory.js` grows (about double) and is the single place for all storage; mitigated by the static checks and by sections.
- Locked-by-default markup flashes for returning visitors until the script unlocks; accepted for the no-JS fallback.
- Soul Affinity from stored data without JS does not work; the notice says so and shared links still work.
- A friend's code stored in the browser and a Compare link put the code and the visitor's own Self data in the URL (history, access logs), the same exposure class as ADR 0006.
- Stale or invalid stored codes: the server rejects them with the existing note; the user removes them. No automatic cleanup.
- Self change wipes friends without a way back; the dialog says so. A visitor who edits only the name also loses friends (deliberate, matches the request).
- `<dialog>` support is universal in current browsers; the `open` fallback keeps older ones usable.
- Terms bump forces re-acceptance for all visitors.
- Wording tests referring to "Love", "Menu" and "Forget my data" must be updated together (list below).
- The `clean` action of `consent.php` and the `?cleaned=1` gate text become unused UI paths.

## Tests (`php tests/run.php`; no database)

No astronomical value changes, so no new reference values are needed. A regression pin proves it: the existing Love goldens (`tests/cases/love.php`, `code.php`, `audit.php`) pass untouched, and a new case renders a Soul Affinity request with fixed `a_*`/`b_*`/`on` and compares the view model to the same inputs through the old path (identical).

**Request / server** (`request.php`, `flow.php`)
- `Request::MODES` contains `friends`; `mode=friends` renders the panel, builds no view, makes no audit attempt (closed-port subprocess, empty error log).
- `nickname`: absent, empty, valid, 40 characters, 41, control character, no letter, array value, invalid UTF-8; accents; `<script>` text is accepted as text and printed escaped.
- Hidden request with `nick`: label is the nickname, all distinctive values of the shared person (name, city, date, time, zone, latitude) absent from the HTML as in the existing no-leak test; without `nick` the label is "Your match"; `nick` without a hidden person has no effect; the nickname is not in the audit record or YAML.
- Self with a valid `h`: banner, hidden `h` and `nick` inputs present, Self result and its share links and `c=` code contain neither; the Self audit record equals the one without `h`; an invalid `h` gives the note and no banner.
- Landing: `?h=<code>` without cookie shows the gate and the code survives `Consent::safeQuery` and the redirect; after accepting, the Soul Affinity locked notice links to `mode=self` carrying `h` and `nick`.
- `Consent::VALUE` is `4`; a cookie of `3` no longer passes.
- Old links: every long link and `c=` code for Self and Love from earlier tests still parses to the same model; a Soul Affinity request with `a_*` is unlocked server-side, one without is locked.

**Templates / CSS** (`ui.php`, `flow.php`)
- No `Menu` summary or `data-menu*` anywhere; chooser has three cards in the order Self Discovery, Soul Affinity, Friends hidden codes; locked texts and unlocked texts both present; the Soul Affinity page has no "You" legend and no visible `a_*` fields, carried inputs are `type="hidden"`; legends "Other soul's info" and "Import from a user"; the import column has `nick`, `import` and the scan button; the Self form has the four buttons in order with Save, Generate and Clear present and the hooks above; one `<dialog data-confirm>`; Friends panel has search, bulk bar, a `<template>` and a `noscript` note; no inline script, style or handler in any new partial; every output escaped.
- Help coverage test: new keys used, removed keys gone, text length and banned-word rules unchanged.
- CSS: `.is-locked` rule exists and differs from the active card colours; the split layout collapses under 720 px; `.menu` rules removed; contrast of the greyed text on the card at least 3 (it is disabled-looking but readable).

**JavaScript static checks** (`memory.php`, `ui.php`; there is no JS runner, `node --check` when available)
- `memory.js` still has none of `fetch(`, `XMLHttpRequest`, `sendBeacon`, `WebSocket`, `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval(`, `new Function`; contains `magic.friends.v1`, the limits (60, 40000, 400, 40), `showModal`, `data-friends`, `data-self-save`; every storage call is in a `try`; only `memory.js` mentions `localStorage`/`sessionStorage`.
- `share.js` and `import.js` still mention no storage; `share.js` keeps its single relative `fetch`.

**Manual (real browser; add to the recipes in `docs/code.md`)**
- Fresh browser: Soul Affinity and Friends greyed with the text; the grey Self buttons Generate and Clear; Save with an incomplete form explains what is missing; Save, then reload: the buttons and cards are active.
- Reveal then edit the date and press Reveal: the dialog appears; Cancel keeps everything, Continue wipes the saved people and friends first (DevTools shows only the new Self entry).
- Newcomer path in a private window: open a hidden link, accept the Terms, see the locked notice, go to Self Discovery with the banner, press Reveal, find the friend in Friends with the typed nickname.
- Friends: add three, search by part of a nickname, rename, select all shown, bulk remove with the dialog, single remove, Compare opens a result labelled with the nickname and no friend detail in the page source.
- Keyboard-only walk of the dialog (focus, Esc), the list and the checkboxes; layout at 360 px and 1280 px; print preview unaffected.
- Browser with storage blocked: Self works, Save/Clear/Friends explain that storage is unavailable.

## Resolved questions (owner, 2026-10-10)

1. **Terms version:** bumped to 4 (`Consent::VALUE`); everyone accepts the updated Terms once.
2. **Friends gated by Self:** yes, Friends is greyed until Self Discovery is filled, like Soul Affinity.
3. **Clear data** keeps the Terms acceptance (default).
4. **Auto-add to Friends:** a code is added on the Soul Affinity import submit, and a pending code is added automatically on Self Reveal or Save (with the nickname if given); no extra confirmation click.
5. **Cleanup of the unused `clean` action** in `consent.php` and the `?cleaned=1` gate text: left as is (default); no UI uses them.
6. **Copy** (button labels, lock text, dialog texts, Terms paragraph) approved as written.

Implementation note: a Self entry without a name is carried into Soul Affinity as the name "Me", because that section requires a name for "you".

## Round 2 hardening

After review the client was tightened without changing the design: hidden codes are accepted by `memory.js` only in the strict stored shape; a friend is added once, only if not already stored, and not again after a reload (guarded by non-identifying `sessionStorage` flags `magic.justSaved` and `magic.friendHandled`, cleared with the other data); notices explain when storage is unavailable; locks only open in the script (the server unlocks when the request carries Self data); focus is restored after removals; the Terms version is 4. Deviation: the unused `clean` action stays reachable only by direct request.

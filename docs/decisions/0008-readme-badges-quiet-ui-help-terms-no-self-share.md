# 0008 — Business README and DEVELOPER.md, badges, quiet buttons, WhatsApp share, honest hidden-code wording, text help links, no Self share, compact Friends

- Status: accepted
- Date: 2026-10-10

**ARCHITECTURE CHANGE: yes** (Self Discovery results lose their share section, which changes the shareable-output contract of D10; Terms version 5; a new outbound user-initiated link to a messaging service; new repository components `VERSION`, `DEVELOPER.md`, `docs/badges/`, `scripts/update-badges.sh`; the documenter's remit and two CLAUDE.md rules change). No decision D1-D14 is reversed. D10 is narrowed (see "Existing decisions").

## Context

Follow-up polish to ADR [0007](0007-self-first-soul-affinity-and-friends-codes.md) (and 0006). Seven owner requests:

1. README becomes a short business specification with badges; developer material moves to a new `DEVELOPER.md`.
2. Buttons are too loud; make them secondary and text-like.
3. A WhatsApp share button in "Generate hidden data code".
4. The sentence "Anyone who gets this link or QR code can read your name..." confuses the owner: the code is only an encoding, not a secret. Fix the wording honestly; real encryption is a roadmap idea only.
5. The "?" help buttons become dotted-underline text links opening a small side popup; fewer of them.
6. Remove the "Share this reading" section from Self Discovery, and the "Sharing" section from Soul Affinity results built from hidden data.
7. Friends hidden codes is messy: smaller, icon buttons, small nickname field.

What exists today (checked in the code): `templates/self-result.php` includes `partials/share.php` whenever `$share` is set, so Self has "Share this reading" (frozen and live links, QR, copy). `templates/love-result.php` includes it for typed people; for a hidden-derived result `public/index.php` sets `$share = null` and the template prints a stub section titled "Sharing" with the note "This reading includes details shared privately with you, so it has no share link." Soul Affinity from hidden data therefore has no links already; only the empty section and its note are left.

## Existing decisions

| Decision | Effect |
|---|---|
| D1 (no Composer, no build, shared hosting) | Holds. Badges are static SVG files written by a shell script run by the documenter; no build step in the deploy, no dependency. |
| D2 / ADR 0003 (MySQL only for the audit) | Holds. No migration, no audit change. |
| D7 (pure core) | Holds. No change in `Astro`, `Time`, `Chart`, readings, `Share\*`. |
| D8 (progressive enhancement) | Holds and is helped: help terms are plain text without JavaScript (today the buttons are simply hidden); the WhatsApp button needs JavaScript like the rest of the hidden-code panel. |
| D10 (shareable state in the URL) | **Narrowed.** Self Discovery has no shareable output any more (its address-bar URL still works as a plain GET URL, and old `c=`/long Self links keep opening). The only shareable results are Soul Affinity results whose other soul was typed. Hidden-derived results were never shareable. The CLAUDE.md "Shareable results" rule keeps its meaning but needs the sentence in "CLAUDE.md edits" below. |
| D12 (compact code) | Unchanged. `ShareCode::encodeSelf` and `ShareLink::self/liveSelf` stay (old links, goldens, the fixed-day "live version" link); only the Share section stops using them. |
| D13 (storage only through `memory.js`) | Holds. |
| D14 / ADR 0006-0007 (hidden details) | Holds. Only the wording of the warning changes and a WhatsApp button is added to the sender's panel. |
| Terms gate, `noaudit`, 5-decimal coordinates | Unchanged. |
| ADR 0006 rejection of absolutely positioned help popovers | **Revisited.** Reason then: overflow at 360 px and no inline style. Both are solved below (bottom sheet on small screens, placement through script-set CSS properties, which the CSP allows). |
| CLAUDE.md rules "only the documenter edits docs/ and README.md" and the confidentiality exception | Need the edits listed in "CLAUDE.md edits". |

## Summary

Assumptions and defaults chosen:

1. **README** is rewritten as a business specification only (sections below). Run/test/deploy/Docker/`noaudit`/test count/agents text moves to `DEVELOPER.md`. The "How it works" paragraph is dropped (no algorithms in docs).
2. **Badges** are three static SVG files committed in `docs/badges/` and referenced by relative path from the README, so nothing is fetched from a third party and nothing is leaked by a viewer. `VERSION` (repo root, one line, `MAJOR.MINOR.PATCH`) is the single source of truth for the version; lines of code are counted by `scripts/update-badges.sh` from the tracked source files. The documenter runs the script and bumps `VERSION` when a release is cut.
3. **Buttons**: the base `button` style becomes quiet (transparent, thin border, gold text, smaller). A new `.btn--primary` keeps the gradient for the few primary actions only (Reveal my sky, Explore our connection, Accept Terms, Yes continue). Inverting the default means nothing loud is left by accident.
4. **WhatsApp**: an `<a>` that `share.js` fills in after the hidden link exists, using the public `wa.me` text link. It is user-initiated navigation, so no CSP change. The panel says the link then also travels through the messaging service.
5. **Hidden-code wording**: "not a secret, not encrypted, anyone holding it can unpack it", in the panel, the Terms and the docs. Roadmap gets an idea line only.
6. **Terms version 5** (`Consent::VALUE = '5'`): the Terms gain the honest wording and the messaging-service sentence. Open question 1 offers keeping 4.
7. **Help**: `help_button($key)` is replaced by `help_term($key, $label)`: the visible term becomes the trigger (dotted underline, upgraded by `help.js`) and opens one small popup beside it (a bottom sheet under 720 px). The count drops from 53 keys to 33 (20 removed, table below).
8. **Share removal**: no share section in Self; no "Sharing" section or note in hidden-derived Soul Affinity. Interpretation, confirmed against ADR 0006/0007: ADR 0006 already removed sharing from hidden results (the stub is the leftover); ADR 0007 kept sharing for Self. The owner now wants Self sharing gone entirely, so it is removed from Self in all cases (not only when hidden-derived, because Self never derives from hidden data). Soul Affinity keeps sharing only for typed people.
9. **Friends** list is compact with icon buttons that have accessible names and tooltips.

Open questions at the end.

## Decision

### A. README, DEVELOPER.md, badges

**README.md** (business specification, English, short sentences, no commands, no algorithms, no test counts):

- Line 1: the badge row, as three Markdown images in this order: version, lines of code, deployed. The deployed badge is a link to the project address already named in CLAUDE.md; the other two are plain images. Alt text carries no number (the number lives only in `VERSION`).
- Line 2 (first note): "This project can be used on [magic.supermaestro.org](https://magic.supermaestro.org)". Exactly this sentence, nothing before it except the badges.
- Sections, in order, each a heading and short paragraphs or bullets:
  1. **What Magic is** (three steps, for wonder not science, free, no account).
  2. **Self Discovery**: inputs (birth date, time, city, optional name, optional current city); results (Sun, Ascendant, Moon; Mercury to Pluto and the North Node with retrograde marks for births 1800-2100; Midheaven and houses; signs in tune with you and the heart sign; born under; Today's sky; three biorhythms); the buttons (Reveal my sky, Save the data, Generate hidden data code, Clear data); print.
  3. **The chart wheel** (explanation required by the owner): a round picture of the sky at the moment and place of birth. The outer ring is the twelve signs; each planet is drawn with its symbol at the place where it stood; the numbers 1-12 mark the twelve houses, areas of life such as home, work and relationships; the left edge marks the Ascendant and the top the Midheaven; a table beside the picture lists the same positions in words; it can be collapsed and it prints. Glyphs that would overlap are nudged apart for legibility, the table has the exact positions. No method is described.
  4. **Soul Affinity**: uses your Self data; the other soul (name enough; date, time, city optional; each detail unlocks more); results (name affinity, biorhythm synchrony, what you have in common, synastry, distance, Past/Present/Future tarot with 78 cards); import from a user; nickname.
  5. **Friends hidden codes**: list, nickname, search, rename, remove, bulk remove, Compare; stays in the browser.
  6. **Hidden data codes**: what the sender gets (link, QR, copy, WhatsApp); what the receiver sees; honest note that the code is an encoding, not encryption, and anyone holding it can read the details.
  7. **Sharing a Soul Affinity reading**: frozen and live link, QR; only when the other person was typed; Self Discovery and hidden-derived results have no share link; links contain the names and birth details and are not encrypted.
  8. **Where you are now** (current city, the reading day).
  9. **Remembered in your browser** (own details, saved people, friends' codes; Clear data; storage may be blocked).
  10. **Terms and privacy** (consent popup and cookie, what is recorded in the audit and what is not, geocoding service receives only the city text, nothing for tracking, retention is manual).
  11. **Limits** (planets 1800-2100 approximate, Ascendant at extreme latitudes, one house system, English only, tropical zodiac).
  12. A last line linking to `DEVELOPER.md` and the docs folder.
- Removed from README: "Testing without recording" and the `noaudit` text, Run it, Docker, Deploy, Database, How it works, Building new features with agents, the test count.

**DEVELOPER.md** (new, repo root, maintained by the documenter): requirements (PHP 8.1+, no Composer); run (`php -S`, Docker with `scripts/docker-db.sh`); tests (`php tests/run.php` and the current number of checks, refreshed by the documenter); testing without recording (`noaudit`, `on=`); database setup, migrations, purge; deployment (web directory, `scripts/deploy.sh`, `.deploy.local`, no host names); badges and releases (below); the agent workflow (`/new-feature`); layering and CSP rules in one paragraph each; links to `docs/`. No host, domain, provider or credentials, and no algorithms.

**Badges.**

| File | Content | Source of the number |
|---|---|---|
| `docs/badges/version.svg` | label `version`, value `v<VERSION>` | `VERSION` |
| `docs/badges/loc.svg` | label `lines of code`, value rounded to the nearest hundred, shown like `21.5k` | `scripts/update-badges.sh` counts lines of tracked `*.php`, `*.js`, `*.css`, `*.sh`, `*.sql` files, tests included, docs and badges excluded |
| `docs/badges/deployed.svg` | label `deployed on`, value with the site name | hand-authored once; never regenerated |

Properties: self-contained SVG (shapes, text, no `<image>`, no `<script>`, no `<style>` with `@import`, no external reference, no `<a>`, no metadata with paths or dates), a `<title>` and `role="img"` with `aria-label`; the same visual style for the three. Not produced at runtime and not served by the app. The README references them by relative path, so a viewer fetches them from the same place as the README itself.

`scripts/update-badges.sh` (bash, `git`, `wc`, `awk` only; offline; deterministic; exit 1 if `VERSION` is not `N.N.N`): rewrites `version.svg` and `loc.svg`. Rounding to hundreds keeps commits quiet. It never touches `deployed.svg`.

**Workflow for the documenter (new standing duties):** after any user- or developer-visible change run `scripts/update-badges.sh` and keep `DEVELOPER.md` current; when cutting a release, bump `VERSION`, run the script, move "Unreleased" in `docs/changelog.md` under the same version heading, and update the status line of `docs/architecture.md`. `VERSION` starts at `0.9.0`; this change is cut as `0.10.0`.

**CLAUDE.md edits** (the caller applies them; the architect does not edit CLAUDE.md or agent files):

- "Only the `documenter` agent edits `docs/`, `README.md`, `DEVELOPER.md`, `VERSION` and `docs/badges/`."
- Add: "README.md is the business specification only (no commands, no `noaudit`, no test counts); developer material lives in `DEVELOPER.md`. After every visible change the documenter runs `scripts/update-badges.sh`."
- Confidentiality exception: extend "README.md only" to "`README.md` and `docs/badges/deployed.svg` (the badge text)". If the owner declines, the badge value reads `live site` and the exception stays as is (open question 2).
- Shareable results rule: append "Self Discovery results have no share section. Soul Affinity results are shareable only when the other soul was typed; a result built from hidden data is never shareable and has no share section."
- `.claude/agents/documenter.md`: add `DEVELOPER.md`, `VERSION`, `docs/badges/` and `scripts/update-badges.sh` to step 2 and to its remit.

### B. Quiet buttons

`public/assets/styles.css` only, plus class names in templates.

- `button` (base): transparent background, `1px solid var(--line)`, gold text, `font-weight: 500`, `padding: 8px 14px`, `font-size: .9rem`, minimum height 36 px; hover lightens the border and background; focus ring unchanged. Disabled look (`.is-empty`, `[aria-disabled="true"]`): muted text, dashed border, no hover change.
- `.btn--primary`: the previous gradient. Used by exactly these: Reveal my sky, Explore our connection, Accept the Terms (gate), "Yes, continue" in the confirm dialog. Everything else (Generate, Clear, Save, Copy link, Share…, Print, Scan, Stop scanning, Remember, Remove saved, Explore with these details, Withdraw, Cancel) is quiet.
- `.linklike` stays; the Friends and help controls use their own classes below.
- Print: no change (buttons are hidden).

### C. WhatsApp button in the hidden-code panel

- `templates/partials/self-actions.php`: inside `.selfactions__actions`, after "Copy link", `<a class="btn btn--quiet" data-hidden-whatsapp href="#" target="_blank" rel="noopener noreferrer" hidden>Share on WhatsApp</a>`; under it one line of text (visible, not a tooltip): "WhatsApp will open with this link as your message. The link then also passes through WhatsApp, so send it only to someone you trust."
- `public/assets/share.js`: after a successful `hidden.php` answer, if `j.link` is an absolute `http(s)` URL and the final address is at most 1500 characters, set the anchor's `href` to the messaging service's public text link (`https://wa.me/` with a `text` parameter holding a fixed sentence "Compare yourself with me on Magic:" plus the link, percent-encoded) and unhide it; otherwise keep it hidden. The anchor is re-hidden when a new generation starts. No fetch, no script, no SDK, no `window.open`. The link text of the button is the only change visible to assistive technology besides its focusability.
- Security notes: `rel="noopener noreferrer"` plus the existing `Referrer-Policy: same-origin` mean no referrer leaves; CSP `form-action` applies to forms only and navigation is not restricted by the current policy, so `public/.htaccess` is **not changed** and gains no third-party source; a test pins this.
- Without JavaScript the whole panel does not exist, so nothing degrades.
- Open question 3: add the platform share sheet button instead or too (not requested; default no).

### D. Honest wording about the hidden code

Copy to use (owner approves). Panel note in `self-actions.php` (replaces the current paragraph):

> This link is not a secret. It only packs your name, birth date, time and place into a code; it is not encrypted, and anyone who has the link or the QR code can unpack it. The other person's screen will not show your details, but they are inside. Opening the link is recorded in the audit like any other request. Share it only with someone you trust.

Other places:

- `templates/partials/share.php` note: "The link contains the names and birth details you entered and is not encrypted. Share it only with people you trust."
- `templates/partials/terms.php`, hidden-code paragraph: replace "can be read by anyone who decodes or intercepts it" with "the link is an encoding, not encryption, so anyone who has the link or QR code can decode and read them". Add: "If you use the WhatsApp button, the link is sent as a message through that service, which then holds it." Browser-memory paragraph unchanged.
- `templates/partials/import.php` hint keeps "The link itself contains the details, so only use links from someone you trust."
- Docs (documenter): `docs/features.md` hidden-details section and Sharing section, README section 6.
- `docs/roadmap.md` Ideas (documenter): "Private hidden codes: only the chosen recipient can read them (would need the recipient to publish a key and its own ADR). Today a hidden code is only an encoding."
- No cryptography is added or designed here.

### E. Help as dotted-underline text links with a side popup

**Markup (server), `templates/partials/help.php`:** `help_button` is removed and replaced by `help_term(string $key, string $label)` printing

`<span class="help"><span class="help__term" data-help-for="help-N"><?= e($label) ?></span><span id="help-N" class="help__pop" hidden data-help="KEY"><?= e(text) ?></span></span>`

The helper is guarded like `qr_svg`; an unknown key throws under `MAGIC_TESTING` and prints the plain label otherwise. Without JavaScript the term is plain text and the popup stays hidden: the page is unchanged.

**Behaviour, `public/assets/help.js` (rewritten, no storage, no network, no `innerHTML`):**

- On load each `.help__term` gets `role="button"`, `tabindex="0"`, `aria-expanded="false"`, `aria-controls` = its popup id, and the wrapper gets the class `help--on` (CSS draws the dotted underline only under `.help--on`).
- Click, Enter and Space toggle; opening closes any other; Esc closes and returns focus to the term; a click or tap outside closes; scroll or resize closes. Focus stays on the term (non-modal); the popup follows the term in the DOM so Tab order is natural.
- Placement: on screens wider than 720 px the popup is absolutely positioned at the right of the term when it fits, else at the left, else below, and clamped inside the viewport, by setting `style.left`/`style.top` from `getBoundingClientRect()` (CSSOM, which the CSP allows; no inline attribute is written). Under 720 px the CSS makes it a bottom sheet (`position: fixed; left/right/bottom: 8px`) and ignores the script's values.
- The popup shows the key's title in bold and the text. Max width about 20 rem, `z-index` above the page and below the confirm dialog.

**Styles:** `.help__term` is inherited text colour, `border-bottom: 1px dotted currentColor`, `cursor: help`, visible focus ring (gold outline); `.help__pop` is `position: absolute`, padded, dark panel with gold left border (as today); hover needs no effect; print hides the popups and removes the underline.

**Which help to keep.** Removed keys (20): `field.name`, `field.date`, `field.time`, `field.import`, `self.hidden_code`, `self.clear`, `self.save`, `self.reveal`, `friends.list`, `friends.search`, `share.link`, `share.qr`, `share.live`, `self.midheaven`, `self.houses`, `self.retrograde`, `self.geo`, `love.sync_overall`, `love.import`, `love.match_hidden`. Reason: the label, the visible note or the button name already says it. The Self buttons get a native `title` tooltip instead (a short sentence in the template, not a help key).

Kept keys (33) and their visible trigger text:

| Key | Trigger text (the term) |
|---|---|
| `field.city` | "Why pick a suggestion?" after the label |
| `field.pos_city` | "Why add it?" after the label |
| `field.nick` | "What is this?" after the label (Import and pending-friend banner) |
| `self.sun`, `self.ascendant`, `self.moon` | the card role heading ("Sun", "Ascendant", "Moon") |
| `self.wheel` | the heading "Your chart wheel" |
| `self.node` | the heading of the North Node card |
| `self.planet.*` (8) | the planet name in its card heading |
| `self.affinity.signs`, `self.affinity.soulmate` | the two sub-headings |
| `self.born_under` | the words "Born under" |
| `self.sky` | the heading "Today's sky" |
| `bio.physical`, `bio.emotional`, `bio.intellectual` | the cycle name in each biorhythm card heading (Self and Love) |
| `love.name_affinity` | the pair-of-names heading |
| `love.sync` | the heading "Biorhythm synchrony" |
| `love.common`, `love.common_level` | the heading "What you have in common", the level pill text |
| `love.synastry`, `love.aspect` | the heading "Synastry", the words "closest" in the table caption sentence |
| `love.tarot.past/present/future` | the card position heading |
| `love.tarot_reversed` | the word "Reversed" |

Rendering the trigger inside the existing heading or label text removes the extra `.head` flex wrappers that only existed to host the button; the implementer deletes wrappers that become empty. Texts of kept keys are reviewed for the new `title` + `text` popup; no wording changes beyond removal are needed.

### F. Share removal

- `templates/self-result.php`: delete the `$share` include.
- `templates/love-result.php`: keep the include for typed people; delete the `elseif (!empty($view['hidden']))` stub section and its note. Hidden-derived results end after the tarot section.
- `public/index.php`: `$share` is built only when `$mode === 'love' && !$hidden`. For Self (and for hidden Love) no share model is built. The fixed-day note keeps its "Open the live version" link for Self and typed Love (a plain link to the same people with today's values; it is not a share section); for hidden Love `live` stays `null` as today. Old `c=` and long links of every kind keep parsing.
- `templates/partials/share.php` remains Love-only; its QR/copy wiring in `share.js` is unchanged.
- The Self page still has Print. The Self address-bar URL still carries the data; the Terms and the docs keep saying results are GET URLs that can be copied by hand, "share only with people you trust".
- `Share\ShareCode::encodeSelf`, `ShareLink::self/liveSelf` are no longer used for a Share section but stay (old-link support, the fixed-day link, goldens). The implementer does not delete them.

### G. Friends hidden codes, compact

`templates/partials/friends.php` and `public/assets/styles.css`, `public/assets/memory.js` (rendering only), `templates/partials/icons.php`.

- New static icons in `icons.php` (`icon()` names): `check-all` (select all), `trash` (remove), `compare` (arrows), `x` (clear search). Same style as the existing ones (20 px viewBox 24, `currentColor`, `aria-hidden`).
- Header: a small `<h2>` (about 1.1 rem), no help trigger. Toolbar on one row: search field (narrow, with its label visually hidden but present for screen readers: "Search by nickname"), "Showing N of M" in muted small text, then two icon buttons.
- Icon buttons use `.iconbtn`: square 32 px, transparent, gold icon, hover background `var(--line)`, focus ring. Each has `aria-label` and `title` with the same text:
  - Select all shown (`data-friends-selectall`, `check-all`; toggles to "Select none" when all shown are selected).
  - Remove selected (`data-friends-remove-selected`, `trash`, plus a small count in a `<span data-friends-selected>`; label "Remove selected (n)"; disabled when none).
- Row: one line, `display: grid` columns checkbox, nickname, date, compare icon-link, remove icon-button; padding 4 px 8px; font 0.85 rem; the nickname input is `size` about 14, min 8 rem, max 12 rem, small padding; the date is muted and hidden under 480 px. Compare is `<a class="iconbtn" data-friend-compare aria-label="Compare with this friend" title="Compare">`; Remove is `<button class="iconbtn" data-friend-remove aria-label="Remove this friend" title="Remove">`. The script sets the aria-label with the nickname when there is one ("Compare with Ann").
- Status and empty-state texts stay; hooks keep their names so `tests/cases/memory.php` and `flow.php` keep working; one new hook `data-friends-selected`.
- The dialog texts for removals are unchanged.

### H. Terms

`Consent::VALUE` becomes `'5'`. Text per D and C (messaging service sentence, "encoding, not encryption"). Everyone accepts once again.

## Changes

### Add

```
VERSION                          single line 0.9.0 (the documenter bumps it on a release)
DEVELOPER.md                     developer material moved out of README
docs/badges/version.svg          generated
docs/badges/loc.svg              generated
docs/badges/deployed.svg         hand-authored once
scripts/update-badges.sh         writes version.svg and loc.svg from VERSION and git ls-files
tests/cases/docs.php             README/DEVELOPER/badge/VERSION checks, confidentiality scan
```

### Change

- `README.md`: rewritten per A.
- `CLAUDE.md`, `.claude/agents/documenter.md`: caller applies the edits listed in A.
- `src/Consent.php`: `VALUE = '5'`.
- `src/Content/Help.php`: remove the 20 keys; update the class comment ("terms" not "buttons").
- `public/index.php`: `$share` only for typed Love; Self keeps the fixed-day live link; nothing else.
- `templates/partials/help.php`: `help_term()` replaces `help_button()`.
- `templates/partials/person-fields.php`: drop help for name/date/time; `help_term` after the city and current-city labels.
- `templates/partials/import.php`: drop help for the import field and the fieldset hint; nickname label uses `help_term('field.nick', ...)`.
- `templates/partials/hidden-person.php`: drop the help button.
- `templates/partials/self-actions.php`: drop four help buttons, add `title` tooltips, new note (D), WhatsApp anchor and line (C), `btn--primary` on Reveal.
- `templates/partials/friends.php`: per G.
- `templates/partials/share.php`: note wording (D); remove `share.link`, `share.qr`, `share.live` help; keep the toolbar.
- `templates/partials/icons.php`: four icons.
- `templates/partials/sky.php`, `geo.php`, `synastry.php`, `bio.php` (if it hosts help), `templates/self-result.php`, `templates/love-result.php`: `help_term` at the trigger texts in the table; Share changes (F).
- `templates/partials/confirm.php`, `memory.php`, `templates/home.php`: `btn--primary` on Accept, Yes continue, Explore our connection; heading and wording unchanged.
- `templates/partials/terms.php`: per D.
- `public/assets/help.js`: rewritten per E.
- `public/assets/share.js`: WhatsApp link (C).
- `public/assets/memory.js`: Friends rendering (labels, the selected count, select-all toggle, aria-labels). No storage logic change.
- `public/assets/styles.css`: quiet buttons, `.btn--primary`, `.help__term`/`.help__pop`/bottom sheet, `.iconbtn`, compact `.friends*`, removal of `.help__btn` rules.
- `public/.htaccess`: **unchanged** (pinned by a test).
- `docs/*` by the documenter: `features.md` (Before you start: Terms version 5, help; Three steps; Sharing: Soul Affinity typed people only; Hidden details: wording, WhatsApp; Friends: icons; Known limits), `architecture.md` (status line, D10 narrowed, D15 documentation and badges, §3 and §5 hidden wording, §7 help row, §8 testing, ADR list), `code.md` (map: new files, help partial/keys, `help_term`, DOM hooks `data-help-for`, `data-hidden-whatsapp`, `data-friends-selected`, recipes), `roadmap.md` (Done entry; Ideas: private hidden codes), `changelog.md` (below).

## Data shapes

```
Help::TEXT                         array<key, {title, text}>, now 33 keys (list above)
help_term(string $key, string $label): void
Markup: span.help > span.help__term[data-help-for=help-N] + span.help__pop#help-N[hidden][data-help=KEY]
WhatsApp URL (built in the browser only): https://wa.me/?text=<percent-encoded sentence + hidden link>
VERSION: "N.N.N\n"
Consent::VALUE: '5'
```

New DOM hooks: `data-help-for`, `data-hidden-whatsapp`, `data-friends-selected`. Removed: `.help__btn`. Share section hooks unchanged for Love.

## Behaviour impact (for `docs/features.md`)

- Buttons look quieter; only the main action of a form is a strong button.
- Terms version 5: everyone accepts again once. Help terms are underlined with dots and open a small popup beside the term (a panel at the bottom on phones); there are fewer of them; they need JavaScript.
- Generate hidden data code: a "Share on WhatsApp" button opens WhatsApp with the link as the message; the panel says the link then also passes through WhatsApp.
- The hidden code is described as not secret and not encrypted: anyone holding the link or QR can read the details inside. Real encryption is only a roadmap idea.
- Self Discovery has no share section. Soul Affinity has a share section only when the other soul was typed; results from Friends, Import and a hidden link have no share section and no note about it. Opening an old shared Self link still works.
- Friends hidden codes: compact list with icon buttons (select all, remove selected, compare, remove), small nickname field.
- Known limits: the WhatsApp button needs an absolute link and JavaScript; share links, where offered, are not encrypted.

## Privacy and sharing

- **Stored (server):** nothing new. No migration, no new column.
- **Stored (browser):** nothing new; no key changes. Clear data and Terms withdrawal behave as before.
- **Audited:** unchanged. Hidden-link opens are recorded as in ADR 0006/0007. Creating a hidden link is still not audited. Pressing the WhatsApp button is not recorded anywhere by the site. Self results are still audited (without a share section nothing changes there).
- **Shown:** the hidden code warning becomes plainer and honest (not a secret, not encrypted). The WhatsApp line discloses that the messaging service receives the link.
- **Third party:** the only new outbound path is the user clicking the WhatsApp link. The page itself loads no third-party script, makes no request, and sends no referrer. The messaging service receives the message text, which includes the hidden link and therefore the sender's name, birth date, time and place in encoded form. That is the user's explicit action and is named in the Terms (version 5).
- **Shareable (rule D10):** no new shareable output. A narrowing: Self results are no longer shareable; Soul Affinity results are shareable only for typed people, through the existing `c=` code (round-trip tests stay). A hidden-derived result is never shareable. The hidden code itself is not a "result"; the WhatsApp message carries the same `h` link the panel already produces, so nothing new needs encoding in a share URL. `h`, `nick` and the Friends list are still never put in a share link.
- **Badges:** static files; they contain no personal data, no path, no date, no host. The README is the only tracked file allowed to carry the project address, plus the badge text if the owner agrees (open question 2); the new test scans the other tracked trees for the name.

## Alternatives considered

- **Third-party badge service (image URLs):** leaks the viewer's request to a third party and depends on its uptime; rejected. **CI-generated badges:** needs a pipeline and a build step; rejected.
- **Version read from `docs/changelog.md` only:** the heading format is prose; a `VERSION` file is trivial to read for a script and for a test, and the test checks the changelog agrees.
- **Counting lines at runtime and serving a dynamic badge from the app:** adds a route, I/O and a cache for decoration; rejected.
- **Keeping both quiet and loud button classes with the loud one as default:** easy to leave a loud button behind; the quiet default is safer.
- **Native `popover` attribute / `<dialog>` for help:** gives top-layer behaviour but needs per-term buttons (the thing being removed) and positioning is not controllable; the existing in-DOM popup pattern is kept.
- **Tooltips only (`title`):** not reachable by touch or keyboard; rejected for the kept terms, accepted for the Self buttons.
- **WhatsApp through the Web Share API only:** not available on desktop browsers and does not target WhatsApp; the `wa.me` link works on both, with the cost of naming a third party.
- **A server endpoint that redirects to the messaging service:** adds a component, logs risk, no benefit.
- **Removing the Self share code (`encodeSelf`):** old links and goldens would break; kept.
- **Keeping the Self share section but without hidden-derived cases:** contradicts the request; Self never derives from hidden data anyway.
- **Encrypting hidden codes now:** out of scope by owner decision; roadmap idea only.

## Risks

- **Dead-ish code:** `encodeSelf`/`liveSelf` have no Share-section user. Mitigated by tests and by the fixed-day link; a later ADR may remove them.
- **Old shared Self links** still open and are still not audited (`noaudit`); users who bookmarked them see no way to regenerate. Accepted.
- **Terms bump** forces everyone to accept again (open question 1).
- **WhatsApp:** the third party holds the link; some links exceed what the service accepts (the button hides above 1500 characters). The `wa.me` format is a public convention that can change; the test pins only our side.
- **Help popups:** the largest template churn in the change (about 30 call sites); wrappers removed from headings can alter spacing. The side popup relies on script-set positions; under 720 px the CSS bottom sheet is the fallback. The implementer checks 360 px and 1280 px.
- **Quiet buttons:** lower visual weight can hide actions; the contrast of gold on the panel and of the muted disabled state must stay at least 4.5 and 3 respectively (tested from the colour tokens).
- **Badge drift:** the LOC badge is stale until the documenter runs the script; the test tolerates 10 percent and `VERSION` equality with the changelog is exact.
- **Confidentiality:** the badge text contains the site name; CLAUDE.md must be updated first or the fallback text used (open question 2).
- **Interpretation of request 6:** if the owner meant "Self share only when ... hidden" it is moot (Self is never hidden-derived); the default follows the explicit instruction.

## Tests (`php tests/run.php`; no database)

No astronomical output changes, so no new reference values; the existing goldens in `love.php`, `code.php`, `audit.php`, `share.php` must pass untouched.

**`tests/cases/docs.php` (new)**
- `VERSION` matches `^\d+\.\d+\.\d+\n?$`; the newest dated heading in `docs/changelog.md` has the same `MAJOR.MINOR` (or `Unreleased` is above it).
- `docs/badges/version.svg` contains `v` plus the `VERSION` value; `loc.svg` contains a `k`-style number within 10 percent of the live count (tracked file types above); all three badges are well-formed XML, have `role="img"`, a `<title>`, and none contains `<script`, `href`, `xlink`, `http`, `<image`, `@import`, `url(`.
- `README.md`: first non-empty line starts with three `![` images referencing `docs/badges/` in the order version, loc, deployed, the third wrapped in a link; the first note line equals the required sentence; no occurrence of `noaudit`, `docker`, `php tests`, `scripts/`, `Composer`, `.deploy`; contains the sections' headings in order and the words "chart wheel"; none of the banned algorithm words used by the help test.
- `DEVELOPER.md` exists and mentions `noaudit`, `php tests/run.php`, `scripts/deploy.sh`, `scripts/update-badges.sh`.
- Confidentiality scan: no tracked file other than `README.md` (and `docs/badges/deployed.svg` if the exception is approved) contains the site name or its domain; the string is built in the test from two fragments so the test file itself does not contain it. Existing scans for hosts and credentials stay.
- `scripts/update-badges.sh` runs offline in a temp copy and reproduces `version.svg` byte for byte.

**Request / server / pages (`flow.php`, `hidden.php`, `share.php`, `ui.php`)**
- A Self result page (any input) contains no `share-h`, no "Share this reading", no `data-copy`, no `data-qr-copy`, no `id="share-link"`; an old Self `c=` code and an old long Self link still render the same result; with `on=` in the past the fixed-day note still has a live link.
- A Love result with typed people still has the Share section, the frozen and live links and the QR; the frozen link round-trips (existing tests); share links contain no `h` or `nick` even when the request had them.
- A hidden-derived Soul Affinity result contains no "Sharing" heading and no "has no share link" sentence; also checked through Import, `h=` and Friends Compare URLs; the no-leak test (distinctive values absent from the HTML) still passes; the fixed-day note has no live link for it.
- `Consent::VALUE` is `5`; a cookie of `4` no longer passes the gate; the gate still carries `h`/`import`.
- Hidden panel: the new note text is present and the old sentence "Anyone who gets this link or QR code can read your name" is absent (`hidden.php` test line that pinned it is updated); the page contains the words "not a secret" and "not encrypted"; the WhatsApp anchor exists, starts `hidden`, has `target="_blank"` and `rel="noopener noreferrer"`, and its explanatory line is present; Terms text contains "encoding, not encryption" and the messaging-service sentence.
- Buttons: exactly the listed primary buttons carry `btn--primary`; CSS has `button` without the gradient and `.btn--primary` with it; contrast of the quiet text on the panel colour at least 4.5 and of the disabled text at least 3 (computed from the hex tokens as in `ui.php`).
- Help: for rendered Self, Love, hidden-Love and Friends pages every `data-help` key exists in `Help::TEXT`; every `data-help-for` points to an existing `help-N` id, ids unique; every key of `Help::TEXT` (dynamic expanded) is rendered on some page; the 20 removed keys are gone and `count(Help::TEXT) === 33`; no `help__btn` anywhere; no help markup left inside a `<label>`; texts 20-240 characters, unique, banned words rule unchanged; unknown key throws in tests.
- Friends: the panel has two icon buttons with `aria-label` and `title` and no visible text labels besides the count, the row template has an icon-only Compare and Remove with `aria-label`, the nickname input has a `size` or max width rule, `data-friends-selected` exists, icons are `aria-hidden`; no inline script, style or handler in any changed partial (the existing layering scan); every output escaped.

**CSS static (`ui.php`)**
- `.help__term` has a dotted underline only under `.help--on`; `.help__pop` is `position: absolute` and has a `max-width`; under `@media (max-width: 720px)` it is `position: fixed` with `bottom`; print hides `.help__pop` and removes the underline; `.iconbtn` has a minimum 32 px size; the old `.help__btn` rules are gone.

**JavaScript static checks**
- `help.js`: contains `Escape`, `closest(".help")`, `role`, `tabindex`, `aria-expanded`, `aria-controls`, `keydown`, `getBoundingClientRect`, `style.left`; no `innerHTML`, `eval(`, `fetch(`, `XMLHttpRequest`, `localStorage`, `sessionStorage`; `node --check` when available.
- `share.js`: contains the single messaging-service address constant, `encodeURIComponent`, `data-hidden-whatsapp`; the only `fetch(` target is still the relative `hidden.php`; no `window.open`, `innerHTML`, storage.
- `memory.js`: all existing static rules unchanged (no network, no HTML injection, storage only here); `data-friends-selected` is written with `textContent`.
- `public/.htaccess` is byte-identical to the previous CSP line set: `script-src 'self'`, `form-action 'self'` present, no third-party host anywhere; no template contains `<script src="http`.

**Manual (real browser; add to the recipes in `docs/code.md`)**
- Hidden code: generate, press Share on WhatsApp on a phone and on desktop; WhatsApp opens with the sentence and the link; with JavaScript off the panel is absent.
- Help: Tab to a term, Enter/Space opens, Esc closes and returns focus, outside tap closes; popup stays inside the viewport near the right edge at 1280 px; bottom sheet at 360 px; screen reader announces "button, collapsed/expanded".
- Quiet buttons: contrast review on all forms; keyboard focus visible; the disabled Generate/Clear still read as disabled.
- Friends: three entries, icon buttons by keyboard and by screen reader names, tooltips on hover, layout at 360 and 1280 px.
- Self result and a hidden-derived Soul Affinity result: no share block; print preview of both.
- With the project root as web directory, `VERSION`, `DEVELOPER.md` and `docs/` return 404.

## Open questions (defaults taken)

1. **Terms version**: bump to 5 (default; the Terms gain a third-party sentence and the plain wording). Alternative: keep 4. **Resolved: version 5.**
2. **Badge text and confidentiality**: default is to extend the CLAUDE.md exception to `docs/badges/deployed.svg` so the badge can read "deployed on" plus the site name. If the owner declines, the badge reads `live site` and the README link carries the address.
3. **WhatsApp only** (default) versus also offering the platform share sheet in the hidden panel.
4. **Version in README**: one version badge, no repeated "current version" text, so the number has a single source (default). Alternative: a prose line the documenter must keep in sync.
5. **LOC scope**: tracked source files including tests and shell scripts (default). Alternative: `src/`, `public/`, `templates/` only.
6. **Release number**: this change cut as 0.10.0 (default); the documenter may choose 0.9.1.
7. **Triggers on form labels** read "Why pick a suggestion?", "Why add it?", "What is this?" (default). Alternative: drop those three helps and add a one-line visible hint.

## Resolutions (owner, 2026-10-10)

1. Terms version bumped to 5 (`Consent::VALUE = '5'`).
2. The deployed badge may carry the site name; the confidentiality exception covers `README.md` and `docs/badges/deployed.svg`.
3. WhatsApp only (no platform share sheet).
4. One version badge, no repeated version text.
5. Lines of code counted over tracked source files, tests and scripts included.
6. Release number 0.10.0 (`VERSION` and the changelog heading match).
7. Help trigger labels as listed in section E.

Implementation notes: the badge XML declaration of the SVG namespace is the only address allowed inside a badge; the docs test removes it before scanning for external references. The Self page still has a `data-qr-copy` hook inside the hidden-code panel (not a share section); the test pins only the share-section hooks.

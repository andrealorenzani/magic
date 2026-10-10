# Changelog

Newest first. Maintained by the `documenter` agent: every user- or developer-visible change adds a line under "Unreleased" in the same commit, and a release moves them into a dated version.

## Unreleased

## v0.10 - 2026-10-10 - Business README, badges, quiet buttons, help terms, no Self share

ADR: [0008](decisions/0008-readme-badges-quiet-ui-help-terms-no-self-share.md).

**Added**
- Badges (`docs/badges/version.svg`, `loc.svg`, `deployed.svg`), `VERSION` (0.10.0) and `scripts/update-badges.sh`; `DEVELOPER.md` with the developer material; `tests/cases/docs.php` (264 tests).
- "Share on WhatsApp" button in the Generate hidden data code panel (opens WhatsApp with the link as the message, with a line saying the link then passes through that service).
- `.btn--primary` for the few main actions (Reveal my sky, Explore our connection, Accept the Terms, Yes continue).
- Compact Friends list with icon buttons (select all, remove selected with a count, Compare, Remove) and a small nickname field.

**Changed**
- README is now a business specification (badge row, one note with the site address, sections in a fixed order, a chart wheel explanation); commands, `noaudit`, test count and agent text moved to `DEVELOPER.md`.
- Buttons are quiet by default (thin border, gold text); only `.btn--primary` keeps the strong look.
- Hidden-code wording is honest: not a secret, an encoding and not encryption, anyone holding the link or QR can read the details. Share section note reworded the same way.
- **Terms version 5** (`Consent::VALUE` is `5`): everyone accepts again; the Terms state "encoding, not encryption" and the messaging-service sentence.
- Help: the "?" buttons are replaced by dotted-underline terms (`help_term()`) that open one small popup beside the term (a bottom panel on phones); keys reduced from 53 to 33. The Self buttons use tooltips instead.
- UI notes reworded.

**Removed**
- "Share this reading" in Self Discovery. Old shared Self links still open.
- The "Sharing" stub section and note in Soul Affinity results built from hidden data.
- The 20 help keys that repeated what the label already said, and the `.help__btn` styles.

**Migration:** visitors re-accept the Terms (cookie value `5`). No database migration; audit unchanged.

## v0.9 - 2026-10-10 - Self first, Soul Affinity, Friends hidden codes

ADR: [0007](decisions/0007-self-first-soul-affinity-and-friends-codes.md).

**Added**
- Page as a path: Self Discovery first ("Start here"), then Soul Affinity and the new Friends hidden codes (`mode=friends`, no result, no audit). Both are locked, with "Self Discovery fields are required to unlock this section.", until a complete Self entry is stored (the server unlocks them when the request carries Self data; the script only ever unlocks).
- Self Discovery buttons on the right (`partials/self-actions.php`): Generate hidden data code, Clear data (grey until data is stored), Reveal my sky, Save the data.
- Friends hidden codes list in `localStorage` (`magic.friends.v1`, at most 60, strict code shape): nickname edit, search, remove, bulk remove, Compare.
- `nick` parameter (`Request::nickname`): receiver-local nickname for a hidden person; never in codes, audit or share links.
- One shared confirm dialog (`partials/confirm.php`) for Clear data, changing the Self data (wipes friends) and removing friends.
- Newcomer path from a hidden link: Terms, then Self Discovery with a pending-friend banner; the code is added to Friends once on Reveal/Save, only if not already stored.
- No-storage notices; non-identifying `sessionStorage` flags `magic.justSaved` and `magic.friendHandled`.
- New partials `self-actions`, `person-carried`, `friends`, `confirm`; new tests `tests/cases/flow.php` (248 tests).

**Changed**
- Love is called Soul Affinity in the UI only (`mode=love`, URLs, share codes and audit unchanged). It has no "You" part: person A comes from the stored Self data or the request (name "Me" if empty). "Other soul's info" is on the left, "Import from a user" (camera scan, paste link, nickname) on the right.
- Self Discovery fields are half width.
- **Terms version 4** (`Consent::VALUE` is `4`): everyone accepts the Terms once again; they now mention friends' codes and nicknames kept in the browser.
- D8 narrowed: Soul Affinity from stored data and Friends need JavaScript.
- Help keys changed (`menu.*` removed; `self.hidden_code`, `self.clear`, `self.save`, `self.reveal`, `field.nick`, `friends.*`, `love.import` added).

**Removed**
- The Menu (`templates/partials/menu.php`) and "Forget my data". "Clear data" replaces them and keeps the Terms acceptance. The `clean` action of `consent.php` and `?cleaned=1` remain but no UI reaches them.

**Migration:** visitors re-accept the Terms (cookie value `4`). No database migration; audit unchanged.

## v0.8 - 2026-10-10 - Menu, hidden details, help popovers, Self name

ADR: [0006](decisions/0006-menu-hidden-import-hints-and-fixes.md).

**Added**
- Menu above the mode chooser: "Clean browser data" (in-page confirmation) and "Share my Self Discovery hidden data" (grey until the stored own entry is complete).
- Hidden-details link and QR (`?h=<code>`, share-code version 3) created by `public/hidden.php` (POST); import by pasted link or code (`import`, works without JavaScript) or by QR scan (`public/assets/import.js`, where `BarcodeDetector` exists).
- Hidden result: the shared person is not displayed and is labelled "Your match"; no share section.
- Optional Self name (share-code version 2 only when a name is given; `name=` in links).
- "?" help popovers (`Content\Help`, `partials/help.php`, `public/assets/help.js`).
- Click-to-copy and collapsible QR blocks with "Link copied"; "* required" marks.
- `consent.php` action `clean`; `Request::hiddenCode`; `ShareCode::encodeHidden`, `decodeHidden`, `extractHidden`; tests `hidden.php` and `ui.php` (232 tests).

**Changed**
- The chart wheel is smaller, brighter and collapsible (`ChartWheel::MIN_SEPARATION` raised).
- The hint under the loved person in Love is removed.
- The Terms popup text scrolls and the Accept button no longer overlaps it.
- `Consent::VALUE` is `3`; the Terms describe hidden links.
- One clipboard routine with fallbacks for copy buttons and QR.
- Audit `format_version` 4 is used for Love results opened from a hidden link (`AuditRecord::FORMAT_VERSION_HIDDEN`); other records stay at 3.

**Fixed**
- The Terms and Conditions section at the end of the page did not open because the footer overlapped it.
- The gate's Accept bar could cover the Terms text on small screens.

**Privacy**
- Creating a hidden link sends the sender's details once to the site; nothing is stored, logged or recorded there.
- "Hidden" means only not shown on screen: the link and the receiver's address bar contain the data.
- Opening a hidden link IS audited with both people's full data and the marker `loved_person_source: hidden_link` (format 4); `noaudit` skips it. Such results have no share links.

**Migration:** visitors re-accept the Terms (cookie value `3`). No database migration.

## v0.7 - 2026-10-09 - Current position, short links, browser memory, derived features

ADR: [0005](decisions/0005-profile-compact-share-and-roadmap.md).

**Added**
- Optional "Current city" in Self and Love (for you and for the loved person); the reading day follows it (in Love, person A's), and the result says "Today for you: ...".
- "Distance and geography" card (approximate, km and miles, time difference).
- Midheaven, the house of each body and an SVG chart wheel (Self).
- Moon phase, "Today's sky" (Self; compact in Love) and "Born under" (Self).
- Synastry table in Love (aspects between the two charts, with counts).
- Tarot uses the full 78-card deck (minor arcana as composed texts).
- Short share links (`?c=` code, version 1, up to 400 characters), a time-zone table for them, and a collapsed link box with a small QR in the Share section.
- Browser memory (`public/assets/memory.js`): the user's own details are remembered in the browser, "Saved people" in Love, "Remember these details", "Forget my data".
- Geocoding cache size cap with daily clean-up.
- New pure namespaces `Earth`, `Sky`; new `ChartWheel`, `Share\ShareCode`, `Share\TimeZoneTable`; `docs/features.md` and this changelog.

**Changed**
- Terms cookie value is now `2`; the Terms text names the current position and the browser memory.
- Audit `format_version` is 3 (YAML keys only, no migration): current positions, reading-day basis, distances, moon phase, synastry counts, Midheaven.
- Share links default to the short code; old long links are accepted unchanged. A `c=` code is merged with other query keys and the code wins; a damaged code is ignored with a note.
- Live tarot readings can differ from before for the same people and day (the deck is now 78 cards); frozen links with `t=` keep their meaning.
- The clock is read once, in `public/index.php`; "today" can now come from the current position's time zone.
- Place labels in short codes are cut at 32 bytes (known limit). Coordinates keep 5 decimals.

**Fixed**
- Cosmetic typos in ADR 0005.

**Privacy**
- The audit now also records the current position (when entered) and the new result summaries. Nothing new in the SQL columns.
- Browser memory stays on the visitor's device, is named in the Terms, and is erased by "Forget my data" and by withdrawing acceptance. It is not read or written by any server code.

**Migration:** visitors re-accept the Terms (one time). No database migration.

## v0.6.1 - 2026-10-09 - Workflow

**Changed**
- Agent workflow: clarify step, architecture-change gate, reviewer "Docs drift" list, changelog rule.

## v0.6 - 2026-10-09 - Sharing and polish

ADR: [0004](decisions/0004-sharing-tarot-spread-and-polish.md).

**Added**
- Share section: frozen and live links, QR code generated in pure PHP, tarot spread in `t=`.
- `?noaudit` to skip the audit write; share links carry it.
- Past / Present / Future tarot spread with position-specific texts.
- Richer "In common" and a compact biorhythm synchrony.

**Changed**
- Terms gate fixes: `no-store`, `Vary: Cookie`, query kept through acceptance, CSP additions.
- Audit `format_version` 2.

**Removed**
- The 3-day tarot.

## v0.5 - 2026-10-09 - Rename and local run

**Added**
- Docker local run (`docker compose up --build`) and `scripts/docker-db.sh`.
- Terms and Conditions consent popup and withdraw section.

**Changed**
- Project renamed from Arcana to Magic (namespace `Magic\`).

**Removed**
- Algorithm details and references from docs and code comments.

## v0.4 - 2026-10-09 - Audit trail and consent

ADR: [0003](decisions/0003-mysql-audit-trail.md).

**Added**
- MySQL audit trail (`magic_audit`, `magic_audit_person`) with a YAML summary, written after the page is sent; migration, migrate and purge scripts.

**Privacy**
- Personal data is now stored on the server for each result; no IP address or user agent. The tables were not yet created in the real database.

## v0.3 - 2026-10-09 - Two modes

ADR: [0002](decisions/0002-self-discovery-and-love-modes.md).

**Added**
- Self discovery: Mercury to Pluto and North Node, sign affinities, "love of your life" sign, biorhythms.
- Love: name affinity, biorhythm synchrony, common values, tarot; partial data for the loved person.
- Printable results; `on=` to fix "today".

## v0.2 - 2026-10-08 - Deployment

**Added**
- Serve `public/` from the project root through a root `.htaccess`.
- Deploy to the server after every commit (`scripts/deploy.sh`); hosting details kept private.

## v0.1 - 2026-10-08 - PHP rewrite

ADR: [0001](decisions/0001-rewrite-in-php.md).

**Added**
- Sun, Ascendant and Moon signs from date, time and city, in PHP with no database.

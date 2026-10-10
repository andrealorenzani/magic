# 0001 — Decisions before release 1.0.0

- Status: accepted
- Date: 2026-10-10

Consolidation of the former ADRs 0001-0008 (plain-text numbers only; the originals stay in git history and in the tag `pre-release-1.0.0`). Grouped by theme. Future ADRs start at 0002 and use `0000-template.md`.

## Context

Magic started as a static JavaScript page (v0.1) and grew over ten versions into a server-rendered PHP site with three steps (Self Discovery, Soul Affinity, Friends hidden codes), sharing, browser memory, an audit trail and a consent gate. Eight ADRs recorded the steps. This record keeps what is still in force and why, and drops the per-feature detail (which lives in `docs/architecture.md`, `docs/code.md` and `docs/features.md`).

## Decision

### 1. Platform and layering (ADR 0001, 0002)
- PHP 8.1+, server-rendered, Apache shared hosting, no Composer, no build step, no framework. The page must work on cheap hosting with nothing to install.
- The astronomy, time, chart, reading and share logic is written in-house and stays pure (no I/O, no clock); only `public/`, `Request`, `Geo` and `Db` touch the outside world. The clock is read once, in the front controller. A test greps the pure code for I/O.
- One front controller on `mode` (old URLs stay valid) instead of separate pages per mode. Printing uses a print stylesheet, not a PDF library.
- Geocoding is server-side with a disk cache and a bundled fallback list; visitors' queries never reach third-party scripts. Time zones come from PHP's own tz data.
- Trade-offs: planets are approximate and limited to 1800-2100 (outside it only the big three); a sign can be wrong right at a boundary; date-only people get flagged approximate values. Entertainment outputs (name affinity, biorhythms, scores, tarot) are labelled as such and carry no medical or fate claims.
- Rejected: heavy ephemeris libraries or binaries (not available on shared hosting), a JavaScript core with PHP only as host, random or session-based tarot (needs state, breaks shared URLs), a PDF library.

### 2. Database and audit trail (ADR 0003)
- MySQL is the only allowed database, via PDO with prepared statements; all tables are prefixed `magic_`; schema changes are idempotent SQL files in `migrations/`. Credentials live only in the gitignored `config.php`.
- The only use of the database is the audit trail: every Self or Soul Affinity result is recorded (functionality, UTC time, reading date, names, birth data and place of the people involved, and a short versioned YAML summary of the result). Not stored: IP address, user agent, referrer, session, URL.
- The write happens after the page is flushed, with a short connect timeout; any failure is swallowed (class and code logged only). A database problem never breaks a page. The app works with no database at all.
- Two tables (record plus people) rather than one wide table or a stored view-model: easier erasure and indexing by name, stable under copy changes. A small hand-written, strictly quoting YAML emitter (no extension, no dependency); the format is versioned (`format_version`, currently up to 4) so readers handle old rows.
- Retention and erasure are manual (`scripts/db-purge.sh`, delete-by-name query). The owner is the data controller; lawful basis, privacy policy and contact are the owner's job.
- Rejected: logging to a file, storing IP or user agent, synchronous write before output, JSON column, flat wide table, signed links to make `noaudit` trustworthy.
- Open risks: shared-hosting MySQL may refuse remote connections (migration can be pasted in the hosting panel); under plain CGI a visitor may wait a few seconds for the write; the audit tables are still not created in the real database (see architecture §9); the owner now holds third parties' data and must operate retention and erasure; the YAML emitter is protected only by golden tests.

### 3. Terms and Conditions consent (ADR 0003, 0004, 0006, 0007, 0008)
- The page is unusable until the visitor accepts the Terms (blocking popup, functional cookie `magic_terms`, no identifier, text in `templates/partials/terms.php`, expandable section at the end, withdraw button). Without consent the query is ignored and nothing is audited.
- Every response sends `private, no-store` and `Vary: Cookie`; the cookie is `Secure` also behind a TLS-terminating proxy; the gate is inert for the footer and Terms section behind it; the accept form posts to a root-relative address.
- The cookie value is a Terms version; bumping it makes everyone accept again. Current version 5 (it names browser storage, current position, hidden data, "encoding not encryption" and the messaging-service sentence). Bump whenever a data flow changes.
- The loved person never consents: only what was entered is stored, and the Terms say so.

### 4. Sharing and the URL as state (ADR 0004, 0005, 0008)
- Shareable state lives in the URL, no server-side storage: whatever a shared page shows that is not derived from the input (reading day, tarot cards and orientation) is encoded in the link, validated on read (invalid is ignored with a note, never a crash) and covered by a round-trip test. This is a rule in `CLAUDE.md`.
- The primary link is a short versioned code (`?c=`), decoded strictly, merged over other keys (the code wins); the long readable query stays accepted forever. Time zones in a code come from an append-only table; place labels and total length are capped; coordinates keep 5 decimals so sender and receiver see identical results.
- The QR code is generated in pure PHP and shown as inline SVG; no external service, no script library (CSP and privacy). Links too long for a QR show the link only.
- A frozen link (people, day, tarot) and a live link (people only). Opening a shared link is not audited again because share links carry `noaudit`; `noaudit` is a testing and owner switch, not a security control.
- The reading day comes from the place the visitor gave (local calendar day), else the UTC date; never from the browser clock, so a link opens identically anywhere.
- Since 0008 only Soul Affinity results with a typed other soul have a Share section. Self Discovery has none; the old Self link encoders and old links still work.
- Rejected: server-stored short codes in MySQL, signed links, external QR services, deflate or binary payloads (links must stay editable page URLs), a browser time zone for "today", rounding coordinates to 2 decimals (owner chose to keep 5).
- Open risks: links carry personal data and appear in history and access logs (the Share section and forms warn); a wrong code layout would break shared links silently (version nibble, canonical re-encoding check, golden codes); a deck or copy change alters live readings of the same day (frozen links are safe); the hand-written QR encoder was verified structurally and by manual scans only.

### 5. Hidden details and Friends (ADR 0006, 0007, 0008)
- A person's details can be packed into a hidden link or QR (`?h=`, code version 3) so the receiver compares without seeing or retyping them. The link is created by a POST to `public/hidden.php`, which stores, logs and audits nothing. The receiver may give the person a local nickname; it is never part of a code, an audit record or a share link.
- Opening a hidden link is audited like any Soul Affinity result with both people (owner decision), marked as coming from a hidden link; the receiver's `noaudit` skips it. A hidden result has no share section and the page never renders the hidden person's data (a no-leak test greps for every value).
- Honesty: "hidden" only means not shown on the receiver's screen. The code is an encoding, not encryption; the link, QR, address bar, history and server logs can hold it. All wording says so. A user-initiated "Share on WhatsApp" link is the only third-party hand-off and is named in the Terms.
- Importing a pasted link or code works without JavaScript. The Friends list lives in the browser only (at most 60 strictly shaped codes); the browser never decodes a friend's code.
- Rejected: a JavaScript encoder and QR generator (two copies of the layout), rewriting the visible URL, POST import (breaks stateless pages), auditing the receiver only, decoding codes in the browser to show names, storing the nickname in the code, platform share sheet, encrypting codes now.
- Open risks: a third party's data is readable by whoever holds the link; results allow inferences about the hidden person (accepted and stated); clipboard and QR-scan support differ by browser (pasting always works); the messaging service sees the link when the user presses the button and may reject very long links.

### 6. Browser memory and the page flow (ADR 0005, 0006, 0007)
- Only `public/assets/memory.js` uses browser storage (`localStorage` keys `magic.me.v1`, `magic.loved.v1`, `magic.friends.v1`, plus two non-identifying `sessionStorage` flags). Validation on read, no network, no HTML injection, content named in the Terms, erased by "Clear data" (which keeps the Terms acceptance), by a confirmed change of the Self data and by withdrawing. Data that arrived through a link is not saved without an explicit action.
- The page is a path: Self Discovery first, then Soul Affinity and Friends, both locked until a complete Self entry exists (the server unlocks when the request carries Self data; the script only ever unlocks). The Menu was removed.
- Progressive enhancement: Self Discovery, shared links and import work without JavaScript; Soul Affinity from stored data and the Friends section need it. One shared native `<dialog>` handles confirmations (no `confirm()`).
- Rejected: server-side lookup of the visitor (cookie or session) as it would store personal data, tabs with a client-side router, a separate script for the Friends list, adding a friend on page load, automatic saving of link data.
- Open risks: shared computers (mitigated by the visible forget button); locked markup flashes for returning visitors; a stale stored code is rejected by the server with a note, with no automatic cleanup; changing the Self data wipes the friends (the dialog says so).

### 7. Interface and content (ADR 0004, 0005, 0006, 0008)
- No inline scripts or styles (strict CSP in `public/.htaccess`); everything echoed through `e()`. Script-set CSS properties are allowed by the CSP.
- Help is a set of dotted-underline terms (`help_term()`) that open one small popup beside the term (a bottom sheet on phones), plain text without JavaScript; absolutely positioned popovers were first rejected, then adopted once the overflow and CSP problems were solved. Native `popover` and tooltips-only were rejected for the terms (touch and keyboard reach).
- Buttons are quiet by default; only the few main actions are primary. Colour contrast minimums are tested from the colour tokens.
- Content (tarot with 78 cards and 132 position texts, 142 composed minor-arcana strings, help and sign copy) lives in `Content\*` so copy and languages change in one place. Copy never talks about methods; a test greps banned words. Quality of the copy is a content risk reviewed by the owner.

### 8. Documentation, badges and workflow (ADR 0008 and the agent workflow)
- `README.md` is a business specification only (badge row, one note with the site address). Commands, tests, deploy, Docker and the release process live in `DEVELOPER.md`. No algorithms in any doc, ADR, UI text or code comment.
- `VERSION` is the single version source; `scripts/update-badges.sh` writes static, self-contained badges (the deployed badge is hand-written). A test checks the version, the badges and where the site name may appear (only README and the deployed badge). No third-party badge service.
- Work goes through architect, implementer, reviewer, documenter, commit, deployer. Only the documenter edits docs. `docs/changelog.md` is updated for every visible change. Deploy uploads only committed changed files, runs the tests first and cannot delete remote files.
- Host, domain, provider and credentials never appear in tracked files.

## Alternatives considered

See the "Rejected" lines in each theme above.

## Risks

Collected under each theme. The ones to watch first: the audit tables not yet created in the real database; personal data in URLs, history and logs; the hidden-code wording versus user expectations of privacy; manual operation of retention and erasure; the Terms version needing a bump on every data-flow change.

## Test plan

Not applicable to this record. The behaviour it summarises is covered by `php tests/run.php`.

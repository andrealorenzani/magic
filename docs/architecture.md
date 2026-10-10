# Architecture

> Status: v0.9 (Self Discovery first, Soul Affinity, Friends hidden codes, no Menu, Terms version 4; hidden-details links, help popovers, Self name; PHP, three modes, current position, short share links + QR, browser memory, Midheaven/houses/wheel, moon phase and Today's sky, synastry, 78-card tarot, MySQL audit trail, Terms and Conditions consent, Docker local run; the audit tables are not yet created in the real database, see §9) · Owner of this file: the `documenter` agent (`.claude/agents/documenter.md`).

## 1. Goal

Magic is a web page for magic lovers. The visitor enters **birth date**, **birth time (hh:mm)** and **birth city**, and receives:

| Result | Meaning | Depends on |
|---|---|---|
| Sun sign | zodiac star sign | date + time (cusp days) |
| Ascendant (rising sign) | sign on the eastern horizon at birth | date, time, **latitude, longitude** |
| Moon sign | sign the Moon occupied | date + time |

The home page is a path of three steps (ADR [0002](decisions/0002-self-discovery-and-love-modes.md), reworked by ADR [0007](decisions/0007-self-first-soul-affinity-and-friends-codes.md)):

- **Self discovery** (`?mode=self`): the three results above plus Mercury–Pluto and the mean North Node, Midheaven, houses and a chart wheel, the signs with most affinity, a "love of your life" sign, the three biorhythms, moon phase and "Today's sky", and a printable page.
- **Soul Affinity** (`?mode=love`, called Love before ADR 0007; the URL, share codes and audit `functionality` are unchanged): locked until Self Discovery data is stored; the visitor (taken from the stored Self data or from the request, no "You" fields) and another soul (only the name is required for them): name affinity %, biorhythm synchrony, common Sun/Moon/Ascendant values, synastry, a Past/Present/Future tarot spread (78 cards) for the couple, printable. The other soul's data can be imported from a hidden code. Self and Soul Affinity have an optional current position (ADR [0005](decisions/0005-profile-compact-share-and-roadmap.md)) and a **Share** section (short link + QR code, ADR [0004](decisions/0004-sharing-tarot-spread-and-polish.md)).
- **Friends hidden codes** (`?mode=friends`, no result, no audit): the hidden codes other people shared, kept in the browser with a local nickname; search, rename, remove, bulk remove, Compare. Locked until Self Discovery data is stored.

Long-term ambition: the most-used page for magic lovers, so adding features must be cheap. Visitor-facing behaviour is described in [features.md](features.md); the release history in [changelog.md](changelog.md).

## 2. Constraints & key decisions

| # | Decision | Why | Trade-off |
|---|---|---|---|
| D1 | **PHP 8.1+, server-rendered, deployable on Apache shared hosting** (Apache + PHP, FTP/SFTP/git deploy). No Composer, no build step, no framework | Hard requirement (hosting). Nothing to install on the server | We write a tiny autoloader/router ourselves |
| D2 | **Database: MySQL only, via PDO, used only for the audit trail** (superseded in part by ADR [0003](decisions/0003-mysql-audit-trail.md); originally "no database"). Tables are prefixed `magic_`; no accounts, no saved charts | The owner wants an audit record of results; everything else needs no storage. The app still works with no database at all | Personal data now rests in the DB (see §5). Further persistence needs its own ADR (§7) |
| D3 | **Astronomy implemented in-house in PHP** (`Magic\Astro\*`) | No dependency (no Swiss Ephemeris binary on shared hosting), testable. Sun and Moon are very accurate; planets (1800–2100) are approximate | A sign may be wrong very close to a boundary (more likely for planets); outside 1800–2100 only the big three are shown |
| D4 | **Tropical zodiac** | The Western standard users expect | Sidereal could be added later |
| D5 | **Geocoding via Open-Meteo** (lat, lon, IANA time zone), called **server-side**, results cached on disk in `cache/`, bundled fallback list if the API is down | One call yields all place data; server-side call keeps visitors' queries out of third-party JS and allows caching | Needs outbound HTTP (`curl` or `allow_url_fopen`, both normally on at shared hosts) |
| D6 | **Time zones via PHP's `DateTimeZone`** | Correct historical DST/offsets from the tz database, no library | Very old dates use LMT as in the tz database |
| D7 | **Pure core, thin web layer** | `Magic\Astro`, `Chart`, `Time` have no I/O and are unit-tested from the CLI | — |
| D8 | **Progressive enhancement** (narrowed by ADR 0007): Self Discovery, shared links and importing a pasted link work without JavaScript (server resolves the city text); JS adds city autocomplete. Soul Affinity started from stored data and the whole Friends section need JavaScript (the data lives in browser storage) | Robust, SEO-friendly, shareable GET URLs | — |
| D10 | **Shareable state lives in the URL** (ADR 0004, rule in CLAUDE.md): whatever a result shows that is not purely derived from the form input (reading day `on`, tarot cards and orientation `t`) is encoded in the share URL, validated on read (invalid: ignored with a note, never a crash) and covered by a round-trip test | A shared page shows exactly what the sender saw, with no server-side storage | Links are long and carry personal data |
| D9 | **Agentic dev workflow** in `.claude/` | See §6 | — |
| D11 | **Current position and the reading day** (ADR 0005): an optional current city gives the local calendar day for "today" (Love: person A's); `on=` wins; otherwise the UTC date. The clock is read only in `public/index.php` | A link opens identically anywhere because the day comes from a place, not from the browser | Visitors without a current city still get the UTC date |
| D12 | **Compact share code** (ADR 0005): the primary share link is `?c=<code>`, versioned and decoded strictly; the long readable query stays accepted. A `c=` code is merged with other query keys and the code wins | Short links and small QR codes | A place label is cut at 32 bytes in a code; codes are capped at 400 characters |
| D14 | **Hidden details** (ADR [0006](decisions/0006-menu-hidden-import-hints-and-fixes.md), Menu replaced by ADR [0007](decisions/0007-self-first-soul-affinity-and-friends-codes.md)): a person's details can be packed into a link or QR (`?h=<code>`, share-code version 3) that the receiver's screen never displays; the result has no share section; opening it is audited like any Love result (both people, marker `loved_person_source: hidden_link`, format 4) unless the receiver adds `noaudit`. The link is created by a POST to `public/hidden.php` (button "Generate hidden data code" in Self Discovery). The receiver may give the person a local nickname (`nick`), never part of a code, audit or share link | The receiver can compare themselves without seeing or retyping the other person's data | "Hidden" only means not shown on screen: the link and the receiver's address bar contain the data |
| D13 | **Browser memory** (ADR 0005, extended by ADR 0007): `public/assets/memory.js` keeps the visitor's own details, saved people and the friends' hidden codes in `localStorage`, validated on read, no network, no HTML injection; named in the Terms; erased by "Clear data" (which keeps the Terms acceptance), by changing the Self data after confirmation and by withdrawing acceptance | Convenience without server-side accounts | Shared computers (mitigated by the visible forget button and no automatic save of link data) |

## 3. System overview

```
  POST consent.php ──► Consent (accept sets, withdraw (and the unused clean) clear cookie magic_terms, 303 back to /)
 Browser ── GET /?mode=self&date&time&city[&lat&lon&tz][&on=]            ┐
         ├─ GET /?mode=love&a_name&a_date&a_time&a_city..&b_name&b_..    ├─► public/index.php
         └─ GET /?mode=friends (panel only, no result, no audit)
    │                                                                     ┘        │
    │  assets/autocomplete.js (one per [data-place])                               ├─ Consent::given (cookie magic_terms) else only the terms popup
    │                                                                              ├─ Share\ShareCode::decode(c) merged into the query · Request::mode / resolveToday (clock read here only)
    │  assets/share.js (copy / Web Share / click-to-copy QR / hidden-link flow) ── POST hidden.php ──► Share\ShareCode::encodeHidden + Qr (stores nothing)
    │  assets/help.js ("?" popovers) · assets/import.js (QR scan into the import field)
    │      └─ GET api/cities.php?q= ──► Geocoder ──────────────────────────────────┤
    │  assets/memory.js (browser-only memory: own details, people, friends' codes, locks, confirm dialog)
    │  assets/print.js (un-hides the Print button)                                 ├─ Request::parse | parseLove ─► Geo\Geocoder ─► Open-Meteo
    │                                                                              │      (cache/ on disk, Geo\FallbackCities if unreachable)
    │                                                                              ├─ SelfReading::build  ─┐ pure view-models
    │                                                                              ├─ LoveReading::build  ─┤
    │                                                                              │    Chart::full / partial ─► Time\Zone, Astro\Angles/Sun/Moon/
    │                                                                              │         Ascendant/Planets/MeanNode/Zodiac
    │                                                                              │    Love\SignAffinity, NameAffinity, Common · Bio\Biorhythm, Synchrony
    │                                                                              │    Tarot\Reading (+ Content\* copy) · Share\ShareLink, ShareCode, Qr (pure)
    │                                                                              │    Astro\Houses/MoonPhase/Aspects · Sky\Today · Love\Synastry · Earth\Geography · ChartWheel (pure)
    │                                                                              ├─ templates/home.php ► self-result.php | love-result.php (+ partials/)
    │                                                                              └─ after the page is flushed, only for results:
    │                                                                                   Audit\AuditRecord (pure) ─► Db\AuditLog::tryWrite ─► MySQL magic_audit*
    │                                                                                   (no config / DB down / any error: swallowed, page unaffected)
```

Hidden details (ADR 0006/0007): `?h=<code>` (or a pasted link/code in `import`) is decoded by `Request::hiddenCode` after the consent check; the person is merged into the query for parsing only, the Soul Affinity form shows a "details loaded and hidden" block instead of the other soul's fields, `LoveReading` labels that person with the receiver's nickname (`Request::nickname`, request-local) or "Your match", and the result shows match results only. `h` without `mode` means Love. An invalid `h`/`import` is ignored with a note. The page has no share section for such a result. `public/hidden.php` (POST only, needs the Terms cookie, `Cache-Control: private, no-store`) receives the sender's validated details, answers JSON with the link and QR path, stores and logs nothing and is not audited. With `mode=self` a valid `h` only shows a "pending friend" banner and carries `h`/`nick` in the form; Self parsing, result, share links and audit never read them. Sections are locked in the markup until the request proves Self data (server) or the stored Self entry is complete (`memory.js`, which only ever unlocks). One shared `<dialog>` (`partials/confirm.php`) confirms Clear data, changing the Self data and removing friends. The help texts are `Content\Help` (about 50 keys) rendered by `partials/help.php` and toggled by `help.js`.

Layering rule (checked by `reviewer` and `tests/cases/layering.php`): `Astro\*`, `Time\*`, `Earth\*`, `Sky\*`, `Chart`, `ChartWheel`, `Love\*`, `Bio\*`, `Tarot\*`, `Share\*`, `SelfReading`, `LoveReading` and `Audit\*` never do I/O (no network, files, `$_GET`, echo, clock). The clock is read only in `public/index.php` (`time()` once, passed down). Only `public/`, `Request`, `Geo` and `Db` touch the outside world. `public/assets/memory.js` is the only code that uses browser storage (`localStorage`; plus two non-identifying `sessionStorage` flags, `magic.justSaved` and `magic.friendHandled`, guarded and cleared with the rest). Templates only render; they escape everything with `e()`.

## 4. Feature pipeline

Shared core (both modes): wall-clock birth data plus place and time zone become a UTC instant (`Zone::toUnix`, DST gap shifted forward, overlap resolved to the first occurrence); `Chart` then gives the Sun, Moon and Ascendant signs, each as a `Zodiac::fromLongitude` position (sign, degree, minute).

Self mode (`SelfReading::build($input, $today)`): `Chart::full` adds Mercury–Pluto with retrograde marks (years 1800–2100), the mean North Node (any year), the Midheaven and the house of each body; `ChartWheel::layout` prepares the drawing; `Sky\Today` gives the moon phase and "Today's sky" (also the phase at birth); `Earth\Geography` gives the optional distance card; `SignAffinity::rank` returns the 3 signs with most affinity and one "love of your life" sign; `Biorhythm::forPerson` gives the three biorhythms for `today`.

Love mode (`LoveReading::build($a, $b, $today)`): `Chart::partial` builds a chart with whatever data each person gave (date only gives Sun and Moon flagged `approx` when the sign may depend on the birth time; no Ascendant); `NameAffinity` gives a percentage; `Synchrony::pair` compares the two people's biorhythms; `Common::between` lists shared Sun/Moon/Ascendant values with a score; `Love\Synastry::between` lists aspects between the two charts; `Sky\Today` and `Earth\Geography` add a compact sky and the distance card; `Tarot\Reading::spread` gives a stateless Past / Present / Future spread of three distinct cards with position-specific texts (same people and day give the same cards; a valid `t=` parameter replaces the draw). Self mode has no tarot.

The reading day ("today") is chosen by `Request::resolveToday`: a valid `on=YYYY-MM-DD` (1900–2100) wins; otherwise the calendar day at the user's current position (Self: the visitor; Love: person A), computed from the single clock read in `public/index.php`; otherwise the server's UTC date. A visitor with no current city far from UTC can therefore see biorhythms and tarot one day off.

Sharing (ADR 0004): `Share\ShareLink` and `Share\ShareCode` build links from the validated model, never from the raw query. The primary link is the short code `?c=<code>` (ADR 0005); the readable long query (`Share\ShareLink`, `t=<spread>` e.g. `t=16u,5r,9u`: card number 0-77 + `u`/`r`) stays accepted. `index.php` decodes `c` right after the consent check and merges it over the other query keys (the code wins); a bad code is ignored with a note. The Share section offers a **frozen link** (people, reading day, and in Love the tarot spread) with a small QR code and a **live link** (people only), the links inside a collapsed `<details>`. Place labels are cut at 32 bytes in a code, codes are capped at 400 characters, and time zones in a code come from the append-only `Share\TimeZoneTable`. The QR is generated in pure PHP (`Share\Qr`) and rendered as inline SVG; nothing goes to a third party. Links up to 520 bytes get a QR, longer ones show the link only. Coordinates are rounded to 5 decimals at parse time (unchanged by ADR 0005) so sender and recipient see identical results. An invalid `t` is ignored with a note. A page fixed to a past `on=` shows a note with a link to the live version. The absolute link origin is built in `public/index.php` from `Http::origin`/`basePath` (validated Host header; relative link and no QR if odd).

`?noaudit` (any value) skips the audit write for that request; share links carry it, so opening a shared link is not recorded again. It is a testing and owner-side switch, not a security control.

Limits: Ascendant (and houses, wheel) flagged approximate at extreme latitudes; whole-sign houses only; planets unavailable outside 1800–2100; biorhythms, scores, name affinity and tarot are entertainment. Love results show a compact biorhythm synchrony (three cards, curves inside a collapsible `<details>`) and a reorganised "In common" section (summary, sign chips, level pills, trait pills, a meaning sentence per body, placeholder cards for what could not be compared). Documentation never describes how values are computed (CLAUDE.md rule).

## 5. Security & privacy

- All user input validated in `Request::parse` (date/time regex + `checkdate`, lat/lon ranges, `tz` checked against `DateTimeZone::listIdentifiers()`); output escaped with `e()`.
- Outbound requests go only to the fixed Open-Meteo host; the query is URL-encoded.
- Only the **city text** (birth city and, if entered, current city) is sent to a third party (Open-Meteo).
- **Audit trail (ADR 0003): personal data IS stored.** For every Self or Love result the app writes to MySQL: the functionality, a UTC timestamp, the reading date, the names, birth date, birth time and place (label, lat, lon, time zone) of the user and, in Love mode, of the loved person (only what was entered), and a YAML summary of the result (signs, name affinity, synchrony, tarot, biorhythm values), unless the request carries `noaudit`. **Not stored:** IP address, user agent, referrer, session ids, the URL or query string. Nothing is written for the mode chooser, validation errors or notes-only pages.
- **Terms and Conditions consent (ADR 0003, update):** the page is unusable until the visitor accepts the T&Cs. Without the cookie `magic_terms` a blocking popup is shown; the request is not processed (the query is ignored) and **no audit record is written**. Accepting is a POST to `public/consent.php`, which sets the cookie and redirects back (carrying the original query through `Consent::safeQuery`; a query that could not be kept shows a sentence on the gate); a withdraw button in the T&Cs section clears it and returns to `./?withdrawn=1`; "Clear data" in Self Discovery erases browser memory (needs JavaScript) but keeps the cookie. The `clean` action of `consent.php` and `?cleaned=1` remain but are not reachable from the UI. The gate fixes of ADR 0004: every response of `index.php` and `consent.php` sends `Cache-Control: private, no-store` and `Vary: Cookie`; the footer and Terms section are inert behind the gate; the accept button has autofocus; small-screen layout scrolls; the cookie is `Secure` also behind a TLS-terminating proxy (`Http::isSecure`); the form posts to a root-relative `consent.php`. The text is `templates/partials/terms.php`, shown in the popup and as an expandable "Terms and Conditions" section at the end of the page. The loved person never consented; only what is entered is stored.
- **Cookies:** no tracking cookies. The only cookie is the **functional consent cookie** `magic_terms` (value `4` since ADR 0007, so every visitor accepts the Terms once again; 1 year, `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS, no identifier).
- **Current position (ADR 0005):** the optional current city is recorded in the audit YAML (label, coordinates, time zone) with the day basis and distances; the Terms say so. The SQL columns still describe the birth place only; `format_version` is 3.
- **Browser storage (ADR 0005):** `memory.js` keeps the visitor's own details, a short list of people and the hidden codes friends shared (with local nicknames; at most 60) in `localStorage` (keys `magic.me.v1`, `magic.loved.v1`, `magic.friends.v1`), only on the visitor's device. Two `sessionStorage` flags (`magic.justSaved`, `magic.friendHandled`) hold no personal data. The browser never decodes a friend's code, so it cannot show the details inside; the nickname is never sent for storage and is not in audits or share links. No server code reads or writes it; it is sent only as the form data the visitor submits. The Terms name it; "Clear data", a confirmed change of the Self data and withdrawing acceptance erase it; a hidden code is stored only on an explicit action (importing in Soul Affinity, or Reveal/Save with a pending code), once, and not again if already stored or removed in the meantime. Without usable storage the page says so. The script has no network calls and never injects HTML (checked by `tests/cases/memory.php`).
- **Hidden details (ADR 0006):** to build a hidden link the sender's browser sends their details once to `public/hidden.php`, which does not store, log or audit them and does not echo them. "Hidden" only means *not shown on the receiver's screen*: the link or QR contains the details, the receiver's address bar and history hold `h=`, and server access logs may too. Opening such a link is audited like a Love result, with both people's full data, the marker `loved_person_source: hidden_link` and `format_version` 4 (the receiver's `noaudit` skips it). The result has no share links. The Self name is optional and recorded for Self results.
- **Retention and erasure:** there is **no automatic retention or deletion**. The owner is the data controller and must operate the process by hand: `scripts/db-purge.sh --days N` deletes audit rows older than N days (person rows follow through `ON DELETE CASCADE`; run it manually or from a cron job on a trusted machine); remove-on-request is a delete by name query, e.g. `DELETE FROM magic_audit WHERE id IN (SELECT audit_id FROM magic_audit_person WHERE name = ?)` with a bound parameter (examples in [code.md](code.md)). Host backups age out on the host's schedule. Lawful basis, privacy policy and contact address are the owner's decision; this is not legal advice.
- **Who can read:** whoever has the database credentials (hosting panel, MySQL client, host backups). No page, API or log of the app outputs audit rows. The app logs only an error class and code on a failed write (`audit: write failed <Class> <code>`), never request data, host or user names.
- **Names now travel in the GET URL** (Love mode also carries the loved person's birth data). They can appear in web-server access logs, browser history and shared links; the Love form warns "share the link only with people you trust". Result pages send `X-Robots-Tag: noindex` and `<meta name="robots" content="noindex">`; all pages send `Cache-Control: private, no-store`. The Share section repeats the warning that links contain names and birth details. POST was rejected because it breaks shareable URLs (D8); a "private mode" would need its own ADR. Mention this in any privacy policy.
- **SQL and YAML safety:** only prepared statements with bound parameters (emulation off); the YAML emitter quotes every user-derived string and accepts only fixed `[a-z][a-z0-9_]*` keys, so input cannot add keys or documents.
- Names are validated: 1–40 characters, no control characters, at least one letter, valid UTF-8.
- `src/`, `templates/`, `cache/`, `tests/`, `docs/`, `migrations/`, `scripts/` and `config.php` live **outside** the web root (`public/` is the document root); `cache/` also has a deny `.htaccess`.
- If the web directory is the project root instead, the root `.htaccess` 301-redirects `/public/...` to `/...` and rewrites everything else into `public/`, so `src/`, `templates/`, `cache/`, `docs/`, `tests/` and `README.md` return 404 (this relies on Apache `mod_rewrite`; prefer `public/` as web root). A `RedirectMatch 404` also covers `config.php`, `config.php.example`, `migrations/` and `scripts/` as a second layer.
- `public/.htaccess` sets a strict CSP (no inline scripts/styles — keep it that way), including `frame-ancestors 'none'; form-action 'self'; base-uri 'none'`.

## 6. Agentic development workflow

```
/new-feature "idea"
    │
    ▼
 architect ──► reads docs/ ──► docs/decisions/NNNN-title.md (design, risks, test plan)
    ▼
 implementer ──► PHP code + tests per the ADR (`php tests/run.php` must pass)
    ▼
 reviewer ──► layering, accuracy, security, a11y ──► findings (loop to implementer if blocking)
    ▼
 documenter ──► updates architecture.md, code.md, features.md, changelog.md, roadmap.md, README.md
    ▼
 commit ──► deployer ──► scripts/deploy.sh (tests, then SFTP upload of changed files)
```

Agents: `.claude/agents/`; orchestration: `.claude/commands/new-feature.md`; rules: `CLAUDE.md`. `docs/` is the shared memory. ADRs: `docs/decisions/` (0001 records the move to PHP, 0002 the two modes, 0003 the MySQL audit trail and the consent update, 0004 sharing, the tarot spread and the gate fixes, 0005 current position, short links, browser memory and the derived features, 0006 menu, hidden-details sharing and import, help popovers, Self name and the Terms/wheel fixes, 0007 Self Discovery first, Soul Affinity, Friends hidden codes, Menu removed, Terms version 4).

## 7. Extension points

| To add… | Touch |
|---|---|
| A body in Self mode | planets are data-driven: add a row to `Planets::ELEMENTS`/`BODIES`, copy in `Content\Bodies::INFO`, and the planet grid in `templates/self-result.php` picks it up from `Chart::full` |
| Another house system | `Astro\Houses` (whole-sign today) and `Chart::full`'s `houses`; the wheel and `Content\Houses` follow |
| A new aspect type or orb | constants in `Astro\Aspects` and copy in `Content\Aspects` |
| Daily reading copy | `Content\Daily`, composed in `Sky\Today` |
| Planets before 1800 | extend `Planets`; `supports()` is the single gate |
| Another mode | `Request::MODES` (today self, love, friends), a `Request::parseX`, a pure `XReading::build`, `templates/x-result.php`, a chooser card in `templates/home.php` |
| Tarot cards / copy / languages | `Content\TarotDeck`, `Content\TarotMinor` (minor arcana), `Content\TarotPast`/`TarotPresent`/`TarotFuture` (per-position texts, read through `Content\TarotSpread`), `Content\Traits`, `Content\Bodies`, `Content\Signs` only |
| Scoring weights | constants at the top of `Love\SignAffinity`, `Love\Common`, `Bio\Synchrony::THRESHOLD` |
| Another stored field in the audit | the pure `Audit\AuditRecord` builders (YAML key or person field) and, for a new column, a new idempotent `migrations/NNN_*.sql` plus the INSERT in `Db\AuditLog`; bump `AuditRecord::FORMAT_VERSION` when the YAML layout changes |
| A new shareable output | encode it in `Share\ShareLink` and `Share\ShareCode` (new format fields need a new code version; the time-zone table is append-only), parse/validate it in `Request`, classify it as derived or encoded, add a round-trip test in `tests/cases/share.php` and `tests/cases/code.php` |
| Browser-stored data (`magic.me.v1`, `magic.loved.v1`, `magic.friends.v1`) | only through `public/assets/memory.js` with documented keys, validation on read, and the Terms text updated |
| Other persistence (saved/shared charts, accounts, caching city lookups in SQL) | MySQL via the existing `src/Db` layer (`Config`, `Connection`), SQL in `migrations/NNN_*.sql` with the `magic_` prefix, a new ADR and a privacy decision first |
| A help text (the "?" popovers) | `Content\Help::TEXT` (key, title, text) and `help_button('key')` in the template; the test checks every key is used |
| Another hidden or private output | classify it per D10/D14: never put the hidden person in a template, give it its own parameter and code version in `Share\ShareCode`, decide about audit and share in an ADR |
| New page/route | new file in `public/` (plain PHP entry scripts; `index.php` is a small front controller on `mode`) + template in `templates/` |

## 8. Testing

`php tests/run.php` — dependency-free runner (exit code ≠ 0 on failure); core checks live in `tests/run.php`, ADR 0002 checks in `tests/cases/*.php` (`planets`, `bio`, `love`, `tarot`, `request`, `layering`, `audit`, `share`, `position`, `sky`, `code`, `memory`, `hidden`, `ui`, `flow`), required by the runner. Astronomy is checked against published reference values (equinox, a known natal chart, planets at a fixed epoch and sign ingresses); the zone converter against known offsets including DST gap/overlap; `Request` against bad/tampered input; `audit` pins the YAML emitter (golden strings, injection), the record builders and the failure isolation of the DB layer (no database needed); `share` checks the QR encoder's structure, share-link round trips and `noaudit`; `position` the reading day, current position and distances; `sky` houses, moon phase, aspects, synastry, the wheel and daily copy; `code` the short codes (round trips, invalid codes, the time-zone table, the gate); `memory` that `memory.js` has no network or HTML injection and the templates carry the hooks; `hidden` the hidden code, the import flow, `hidden.php`, `consent.php` and that no hidden detail leaks into the page; `flow` the page structure and locks, the pending friend, nicknames, Terms version, Friends hardening; `ui` the chooser, required marks, help coverage and wording, CSS regressions (Terms section, gate, wheel) and static rules for the scripts; `layering` greps the pure code for I/O and the templates for unescaped output or inline script/style. The web layer is verified with `php -S localhost:8081 -t public` or the Docker setup (§10) and curl/browser. The optional DB integration test runs only with `MAGIC_DB_TEST=1` and a `config.php`.

## 9. Deployment (Apache shared hosting)

1. Create a domain/subdomain with PHP ≥ 8.1 and set its **web directory to `<project>/public`** (Panel → Domains → Manage Websites → Edit). *Alternative:* leave the web directory at the project root; the root `.htaccess` then serves `public/` as the site (verified live).
2. Upload the whole project (SFTP/rsync/git) so `src/`, `templates/`, `cache/` sit next to `public/`.
3. Ensure `cache/` is writable by the PHP user (`chmod 775 cache`).
4. Database (optional; without it every page works and audit writes fail silently):
   - `config.php` (gitignored, in the project root, **outside `public/`**, denied by the root `.htaccess`) holds the MySQL host, port, database, user and password. Generate it with `scripts/make-config.sh`, which reads the database section of `~/.password` (the section name is kept only in the gitignored `.deploy.local` as `DB_PASSWORD_SECTION`) and writes the file with mode 600, printing no values; or copy `config.php.example` by hand.
   - `scripts/deploy.sh` uploads `config.php` when its content hash changed (or with `--all`), never showing its content. Caveat: the uploaded file gets the server's default mode, not 600; it is private by location and the deny rules only.
   - Apply the schema with `scripts/db-migrate.sh` (runs `migrations/*.sql` in order with the `mysql` client; migrations are idempotent, no bookkeeping table).
   - A database failure (no config, no PDO, refused connection, SQL error, oversize record) never breaks a page: the write happens after the response is flushed (`fastcgi_finish_request()` when available), with a 2 s connect timeout, and only `audit: write failed <Class> <code>` is logged. Under plain CGI the visitor may wait a few seconds; check on the server.
   - MySQL errors from the shell scripts are sanitised to `mysql error <code> (<SQLSTATE>)`.
5. Visit the site.

**Current status (honest):** the `magic_audit` and `magic_audit_person` tables have **not** been created in the real database yet. The database server refused the connection (access denied) from the development machine. The migration still has to be applied from a host the database server allows, or by pasting `migrations/001_create_magic_audit.sql` into the hosting panel's SQL tool. Until then the app runs normally and audit writes fail silently (logged as a class and code only).

### Automated deploy

`scripts/deploy.sh [--all] [--dry-run]` wraps the `sftp-upload` skill (`~/.claude/skills/sftp-upload`). Target settings come from the gitignored `.deploy.local` (template: `.deploy.local.example`); credentials live outside the repo in `~/.password`. Behaviour:

- Refuses to run with uncommitted tracked changes and runs `php tests/run.php` first; failing tests abort the deploy.
- Uploads only committed files changed since the last deployed commit (recorded in the gitignored `.deploy-state`); `--all` uploads every tracked file.
- Cannot delete remote files: removed files are listed as warnings and must be deleted manually.
- Run by the `deployer` agent as the last step of `/new-feature`. Host, domain and provider names never appear in the repo. `config.php` is uploaded separately from the tracked files; its last uploaded hash lives in the gitignored `.deploy-config-hash`.

## 10. Local run with Docker

`docker compose up --build` starts the page at http://localhost:8081 without installing PHP or MySQL. `docker-compose.yml` defines two services:

- `web`: built from `docker/Dockerfile` (`php:8.3-apache` + `pdo_mysql`, `mod_rewrite`/`headers`/`expires`); the project is bind-mounted, and, as on shared hosting, the web directory is the project root with the root `.htaccess` serving `public/`. `MAGIC_CONFIG` points to `docker/config.php`, which reads the DB settings from the container environment. Published on `127.0.0.1:8081` only.
- `db`: MySQL 8.4, data in the named volume `dbdata`, **not published to the host**; `migrations/` is mounted into `/docker-entrypoint-initdb.d`, so the schema is applied automatically the first time the volume is created. The DB password is a throwaway local default, overridable with `MAGIC_DB_PASSWORD`.

`scripts/docker-db.sh` works on that database (`migrate`, `shell`, `query "SQL"`, `audit [N]`, `reset`); the password never leaves the containers. Steps and commands are in the README. This setup is for development only and is not part of deployment.

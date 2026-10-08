# 0003 — MySQL audit trail of results (supersedes D2 and the "not stored" privacy statement)

- Status: accepted (implemented with the deviations listed in "Implementation notes")
- Date: 2026-10-09
- Supersedes: **D2** ("no database, no personal data at rest") and the sentence in `architecture.md` §5 "Birth data and names are processed per request and **not stored**". Nothing else is broken (see "Existing decisions touched").

## Context

The owner wants an audit trail: for every request that produces a result (Self discovery or Love) the app stores which functionality was used, the people involved (name, date of birth, place of birth, time of birth), when, and a YAML description of the response. CLAUDE.md already allows MySQL via an ADR, requires the `magic_` table prefix, `migrations/*.sql` applied with the `mysql` command, credentials only in the gitignored `config.php` and `~/.password`, and "a DB failure must never break a page".

Today a request carries ONE set of user data: Self = one person; Love = person A (the user) and person B (the loved one). The three "blocks" of the request are therefore:

| Role | Meaning | Filled when |
|---|---|---|
| `self` | the person of the Self-discovery tab | Self request (the person is also the user of the selected functionality, so it is stored once, here); a Love request only if it also carries Self-discovery data (not the case today; the builder accepts an optional `self` person so a future link needs no schema change) |
| `user` | the user of the selected functionality | Love request: person A. Not stored for a Self request (it would duplicate `self`) |
| `loved` | the loved one | Love request: person B |

(This deliberately differs from "fill both blocks for Self": storing the same person twice adds personal data and no information.)

## Decision

1. **One MySQL database, two tables**, a request table and a person table (normalised). The flat alternative (one wide row with three 8-column blocks, most of them NULL) was rejected: 24 columns, a block that is always empty today, and erasure by person is harder.
2. Tables: `magic_audit` (one row per result) and `magic_audit_person` (0..3 rows per result, `role` = `self|user|loved`, `ON DELETE CASCADE`). InnoDB, utf8mb4.
3. **Building the record and the YAML are pure** (`src/Audit/AuditRecord.php`, `src/Audit/Yaml.php`). **All I/O is in `src/Db/`** (`Config`, `Connection`, `AuditLog`). Hook: `public/index.php`, after the template has been rendered, only when `$view !== null` (not for the chooser, validation errors or notes-only pages).
4. **Failure isolation**: config missing, PDO extension missing, connect error (2 s timeout), SQL error, oversize record: caught, a single `error_log` line without any request data, page unaffected. The write happens after the response is flushed (`fastcgi_finish_request()` when available, else `flush()`), with `ignore_user_abort(true)` and `set_time_limit(10)`.
5. **No IP, no user agent, no URL, no cookies, no session id** are stored. `noindex` / `no-store` on result pages stays.
6. **Privacy consequences are accepted explicitly** (section "Privacy").
7. Time: `created_at` is a UTC `DATETIME` filled by MySQL with `UTC_TIMESTAMP()` (independent of session/server time zone). `on_date` is the reading date used by the builders (`$today`, UTC date, possibly the `on=` override), because biorhythms and tarot in the response depend on it.

## Design

### Files to add

```
migrations/001_create_magic_audit.sql
config.php.example                  tracked template; real config.php is gitignored (already in .gitignore)
scripts/lib/dbcred.sh               sourced helper: load_db_credentials (sets shell vars, never prints)
scripts/make-config.sh              writes config.php from ~/.password (mode 600), prints only "config.php written"
scripts/db-migrate.sh               applies migrations/*.sql with the mysql client
scripts/db-purge.sh                 deletes audit rows older than N days (retention)
src/Audit/Yaml.php                  pure: Yaml::dump(array): string
src/Audit/AuditRecord.php           pure: fromSelf / fromLove -> record array (+ response_yaml)
src/Db/Config.php                   I/O: Config::load(string $path): ?array
src/Db/Connection.php               I/O: Connection::open(array $cfg): \PDO (throws)
src/Db/AuditLog.php                 I/O: AuditLog::tryWrite(?string $configPath, array $record): bool (never throws)
tests/cases/audit.php               required by tests/run.php
```

### Files to change

- `public/index.php`: after `require templates/home.php`, if `$view !== null`: build the record and call `AuditLog::tryWrite` (see "Hook"). Reads the config path from `getenv('ARCANA_CONFIG') ?: ARCANA_ROOT.'/config.php'` (the env override exists for tests).
- `templates/home.php`: a static privacy notice in the footer (text below; escaped via `e()` if it uses variables, otherwise literal).
- `scripts/deploy.sh`: also upload `config.php` when its content changed (see "Deploy").
- `.gitignore`: add `.deploy-config-hash`. (`config.php` is already there.)
- `.deploy.local.example`: add `DB_PASSWORD_SECTION=` with a generic comment ("name of the database section in ~/.password"; no real value).
- `.htaccess` (root): add a defence-in-depth deny for `config.php`, `config.php.example`, `migrations/` and `scripts/` (`<FilesMatch>`/`RedirectMatch 404` is fine; they are already rewritten into `public/` and answer 404, this is a second layer).
- `tests/cases/layering.php`: add `Audit` to the pure directories; add a new check for `src/Db` (see Test plan).
- `tests/run.php`: `require` `tests/cases/audit.php`.
- Docs (documenter, afterwards): `architecture.md` D2 row -> "superseded by ADR 0003", §3 diagram, §5, §7 (persistence row), §9; `code.md` map, data shapes, recipes; `roadmap.md` (Done: audit trail; Ideas: retention cron, private mode); `README.md` (setup: config, migrate).

### Schema (`migrations/001_create_magic_audit.sql`)

Idempotent, plain SQL, no data in the file.

```sql
CREATE TABLE IF NOT EXISTS magic_audit (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at     DATETIME NOT NULL,              -- UTC, set with UTC_TIMESTAMP()
  functionality  VARCHAR(16) NOT NULL,           -- 'self' | 'love'
  on_date        DATE NOT NULL,                  -- reading date ("today" or on=)
  format_version TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- version of the YAML layout
  response_yaml  MEDIUMTEXT NOT NULL,            -- UTF-8 YAML, capped at 64 KiB by the app
  PRIMARY KEY (id),
  KEY idx_magic_audit_created_at (created_at),
  KEY idx_magic_audit_functionality (functionality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS magic_audit_person (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  audit_id    BIGINT UNSIGNED NOT NULL,
  role        VARCHAR(8) NOT NULL,               -- 'self' | 'user' | 'loved'
  name        VARCHAR(40) NOT NULL,              -- characters, same limit as Request
  birth_date  DATE NULL,                         -- NULL when not given (loved one)
  birth_time  TIME NULL,                         -- NULL when not given/used
  place_label VARCHAR(255) NULL,                 -- Geocoder::label() text as resolved
  lat         DECIMAL(8,5) NULL,
  lon         DECIMAL(8,5) NULL,
  tz          VARCHAR(64) NULL,                  -- IANA zone
  PRIMARY KEY (id),
  UNIQUE KEY uq_magic_audit_person_role (audit_id, role),
  KEY idx_magic_audit_person_name (name),
  CONSTRAINT fk_magic_audit_person_audit FOREIGN KEY (audit_id)
    REFERENCES magic_audit (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Notes: the YAML is text, so `MEDIUMTEXT` (utf8mb4) rather than a binary blob (readable in any client, correct charset); the 64 KiB app cap keeps rows small. The `name` index exists for the erasure process. No extra "app version" column (the repo has no version constant; `format_version` covers the one thing that matters for reading old rows). No request id (the auto-increment id is it).

### Pure record builder (`Arcana\Audit\AuditRecord`)

```php
AuditRecord::fromSelf(array $input, array $view, string $today): array
AuditRecord::fromLove(array $a, array $b, array $view, string $today, ?array $self = null): array
// $input = Request::parse()['input']; $a/$b = Request::parseLove() persons; $view = SelfReading|LoveReading::build()
```

Record shape (the only input of `AuditLog`):

```php
[
  'functionality' => 'self'|'love',
  'on_date'       => 'YYYY-MM-DD',
  'format_version'=> 1,
  'persons'       => list<[
      'role' => 'self'|'user'|'loved', 'name' => string,
      'birth_date' => ?'YYYY-MM-DD', 'birth_time' => ?'HH:MM:SS',
      'place_label' => ?string, 'lat' => ?float, 'lon' => ?float, 'tz' => ?string ]>,
  'response_yaml' => string,    // Yaml::dump of the response summary, <= 65536 bytes
]
```

Rules: `birth_time` is stored only if the request actually used it (Love person B: time without date+place is ignored by `Request`, so it is NULL). Names are the already-validated 1-40 character strings. `place_label` is capped at 255 characters. Lat/lon rounded to 5 decimals.

The YAML is a **summary**, not the full view-model (no curves, no copy text, no long keyword lists). Keys are code constants, all `[a-z][a-z0-9_]*`.

Self (`format_version` 1):

```yaml
functionality: self
on_date: "2026-10-09"
chart:
  utc: "1879-03-14 10:50"
  polar: false
  sun: {sign: Pisces, degree: 23, minute: 30}     # emitted in block style, shown inline here
  ascendant: ...
  moon: ...
  planets_supported: true
  planets:
    mercury: {sign: ..., degree: .., minute: .., retrograde: false}   # one entry per body present
  north_node: {sign: ..., degree: .., minute: ..}
affinity:
  most_affine: [Sign, Sign, Sign]       # sign names, in rank order
  soulmate: Sign
biorhythm:                                # today's rounded values
  physical: 12
  emotional: -40
  intellectual: 77
notes: []                                 # view notes, strings
```

Love:

```yaml
functionality: love
on_date: "2026-10-09"
name_affinity:
  percent: 48
  counts: {a: 4, m: 0, o: 2, r: 2, e: 3}
  chain: [40223, 4245, 669, 135, 48]
charts:
  a: {sun: Sign, moon: Sign, ascendant: Sign}   # null/absent keys when the body is missing; approx flags as sun_approx / moon_approx
  b: {...}
common:
  score: 60                                # null when not comparable
synchrony:
  overall: 51.2                            # null when B has no date
  band: complementary
tarot:
  - {date: "2026-10-09", card: The Lovers, reversed: false}
  - ...
notes: []
```

(The implementer may group keys differently as long as they are deterministic, `[a-z0-9_]`, and the tests below pin the layout. Everything is derived from the view-models; sign names come from `$pos['name']`.)

If the dumped YAML exceeds 65536 bytes (not expected: a few KB), the builder replaces it by a minimal document `functionality`, `on_date`, `truncated: true` so the row is still written and never partially cut inside a multibyte character.

### Pure YAML emitter (`Arcana\Audit\Yaml`)

`Yaml::dump(array $data): string` — block style, 2-space indent, `\n` line endings, trailing newline, deterministic (insertion order kept; no sorting). Supported values: `null`, `bool`, `int`, `float`, `string`, lists, maps. Anything else throws `InvalidArgumentException` (programmer error, caught by `tryWrite`).

- **Keys**: must match `^[a-z][a-z0-9_]*$`, else exception. User data therefore can never become a key.
- **Scalars**: `null` -> `null`, bools `true`/`false`, ints as is, floats rounded to 4 decimals, trailing zeros removed, `-0` -> `0`, NaN/INF -> `null`.
- **Strings**: emitted plain only if they match `^[A-Za-z][A-Za-z0-9_ ./()-]*$`, do not end with a space, and are not a YAML-ish reserved word (case-insensitive `null true false yes no on off y n ~`). Everything else (empty string, digits-first, colons, `#`, quotes, leading `-`/`?`/`&`/`*`/`!`/`|`/`>`/`%`/`@`/backtick, brackets/braces, commas, any non-ASCII, any control character) is **double-quoted**: `\\`, `\"`, `\n`, `\r`, `\t`, `\0`, other C0 controls and DEL as `\xNN`, U+0085/U+2028/U+2029 as `\uNNNN`; other Unicode is kept as UTF-8. Strings that are not valid UTF-8 are emitted as `"<invalid utf-8>"`.
- Multi-line values never use block scalars (`|`/`>`), so a value can never add a key or document marker; an injection-looking name such as `x\nresponse: {admin: true}` becomes a single quoted line.
- **Collections**: lists of scalars in flow style `[a, b]` only when every item is a scalar and short; otherwise one `- ` item per line. Empty list `[]`, empty map `{}`.

No secrets can enter the response: the builders read only view-model fields (no config, no request headers).

### Database layer (I/O, `src/Db/`)

```php
Config::load(string $path): ?array
// null when the file is missing/unreadable or does not return an array with non-empty string host,name,user and a string password; port defaults to 3306 (int 1-65535). No exceptions, no output.

Connection::open(array $cfg): \PDO
// DSN "mysql:host=..;port=..;dbname=..;charset=utf8mb4"; options: ATTR_ERRMODE=EXCEPTION, ATTR_EMULATE_PREPARES=false, ATTR_TIMEOUT=2 (connect), PDO::MYSQL_ATTR_READ_TIMEOUT=3 if defined, ATTR_PERSISTENT=false. Throws on failure (callers catch).

AuditLog::tryWrite(?string $configPath, array $record): bool
// never throws. Steps: Config::load -> Connection::open -> beginTransaction -> prepared INSERT into magic_audit (created_at = UTC_TIMESTAMP()) -> lastInsertId -> prepared INSERT per person -> commit. On any Throwable: rollBack if active, error_log('audit: write failed ' . get_class($e) . ' ' . $e->getCode()) — class name and code only; the message is never logged because PDO connect errors can contain host and user names. Returns true/false. Missing config logs nothing (an unconfigured dev machine is normal).
```

All SQL is a fixed string with `?` placeholders (`execute([...])`); no string interpolation of any value, no `query()`/`exec()` with variables. The only `exec` allowed is none.

### Hook (`public/index.php`, after `require templates/home.php`)

```php
if ($view !== null) {
    ignore_user_abort(true);
    set_time_limit(10);
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); } else { @flush(); }
    $record = $mode === 'self'
        ? AuditRecord::fromSelf($in, $view, $today)
        : AuditRecord::fromLove($parsed['a'], $parsed['b'], $view, $today);
    AuditLog::tryWrite(getenv('ARCANA_CONFIG') ?: ARCANA_ROOT . '/config.php', $record);
}
```

The record build is itself wrapped in the same try/catch (a small `try { ... } catch (\Throwable $e) { error_log('audit: build failed ' . get_class($e)); }` in the index) so a bug in the pure builder cannot surface after the page.

### Config and credentials

`config.php.example` (tracked):

```php
<?php
// Copy to config.php (gitignored). Generated by scripts/make-config.sh from the database section of ~/.password.
return ['host' => 'localhost', 'port' => 3306, 'name' => 'database', 'user' => 'user', 'password' => 'change-me'];
```

`config.php` lives in the project root, outside `public/`. When the web directory is `public/` it is not reachable at all; when the web directory is the project root the root `.htaccess` rewrites every URL into `public/` (so `/config.php` answers 404) plus the explicit deny added by this ADR. It is a PHP file returning an array, so even a misconfigured server would execute it rather than show it.

`scripts/lib/dbcred.sh` + `scripts/make-config.sh`:

- Section name comes from `DB_PASSWORD_SECTION` in the gitignored `.deploy.local`. The repo and the docs say only "the database section of `~/.password`".
- The parser of `~/.password` is the same one the `sftp-upload` skill uses; the implementer reuses its logic/format after inspecting the **structure** of the file (key names only, never values; never `cat` it, no `set -x`, no `echo` of any variable). Mapping: host, port (default 3306), database name, user, password.
- `make-config.sh`: `umask 077`, writes to a temp file in the project root, escapes `\` and `'` for the PHP single-quoted strings, `mv` to `config.php`, `chmod 600`, prints only `config.php written`. Fails with a generic message if the section or a field is missing (the message never contains values, nor the section name).

### Migrations (`scripts/db-migrate.sh`)

- Sources `.deploy.local` (for the section name) and `scripts/lib/dbcred.sh`; creates `defaults=$(mktemp)` under `umask 077` with `trap 'rm -f "$defaults"' EXIT`, containing `[client]` with `host`, `port`, `user`, `password` (values quoted/escaped for the option-file syntax), then runs `mysql --defaults-extra-file="$defaults" --default-character-set=utf8mb4 "$DB_NAME" < migrations/NNN_*.sql` for every file in lexical order. Alternative accepted by the same script: `MYSQL_PWD` exported only for the mysql process; the option file is preferred because env vars are visible to the same user's `ps -e`-style tools on some systems. The password is never on the command line, never echoed.
- Idempotent: every migration uses `CREATE TABLE IF NOT EXISTS`; later migrations that alter tables must be written idempotently too (documented in the script header). No migrations bookkeeping table (YAGNI; revisit with the first non-idempotent change).
- Output: `applied migrations/001_create_magic_audit.sql` per file; mysql errors are shown but run with stderr filtered through a guard that drops lines containing the password or user (simplest: do not enable `--verbose`; mysql client does not echo the password).
- Risk: the hosting MySQL server may refuse remote clients. Then the fallback is to run the script on a machine allowed by the panel (or to paste the SQL in the panel's SQL tool). The script is unchanged either way.

`scripts/db-purge.sh [--days N]` (default 90): same credential handling, runs `DELETE FROM magic_audit WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? DAY` with N validated as an integer in the shell (`[[ $N =~ ^[0-9]+$ ]]`) and passed via `mysql -e` built only from that checked integer; persons disappear through the cascade.

### Deploy (`scripts/deploy.sh`)

After the tracked-files loop and only when not `--dry-run`: if `config.php` exists, compute `sha256sum config.php`, compare to the gitignored `.deploy-config-hash`; if different, upload it with the same uploader call used for other files (`--dir "${DEPLOY_DIR%/}/"`, `--force`, stdout to `/dev/null`), print only `uploaded config.php (contents not shown)`, then write the new hash. In `--dry-run`, print `would upload config.php` when the hash differs. If `config.php` is absent, print nothing (the page works without it). If the uploader supports setting a file mode, request 600; otherwise the file is private by location (outside `public/`) and by the deny rules. The script never `cat`s or echoes the file; `--all` also forces the config upload.

### Privacy (the consequences of superseding D2 and §5)

1. **What is stored**: functionality, UTC timestamp, reading date, names, birth date/time/place of the user and (Love) of the loved one, and the YAML summary of the result. **Not stored**: IP address, user agent, referrer, cookies, full URL/query string, the typed city text beyond the resolved label. An IP would add identifiability without being needed for an audit of results; revisit only with a concrete abuse/rate-limiting need and a new ADR.
2. **Third parties**: the loved person never consented and is a data subject too. Mitigation by minimisation: the form already makes everything but the name optional for them, and only what was provided is stored; nothing is inferred or enriched; no email or free text. Names are stored as typed (the spec requires it); a later hardening could store a hash instead of the loved one's name, at the cost of the audit value.
3. **Who can read**: the database owner (hosting panel, MySQL client, backups made by the host). Access from the application only: the DB credentials are in the gitignored `config.php` (mode 600, outside `public/`) and in `~/.password`; there is no page, API or log that outputs audit rows. The DB user should be used by this project only (a DB per project is the shared-hosting norm).
4. **Retention (suggested default)**: 90 days, enforced by `scripts/db-purge.sh --days 90` run manually or from a cron job on a trusted machine; the owner may choose a different period but must state it in the notice. Deleting on demand (erasure): `DELETE FROM magic_audit WHERE id IN (SELECT audit_id FROM magic_audit_person WHERE name = ?)` (documented in the README/ops notes, run by the owner with a parameterised statement); cascade removes the person rows. Backups made by the host age out on the host's schedule: say so in the notice.
5. **Notice on the page** (static footer text in `templates/home.php`, English like the rest): "To keep an audit record, Arcana stores the details you enter (names, birth date, time and place) and a summary of your result for 90 days. It does not store your IP address. To have your data deleted, contact the site owner." The Love form already warns about names in the URL; add: "The details of the person you love are stored too, only what you enter." The notice text must match the retention actually configured. (No contact address is invented here; the owner supplies it.)
6. **Search engines/caches**: `X-Robots-Tag: noindex`, `<meta name="robots">` and `Cache-Control: private, no-store` stay on result pages.
7. **Legal**: GDPR-style obligations (lawful basis, information, erasure, minimisation, storage limitation) apply if EU visitors use the site; the owner is the controller. This ADR provides the technical means (notice, minimisation, retention purge, erasure query) and does not constitute legal advice; the owner should decide the lawful basis (e.g. legitimate interest for an audit trail) before go-live.
8. **Sensitivity in the YAML**: the response summary repeats signs and numbers that follow from the stored birth data; it contains no extra personal data beyond that and no secrets.

### Other risks handled in the design

- **SQL injection**: only prepared statements with bound parameters, emulation off; the layering test forbids interpolation in SQL strings in `src/Db`.
- **Stored injection into YAML**: user text is only ever a quoted scalar value, never a key or a block scalar (see emitter rules); the YAML is never parsed by the app.
- **Size**: names <= 40 chars, label <= 255, YAML <= 64 KiB with the `truncated` fallback; no unbounded fields. Request volume is not rate-limited by this ADR (a bot can fill the table; the retention purge bounds it; rate limiting needs an IP and is out of scope).
- **Latency/availability**: flush-then-write, 2 s connect timeout, 3 s read timeout where supported, 10 s script limit; DB down costs the visitor nothing on `fastcgi_finish_request` hosts and at most about 2 s of connection hold-open on hosts that cannot detach (CGI), which the reviewer should check on the server.
- **Time zone**: `created_at` UTC via `UTC_TIMESTAMP()`; `on_date` is the UTC reading date or `on=`; both documented as UTC.
- **Reproducible requests (`on=`) and test traffic** are stored like any other; no special casing.
- **Secrets in the repo**: `config.php` gitignored (already), `.deploy-config-hash` gitignored, `DB_PASSWORD_SECTION` only in `.deploy.local`, and the section name is not written in any tracked file, doc, script or test.

## Existing decisions touched

- **D2 (no database / no personal data at rest): superseded** by this ADR. Update the D2 row to "MySQL via PDO, used only for the audit trail (ADR 0003); no accounts, no saved charts".
- **`architecture.md` §5, "not stored" statement: superseded** (replace by the retention/notice text above). The "names in the URL", `noindex`, `no-store` and `.htaccess` points remain.
- D1 (no Composer, shared hosting): kept (PDO MySQL is a standard shared-hosting extension; its absence is handled as a DB failure). D5, D7 (pure core: kept, the new pure code lives in `src/Audit`, I/O in `src/Db`), D8 (page works without JS and, now, without a database): kept. `architecture.md` §7 "Persistence" row and §9 step 4 get updated. No other decision is broken.

## Alternatives considered

- **Flat wide table** (`self_*`, `user_*`, `loved_*` columns): simplest SQL, but 24 columns, always-NULL block, harder erasure and indexing by name. Rejected for the two-table design.
- **Store the full JSON/serialized view-model**: bigger, repeats copy text, and changes whenever copy changes. A YAML summary with a `format_version` is smaller and stable.
- **PHP `yaml` extension / Symfony YAML**: extension often absent, no Composer. Hand-written emitter with a tiny, strictly quoted subset.
- **JSON column** instead of YAML: simpler and safer, but the owner asked for YAML; the format can be switched by bumping `format_version`.
- **Logging to a file** instead of MySQL: no DB needed but hard to query/purge, and the owner asked for MySQL.
- **Store IP / user agent**: no stated need; rejected (data minimisation).
- **Synchronous write before output**: simpler but a slow/down DB would delay or break the page; rejected.
- **Write via `register_shutdown_function`**: similar effect; explicit flush-then-write in the controller is clearer and testable.

## Risks

- Shared-hosting MySQL may not accept remote connections for `scripts/db-migrate.sh`; fallback described above.
- `fastcgi_finish_request` is absent under plain CGI; the visitor may then wait for the write (bounded to a few seconds). Check on the server after the first deploy.
- The owner now holds third parties' personal data; the notice, retention purge and erasure query must be operated, not just shipped.
- A hand-written YAML emitter is easy to get subtly wrong; mitigated by a deliberately small grammar and golden tests.
- Credentials handling in shell scripts: `set +x`, no echo, `umask 077`, temp file removed by `trap`; the reviewer must check no value reaches stdout/stderr/logs.
- Config uploaded by `deploy.sh` leaves a copy on the server and in the host's backups; accepted.

## Test plan

All in `tests/cases/audit.php` (dependency-free, no DB needed unless stated).

**Yaml (golden strings, exact equality):**
- `Yaml::dump(['a' => 1, 'b' => true, 'c' => null, 'd' => 1.5, 'e' => 2.0, 'f' => -0.0])` -> `"a: 1\nb: true\nc: null\nd: 1.5\ne: 2\nf: 0\n"`; NaN/INF -> `null`; 0.123456 -> `0.1235`.
- Plain: `Pisces`, `Rome, Italy` is NOT plain (comma) -> `"Rome, Italy"`; `Mary Ann` plain.
- Quoting: `a: b` -> `"a: b"`; `# c` -> `"# c"`; `- x` -> `"- x"`; `yes`, `No`, `null`, `~`, `123`, `1e3`, `''` -> all quoted (`""` for empty); `it's "x"` -> `"it's \"x\""`; backslash `a\b` -> `"a\\b"`; trailing space `x ` quoted.
- Newlines/controls: `l1\nl2` -> `"l1\nl2"`; `\r\n\t` escaped; `"\x00"` -> `"\0"`; `\x1b` -> `"\x1B"`; U+2028 -> `" "`.
- Unicode: `Zoë`, `José Ñandú`, `日本語`, `🙂` -> double-quoted, raw UTF-8 preserved; invalid UTF-8 `"\xff"` -> `"<invalid utf-8>"`.
- Injection: name `x\nresponse: {admin: true}\n---\n!!php/object` dumped under key `name` yields exactly one line starting with `name: "` and no other line starting with a key; the output round-trips through a tiny test-local parser of the quoted scalar (or, if `yaml_parse` exists, through it, skipped otherwise) to the original string.
- Keys: `['Bad Key' => 1]`, `['a:b' => 1]`, `['' => 1]` throw `InvalidArgumentException`.
- Structure: nested map + list of maps + empty list/map render with 2-space indentation, deterministic (same input twice -> identical bytes); object or resource value throws.

**AuditRecord (pure, fixed inputs):**
- Self, Albert Einstein, 1879-03-14 11:30, Ulm (48.4, 10.0, `Europe/Berlin`), today `2026-10-09`: record `functionality` `self`, exactly one person with role `self`, `birth_date` `1879-03-14`, `birth_time` `11:30:00`, `lat` 48.4, `tz` `Europe/Berlin`; YAML contains `sun:` with sign `Pisces`, `ascendant` `Sagittarius`, `moon` `Cancer` (existing reference chart of the suite), `on_date: "2026-10-09"`; `response_yaml` ends with `\n` and is <= 65536 bytes; same input twice -> identical record.
- Love, A Andrea Lorenzani (date/time/place given) and B Silvia Pellico (name only), today `2026-10-09`: roles `user` and `loved`; B has `birth_date`, `birth_time`, `place_label`, `lat`, `lon`, `tz` all `null`; YAML has `percent: 48` and `chain: [40223, 4245, 669, 135, 48]` (ADR 0002 reference); no `self` person; with the optional `$self` argument a third `self` row appears.
- B with date+city but no time: `birth_time` null. B time without date: time ignored in `Request`, record has null.
- Oversize: a synthetic view with a 70 KiB note string yields the `truncated: true` document, valid UTF-8.
- Names containing quotes, `%`, `;`, `'--`, emoji pass through the record unchanged in `persons` (escaping is the DB layer's job via binding) and are quoted in the YAML.
- Records contain no key named like `password`, `host`, `ip` (guard test).

**Failure isolation (no DB required):**
- `Config::load` returns null for a missing path, a directory, a file returning a non-array, and an array missing `password`; no output and no warnings (run with `error_reporting(E_ALL)` and an error handler that fails the check).
- `AuditLog::tryWrite('/nonexistent/config.php', $record)` returns false, does not throw, writes nothing to the error log.
- `tryWrite` with a temp config pointing to `127.0.0.1` on a closed port returns false in < 4 s, with an `error_log` file (set via `ini_set('error_log', tmp)`) containing `audit: write failed` and **not** containing the record's name, host or user (assert absent).
- Page renders without the database: run `php -r` in a subprocess that sets `$_GET` to the Einstein Self query (with `lat/lon/tz` supplied so no network is needed) and `ARCANA_CONFIG=/nonexistent`, requires `public/index.php` with output buffering, and asserts exit code 0, output contains `Pisces`, and the notice text "does not store your IP address". Same for a Love query containing `Silvia Pellico`. Chooser and validation-error queries produce no audit attempt (assert via a closed-port config plus an empty `error_log` file: nothing logged because nothing was attempted).

**Layering:** add `Audit` to the pure directories in `tests/cases/layering.php` (no echo, `$_GET`, file/curl, clock). New check for `src/Db/*.php`: no `$_GET`/`$_POST`, no `echo`/`print`, no `query(`/`exec(` calls, every `prepare(` argument is a string literal without `$` interpolation, and `error_log(` arguments never contain `getMessage`. Scripts check: `scripts/*.sh` and `scripts/lib/*.sh` contain no `set -x`, no `echo`/`printf` of variables whose name contains `PASS`, `PWD`, `CRED`, `HOST`, and no `cat` of `~/.password` (grep test); no tracked file contains the private section name (the test reads it from `.deploy.local` when present and greps `git ls-files` content, skipped otherwise).

**Optional integration (skipped when `config.php` is absent):** with a real `config.php` against a test/throwaway database, run the migration twice (idempotent), call `tryWrite` with the Einstein and Andrea/Silvia records, select the rows back through PDO and assert: `created_at` within 5 s of `UTC_TIMESTAMP()`, one `magic_audit` row, persons `1` / `2`, `response_yaml` byte-equal to the record, names with quotes and emoji round-trip exactly (utf8mb4), a name of 41 characters is rejected by the column (strict mode) and returns false, deleting the audit row cascades to persons. The test deletes its own rows (by id) afterwards and never prints config values. It must refuse to run unless an env flag `ARCANA_DB_TEST=1` is set, so a developer pointing `config.php` at production cannot add rows by accident.

**Manual:** `php -S localhost:8081 -t public` without `config.php` (pages work, no error output); with `config.php` after `scripts/db-migrate.sh` (rows appear for Self and Love only; none for the chooser or an invalid date); `scripts/deploy.sh --dry-run` prints `would upload config.php` only when the hash changed and never prints its content; the live site responds 404 for `/config.php`, `/migrations/001_create_magic_audit.sql` and `/scripts/deploy.sh`; the footer notice is visible and matches the retention.

## Implementation notes

Deviations from the design above:

- The Self form has no name field, so the Self person's `name` is stored as `''`.
- The Self time defaults to 12:00 when no time is given, and that value is what is stored as `birth_time`.
- Empty arrays are emitted as `[]` by the YAML emitter.
- The Love YAML layout is the one built in `AuditRecord::fromLove` (see `docs/code.md`): `name_affinity`, `charts.a|b` with `sun_approx`/`moon_approx`, `common.score`, `synchrony`, `tarot`, `notes`.
- The page notice no longer states "90 days": there is no automatic purge. `scripts/db-purge.sh --days N` is manual and nothing schedules it; the footer says the data can be removed on request.
- Errors of the `mysql` client in the scripts are sanitised to `mysql error <code> (<SQLSTATE>)` (stderr is never shown, as it can contain user and client address).

Operational status: the tables have **not** been created in the real database. The database server refused the connection (access denied) from the development machine. `scripts/db-migrate.sh` must still be run from an allowed host, or `migrations/001_create_magic_audit.sql` pasted into the hosting panel's SQL tool. Until then the app runs normally and audit writes fail silently (logged as class and code only). Also open: the uploaded `config.php` is not mode 600 on the server.

# 0001 — Rewrite in PHP for shared hosting

- Status: accepted
- Date: 2026-10-08

## Context
v0.1 was a static JavaScript app with a Node dev server. The project must be deployed on Apache shared hosting, so it must be PHP, and any database must be MySQL.

## Decision
Port the core 1:1 to PHP 8.1+ (`Magic\Astro\*`, `Time\Zone`, `Chart`), server-render the page, move geocoding server-side with disk cache, keep JS only for autocomplete. No database yet (D2 in architecture.md).

## Alternatives considered
- Keep JS core, PHP only as host: two languages, little benefit, and server-side validation is wanted anyway.
- Add MySQL now for city cache: adds setup and privacy surface for no user-visible gain; disk cache suffices.

## Risks
Outbound HTTP may be disabled on some hosts (fallback list covers major cities). `cache/` must be writable.

## Test plan
Astronomy reference values carried over from v0.1; added zone, request-validation and fallback tests (`php tests/run.php`, 18 checks).

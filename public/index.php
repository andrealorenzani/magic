<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Audit\AuditRecord;
use Magic\Consent;
use Magic\Db\AuditLog;
use Magic\Geo\Geocoder;
use Magic\Http;
use Magic\LoveReading;
use Magic\Request;
use Magic\SelfReading;
use Magic\Share\Qr;
use Magic\Share\ShareLink;

// The page differs by cookie (gate or result): never cache it.
header('Cache-Control: private, no-store');
header('Vary: Cookie');

// Only plain strings are accepted from the query string (no arrays).
$consented = Consent::given($_COOKIE);
// Without accepted Terms and Conditions nothing is processed: the page only shows the terms popup.
$q = $consented ? array_filter($_GET, 'is_string') : [];
$rawQuery = ltrim((string) ($_SERVER['QUERY_STRING'] ?? ''), '?');
$returnQuery = $consented ? '' : Consent::safeQuery($rawQuery);
$withdrawn = !$consented && ($_GET['withdrawn'] ?? null) === '1';
if ($withdrawn && $returnQuery === 'withdrawn=1') {
    $returnQuery = '';
}
$queryDropped = !$consented && !$withdrawn && $rawQuery !== '' && $returnQuery === '';
$noAudit = Request::noAudit($q);
$base = Http::basePath($_SERVER);
$consentAction = ($base === null ? '' : $base . '/') . 'consent.php';
$mode = Request::mode($q);
$today = Request::parseToday($q, gmdate('Y-m-d')); // the clock is read here, nowhere else

$field = static fn (string $key, string $default = ''): string => $q[$key] ?? $default;
$person = static fn (string $p, string $time): array => [
    'name' => $field($p . 'name'), 'date' => $field($p . 'date'), 'time' => $field($p . 'time', $time),
    'city' => $field($p . 'city'), 'lat' => $field($p . 'lat'), 'lon' => $field($p . 'lon'), 'tz' => $field($p . 'tz'),
];
$self = $person('', '12:00');
$love = ['a' => $person('a_', '12:00'), 'b' => $person('b_', '')];
$onOverride = isset($q['on']) && $q['on'] === $today ? $today : null;
$errors = [];
$notes = [];
$view = null;
$share = null;
$fixedDay = null;
$submitted = false;

if ($mode === 'self' && (isset($q['date']) || isset($q['city']))) {
    $submitted = true;
    $parsed = Request::parse($q, new Geocoder(MAGIC_ROOT . '/cache'));
    $errors = $parsed['errors'];
    $notes = $parsed['notes'];
    if ($parsed['input'] !== null) {
        $in = $parsed['input'];
        $view = SelfReading::build($in, $today);
        $notes = array_merge($notes, $view['notes']);
        $self = array_merge($self, ['city' => $in['city'], 'lat' => (string) $in['lat'], 'lon' => (string) $in['lon'], 'tz' => $in['tz']]);
    }
} elseif ($mode === 'love') {
    foreach (array_keys($q) as $key) {
        if (str_starts_with($key, 'a_') || str_starts_with($key, 'b_')) {
            $submitted = true;
            break;
        }
    }
    if ($submitted) {
        $parsed = Request::parseLove($q, new Geocoder(MAGIC_ROOT . '/cache'));
        $errors = $parsed['errors'];
        $notes = $parsed['notes'];
        if ($parsed['a'] !== null && $parsed['b'] !== null) {
            $tarotParam = Request::parseTarot($q);
            $view = LoveReading::build($parsed['a'], $parsed['b'], $today, $tarotParam['slots']);
            if ($tarotParam['invalid']) {
                $notes[] = 'The cards in this link were not valid, so a fresh reading is shown.';
            }
            $notes = array_merge($notes, $view['notes']);
            foreach (['a' => 'a_', 'b' => 'b_'] as $k => $p) {
                if ($parsed[$k]['place'] !== null) {
                    $pl = $parsed[$k]['place'];
                    $love[$k] = array_merge($love[$k], ['city' => $pl['city'], 'lat' => (string) $pl['lat'], 'lon' => (string) $pl['lon'], 'tz' => $pl['tz']]);
                }
            }
        }
    }
}

if ($view !== null) {
    // Share section: frozen link (also the QR) and a live link, built from the validated model only.
    $frozenQuery = $mode === 'self'
        ? ShareLink::self($in, $today)
        : ShareLink::love($parsed['a'], $parsed['b'], $view['tarot'], $today);
    $liveQuery = $mode === 'self' ? ShareLink::liveSelf($in) : ShareLink::liveLove($parsed['a'], $parsed['b']);
    $origin = Http::origin($_SERVER);
    $prefix = ($origin !== null && $base !== null) ? $origin . $base . '/?' : './?';
    $share = ['frozen' => $prefix . $frozenQuery, 'live' => $prefix . $liveQuery, 'qr' => null, 'tooLong' => false];
    if ($origin !== null && $base !== null) {
        $share['qr'] = Qr::encode($share['frozen']);
        $share['tooLong'] = $share['qr'] === null;
    }
    $realToday = gmdate('Y-m-d');
    $fixedDay = $today !== $realToday ? ['date' => $today, 'live' => './?' . $liveQuery] : null;
}

if ($submitted) {
    // Names and birth data are in the URL: keep results out of search engines and shared caches.
    header('X-Robots-Tag: noindex');
}

require MAGIC_ROOT . '/templates/home.php';

// Audit trail (ADR 0003): only for results; after the page is flushed; never allowed to break the page.
if ($view !== null && !$noAudit) {
    ignore_user_abort(true);
    set_time_limit(10);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @flush();
    }
    try {
        $record = $mode === 'self'
            ? AuditRecord::fromSelf($in, $view, $today)
            : AuditRecord::fromLove($parsed['a'], $parsed['b'], $view, $today);
        AuditLog::tryWrite(getenv('MAGIC_CONFIG') ?: MAGIC_ROOT . '/config.php', $record);
    } catch (\Throwable $e) {
        error_log('audit: build failed ' . get_class($e));
    }
}

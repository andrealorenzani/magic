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
use Magic\Share\ShareCode;
use Magic\Share\ShareLink;
use Magic\Time\Zone;

// The page differs by cookie (gate or result): never cache it.
header('Cache-Control: private, no-store');
header('Vary: Cookie');

// Only plain strings are accepted from the query string (no arrays).
$consented = Consent::given($_COOKIE);
// Without accepted Terms and Conditions nothing is processed: the page only shows the terms popup.
$q = $consented ? array_filter($_GET, 'is_string') : [];
$codeNote = null;
if (isset($q['c'])) {
    $decoded = ShareCode::decode($q['c']);
    unset($q['c']);
    if ($decoded === null) {
        $codeNote = 'The short code in this link is not valid, so it was ignored.';
    } else {
        $q = $decoded + $q;
    }
}
$hiddenState = Request::hiddenCode($q);
// The receiver's own nickname for a hidden person: local to this page view, never audited or shared.
$nickState = Request::nickname($q);
$nick = $nickState['nick'];
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
// Hidden details (a person shared with the visitor): loaded for the matching only, never shown.
$hidden = false;
$hiddenCode = null;
$hiddenAnonymous = false;
$pendingHidden = null;
if ($mode === 'self' && $hiddenState['code'] !== null) {
    $pendingHidden = ['code' => $hiddenState['code'], 'nick' => $nick];
}
if ($mode === 'love' && $hiddenState['person'] !== null) {
    foreach (array_keys($q) as $key) {
        if (str_starts_with($key, 'b_')) {
            unset($q[$key]);
        }
    }
    foreach ($hiddenState['person'] as $key => $value) {
        $q['b_' . $key] = $value;
    }
    $hidden = true;
    $hiddenCode = $hiddenState['code'];
    $hiddenAnonymous = ($hiddenState['person']['name'] ?? '') === '';
    if ($hiddenAnonymous) {
        $q['b_name'] = 'Match';
    }
}
unset($q['h'], $q['import'], $q['nick']);
$nowUnix = time(); // the clock is read here, nowhere else
$userTz = null;
$today = Request::resolveToday($q, $nowUnix, null);

$field = static fn (string $key, string $default = ''): string => $q[$key] ?? $default;
$person = static fn (string $p, string $time): array => [
    'name' => $field($p . 'name'), 'date' => $field($p . 'date'), 'time' => $field($p . 'time', $time),
    'city' => $field($p . 'city'), 'lat' => $field($p . 'lat'), 'lon' => $field($p . 'lon'), 'tz' => $field($p . 'tz'),
    'pos_city' => $field($p . 'pos_city'), 'pos_lat' => $field($p . 'pos_lat'), 'pos_lon' => $field($p . 'pos_lon'), 'pos_tz' => $field($p . 'pos_tz'),
];
$nowFields = static fn (?array $now): array => $now === null ? [] : [
    'pos_city' => $now['city'], 'pos_lat' => (string) $now['lat'], 'pos_lon' => (string) $now['lon'], 'pos_tz' => $now['tz'],
];
$self = $person('', '12:00');
$love = ['a' => $person('a_', '12:00'), 'b' => $hidden ? array_map(static fn (string $v): string => '', $person('b_', '')) : $person('b_', '')];
$errors = [];
$notes = [];
if (($mode === 'love' || $mode === 'self') && $hiddenState['invalid']) {
    $notes[] = 'The hidden details in this link are not valid, so they were ignored.';
}
if ($nickState['invalid'] && ($hidden || $pendingHidden !== null)) {
    $notes[] = 'The nickname was not valid, so it was ignored.';
}
$matchLabel = $nick ?? 'Your match';
$aKnown = false;
$loveLink = null;
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
        $userTz = $in['now']['tz'] ?? null;
        $today = Request::resolveToday($q, $nowUnix, $userTz);
        $view = SelfReading::build($in, $today, Request::dayBasis($q, $userTz));
        $notes = array_merge($notes, $view['notes']);
        $aKnown = true;
        $aQuery = ['mode' => 'love', 'a_name' => $in['name'] ?? 'Me', 'a_date' => sprintf('%04d-%02d-%02d', $in['year'], $in['month'], $in['day']),
            'a_time' => sprintf('%02d:%02d', $in['hour'], $in['minute']), 'a_city' => $in['city'], 'a_lat' => (string) $in['lat'], 'a_lon' => (string) $in['lon'], 'a_tz' => $in['tz']];
        if ($in['now'] !== null) {
            $aQuery += ['a_pos_city' => $in['now']['city'], 'a_pos_lat' => (string) $in['now']['lat'], 'a_pos_lon' => (string) $in['now']['lon'], 'a_pos_tz' => $in['now']['tz']];
        }
        if ($noAudit) {
            $aQuery['noaudit'] = '';
        }
        $loveLink = '?' . http_build_query($aQuery, '', '&', PHP_QUERY_RFC3986);
        $self = array_merge($self, $nowFields($in['now']), ['city' => $in['city'], 'lat' => (string) $in['lat'], 'lon' => (string) $in['lon'], 'tz' => $in['tz']]);
    }
} elseif ($mode === 'love') {
    foreach (array_keys($q) as $key) {
        if (str_starts_with($key, 'a_') || (!$hidden && str_starts_with($key, 'b_'))) {
            $submitted = true;
            break;
        }
    }
    if ($submitted) {
        $parsed = Request::parseLove($q, new Geocoder(MAGIC_ROOT . '/cache'));
        $errors = $parsed['errors'];
        $notes = array_merge($notes, $parsed['notes']);
        if ($hidden) {
            // Nothing about the shared person may reach the page, not even inside a message.
            $mask = static fn (string $m): string => str_starts_with($m, 'Loved person: ') ? 'Some of the shared details could not be used.' : $m;
            $errors = array_values(array_unique(array_map($mask, $errors)));
            $notes = array_values(array_unique(array_map($mask, $notes)));
            if ($parsed['b'] !== null) {
                $parsed['b'] = ['label' => $matchLabel, 'anonymous' => $hiddenAnonymous] + $parsed['b'];
                if ($hiddenAnonymous) {
                    $parsed['b']['name'] = '';
                }
            }
        }
        $aKnown = $parsed['a'] !== null;
        if ($parsed['a'] !== null && $parsed['b'] !== null) {
            $tarotParam = Request::parseTarot($q);
            $userTz = $parsed['a']['now']['tz'] ?? null;
            $today = Request::resolveToday($q, $nowUnix, $userTz);
            $view = LoveReading::build($parsed['a'], $parsed['b'], $today, $tarotParam['slots'], Request::dayBasis($q, $userTz));
            if ($tarotParam['invalid']) {
                $notes[] = 'The cards in this link were not valid, so a fresh reading is shown.';
            }
            $notes = array_merge($notes, $view['notes']);
            foreach (['a' => 'a_', 'b' => 'b_'] as $k => $p) {
                if ($hidden && $k === 'b') {
                    continue;
                }
                $love[$k] = array_merge($love[$k], $nowFields($parsed[$k]['now']));
                if ($parsed[$k]['place'] !== null) {
                    $pl = $parsed[$k]['place'];
                    $love[$k] = array_merge($love[$k], ['city' => $pl['city'], 'lat' => (string) $pl['lat'], 'lon' => (string) $pl['lon'], 'tz' => $pl['tz']]);
                }
            }
        }
    }
}

$onOverride = isset($q['on']) && $q['on'] === $today ? $today : null;

if ($view !== null && !$hidden) {
    // Live link (today's values, same people) for the fixed-day note; a Share section exists only for typed Soul Affinity.
    $longLive = $mode === 'self' ? ShareLink::liveSelf($in) : ShareLink::liveLove($parsed['a'], $parsed['b']);
    try {
        $liveQuery = ShareLink::codeQuery($mode === 'self'
            ? ShareCode::encodeSelf($in, $today, false)
            : ShareCode::encodeLove($parsed['a'], $parsed['b'], null, $today, false));
    } catch (\Throwable $e) {
        $liveQuery = $longLive;
    }
    if ($mode === 'love') {
        $longFrozen = ShareLink::love($parsed['a'], $parsed['b'], $view['tarot'], $today);
        try {
            $frozenQuery = ShareLink::codeQuery(ShareCode::encodeLove($parsed['a'], $parsed['b'], $view['tarot'], $today, true));
        } catch (\Throwable $e) {
            $frozenQuery = $longFrozen;
        }
        $origin = Http::origin($_SERVER);
        $prefix = ($origin !== null && $base !== null) ? $origin . $base . '/?' : './?';
        $share = ['frozen' => $prefix . $frozenQuery, 'live' => $prefix . $liveQuery, 'qr' => null, 'tooLong' => false];
        if ($origin !== null && $base !== null) {
            $share['qr'] = Qr::encode($share['frozen']);
            $share['tooLong'] = $share['qr'] === null;
        }
    }
    $realToday = Zone::dateAt($nowUnix, $userTz ?? 'UTC');
    $fixedDay = $today !== $realToday ? ['date' => $today, 'live' => './?' . $liveQuery] : null;
} elseif ($view !== null) {
    // A result with hidden details has no share section and no live link.
    $fixedDay = $today !== Zone::dateAt($nowUnix, $userTz ?? 'UTC') ? ['date' => $today, 'live' => null] : null;
}

if ($codeNote !== null) {
    $notes[] = $codeNote;
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

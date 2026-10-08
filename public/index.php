<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Arcana\Geo\Geocoder;
use Arcana\LoveReading;
use Arcana\Request;
use Arcana\SelfReading;

// Only plain strings are accepted from the query string (no arrays).
$q = array_filter($_GET, 'is_string');
$mode = Request::mode($q);
$today = Request::parseToday($q, gmdate('Y-m-d')); // the clock is read here, nowhere else

$field = static fn (string $key, string $default = ''): string => $q[$key] ?? $default;
$person = static fn (string $p, string $time): array => [
    'name' => $field($p . 'name'), 'date' => $field($p . 'date'), 'time' => $field($p . 'time', $time),
    'city' => $field($p . 'city'), 'lat' => $field($p . 'lat'), 'lon' => $field($p . 'lon'), 'tz' => $field($p . 'tz'),
];
$self = $person('', '12:00');
$love = ['a' => $person('a_', '12:00'), 'b' => $person('b_', '')];
$errors = [];
$notes = [];
$view = null;
$submitted = false;

if ($mode === 'self' && (isset($q['date']) || isset($q['city']))) {
    $submitted = true;
    $parsed = Request::parse($q, new Geocoder(ARCANA_ROOT . '/cache'));
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
        $parsed = Request::parseLove($q, new Geocoder(ARCANA_ROOT . '/cache'));
        $errors = $parsed['errors'];
        $notes = $parsed['notes'];
        if ($parsed['a'] !== null && $parsed['b'] !== null) {
            $view = LoveReading::build($parsed['a'], $parsed['b'], $today);
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

if ($submitted) {
    // Names and birth data are in the URL: keep results out of search engines and shared caches.
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');
}

require ARCANA_ROOT . '/templates/home.php';

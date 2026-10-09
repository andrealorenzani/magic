<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Consent;
use Magic\Geo\Geocoder;
use Magic\Http;
use Magic\Request;
use Magic\Share\Qr;
use Magic\Share\ShareCode;
use Magic\Time\Zone;

// Builds the link and QR path of a hidden-details code from the sender's own details.
// Nothing is stored, logged or recorded here, and the input is never echoed back.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');

$reply = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $reply(405, ['ok' => false, 'error' => 'method']);
}
if (!Consent::given($_COOKIE)) {
    $reply(403, ['ok' => false, 'error' => 'terms']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    $reply(413, ['ok' => false, 'error' => 'data']);
}

$in = array_filter($_POST, 'is_string');
$str = static function (string $k) use (&$in): string {
    return $in[$k] ?? '';
};
$validPlace = static function (string $p) use (&$in, $str): bool {
    $lat = filter_var($in[$p . 'lat'] ?? null, FILTER_VALIDATE_FLOAT);
    $lon = filter_var($in[$p . 'lon'] ?? null, FILTER_VALIDATE_FLOAT);
    return $lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && Zone::isValid($str($p . 'tz'));
};
// The browser must send resolved coordinates and zone; no place lookup happens here.
if (!$validPlace('')) {
    $reply(422, ['ok' => false, 'error' => 'data']);
}
if ($str('pos_city') !== '' && !$validPlace('pos_')) {
    unset($in['pos_city'], $in['pos_lat'], $in['pos_lon'], $in['pos_tz']);
}

$parsed = Request::parse($in, new Geocoder(MAGIC_ROOT . '/cache'));
$p = $parsed['input'];
if ($p === null) {
    $reply(422, ['ok' => false, 'error' => 'data']);
}
$person = [
    'name' => (string) ($p['name'] ?? ''),
    'date' => sprintf('%04d-%02d-%02d', $p['year'], $p['month'], $p['day']),
    'time' => sprintf('%02d:%02d', $p['hour'], $p['minute']),
    'city' => $p['city'], 'lat' => $p['lat'], 'lon' => $p['lon'], 'tz' => $p['tz'],
];
if ($p['now'] !== null) {
    $person += ['pos_city' => $p['now']['city'], 'pos_lat' => $p['now']['lat'], 'pos_lon' => $p['now']['lon'], 'pos_tz' => $p['now']['tz']];
}
try {
    $code = ShareCode::encodeHidden($person);
} catch (\Throwable) {
    $reply(422, ['ok' => false, 'error' => 'data']);
}

$origin = Http::origin($_SERVER);
$base = Http::basePath($_SERVER);
$link = ($origin !== null && $base !== null ? $origin . $base . '/' : './') . '?h=' . $code;
$qr = null;
if ($origin !== null && $base !== null) {
    $modules = Qr::encode($link);
    if ($modules !== null) {
        $qr = ['size' => count($modules) + 2 * Qr::QUIET, 'path' => Qr::path($modules)];
    }
}
$reply(200, ['ok' => true, 'link' => $link, 'qr' => $qr, 'tooLong' => $origin !== null && $base !== null && $qr === null]);

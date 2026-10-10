<?php
declare(strict_types=1);

// Dependency-free test runner: `php tests/run.php`. Exits non-zero on failure.
require __DIR__ . '/../src/autoload.php';

use Magic\Astro\Angles;
use Magic\Astro\Ascendant;
use Magic\Astro\Moon;
use Magic\Astro\Sun;
use Magic\Astro\Zodiac;
use Magic\Chart;
use Magic\Geo\FallbackCities;
use Magic\Geo\Geocoder;
use Magic\Request;
use Magic\Time\Zone;

define('MAGIC_TESTING', true);
$failures = 0;
$count = 0;

function check(string $name, callable $fn): void
{
    global $failures, $count;
    $count++;
    try {
        $fn();
        echo "ok   $name\n";
    } catch (Throwable $t) {
        $failures++;
        echo "FAIL $name: {$t->getMessage()}\n";
    }
}
function same(mixed $got, mixed $want): void
{
    if ($got !== $want) {
        throw new RuntimeException('got ' . var_export($got, true) . ', expected ' . var_export($want, true));
    }
}
function near(float $got, float $want, float $tol): void
{
    if (abs($got - $want) > $tol) {
        throw new RuntimeException("got $got, expected $want ± $tol");
    }
}
function utc(int $y, int $mo, int $d, int $h, int $mi): float
{
    return Angles::julianDay(gmmktime($h, $mi, 0, $mo, $d, $y));
}

check('julian day of J2000', fn () => same(Angles::julianDay(946728000), 2451545.0));
check('sun longitude at J2000 ≈ 280.37°', fn () => near(Sun::longitude(2451545.0), 280.37, 0.02));
check('sun at March 2000 equinox ≈ 0° Aries', fn () => near(fmod(Sun::longitude(utc(2000, 3, 20, 7, 35)) + 180, 360) - 180, 0, 0.02));
check('moon, Meeus ex. 47.a (133.1627° + nutation)', fn () => near(Moon::longitude(2448724.5), 133.1627 + 0.0046, 0.03));
check('ascendant RAMC 0° at equator = 90°', fn () => near(Ascendant::longitude(2451545.0, 0, -280.46061837), 90, 0.01));
check('ascendant near the Sun at sunrise (London, solstice)', function () {
    $jd = utc(2024, 6, 21, 3, 45);
    near(fmod(Ascendant::longitude($jd, 51.5, -0.12) - Sun::longitude($jd) + 540, 360) - 180, 0, 15);
});
check('zodiac sign/degree/minute', function () {
    $s = Zodiac::fromLongitude(95.5);
    same([$s['name'], $s['degree'], $s['minute']], ['Cancer', 5, 30]);
    same(Zodiac::fromLongitude(-1)['name'], 'Pisces');
    same(Zodiac::fromLongitude(360.0)['name'], 'Aries');
});
check('Einstein 1879-03-14 10:50 UTC Ulm: Pisces/Sagittarius/Cancer', function () {
    $jd = utc(1879, 3, 14, 10, 50);
    same(Zodiac::fromLongitude(Sun::longitude($jd))['name'], 'Pisces');
    same(Zodiac::fromLongitude(Moon::longitude($jd))['name'], 'Sagittarius');
    same(Zodiac::fromLongitude(Ascendant::longitude($jd, 48.4, 9.99))['name'], 'Cancer');
});
check('Chart::compute end-to-end (Rome, CEST)', function () {
    $c = Chart::compute(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome');
    same($c['utc'], '1990-07-15 06:30');
    same([$c['sun']['name'], $c['moon']['name'], $c['ascendant']['name'], $c['polar']], ['Cancer', 'Aries', 'Leo', false]);
});

$ts = fn (int $y, int $mo, int $d, int $h, int $mi, string $tz) => gmdate('Y-m-d H:i', Zone::toUnix($y, $mo, $d, $h, $mi, $tz));
check('zone: winter/summer offsets', function () use ($ts) {
    same($ts(2024, 1, 15, 12, 0, 'Europe/Rome'), '2024-01-15 11:00');
    same($ts(2024, 7, 15, 12, 0, 'Europe/Rome'), '2024-07-15 10:00');
});
check('zone: half-hour and far zones', function () use ($ts) {
    same($ts(2024, 1, 1, 0, 0, 'Asia/Kolkata'), '2023-12-31 18:30');
    same($ts(2024, 1, 1, 0, 0, 'America/Los_Angeles'), '2024-01-01 08:00');
});
check('zone: DST overlap = first occurrence (NY 2024-11-03 01:30 EDT)', fn () => same($ts(2024, 11, 3, 1, 30, 'America/New_York'), '2024-11-03 05:30'));
check('zone: DST gap shifts forward (NY 2024-03-10 02:30)', function () use ($ts) {
    $r = $ts(2024, 3, 10, 2, 30, 'America/New_York');
    if (!in_array($r, ['2024-03-10 06:30', '2024-03-10 07:30'], true)) {
        throw new RuntimeException($r);
    }
});
check('zone: rejects bad zone and bad date', function () {
    foreach ([[2024, 2, 30, 1, 1, 'Europe/Rome'], [2024, 1, 1, 1, 1, 'Mars/Base']] as $a) {
        try {
            Zone::toUnix(...$a);
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('expected exception');
    }
});

// Offline geocoder (unreachable cache dir + fallback list only).
check('fallback city search folds accents and case', function () {
    same(FallbackCities::search('sao')[0]['name'], 'São Paulo');
    same(FallbackCities::search('ROME')[0]['timeZone'], 'Europe/Rome');
});
$geo = new Geocoder(sys_get_temp_dir());
check('request: validation errors', function () use ($geo) {
    $r = Request::parse(['date' => '2024-13-01', 'time' => '25:00', 'city' => ''], $geo);
    same($r['input'], null);
    same(count($r['errors']), 3);
});
check('request: accepts complete input without network', function () use ($geo) {
    $r = Request::parse(['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'], $geo);
    same($r['errors'], []);
    same($r['input']['hour'], 8);
});
check('request: rejects tampered time zone (falls back to geocoding path)', function () use ($geo) {
    $r = Request::parse(['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => '../../etc'], $geo);
    if ($r['input'] !== null && $r['input']['tz'] === '../../etc') {
        throw new RuntimeException('tz accepted');
    }
});

// ADR 0002 test cases (use check/same/near/utc defined above).
foreach (['planets', 'bio', 'love', 'tarot', 'request', 'layering', 'audit', 'share', 'position', 'code', 'memory', 'sky', 'ui', 'hidden', 'flow', 'docs'] as $case) {
    require __DIR__ . "/cases/$case.php";
}

echo "\n$count tests, $failures failed\n";
exit($failures === 0 ? 0 : 1);

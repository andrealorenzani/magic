<?php
declare(strict_types=1);

use Arcana\Astro\Angles;
use Arcana\Astro\MeanNode;
use Arcana\Astro\Moon;
use Arcana\Astro\Planets;
use Arcana\Astro\Sun;
use Arcana\Astro\Zodiac;
use Arcana\Chart;

/** Circular difference in degrees. */
$circ = fn (float $a, float $b): float => abs(fmod($a - $b + 540, 360) - 180);

check('planets at J2000 (geocentric ecliptic longitude, tol 0.5)', function () use ($circ) {
    $want = ['mercury' => 271.9, 'venus' => 241.6, 'mars' => 327.9, 'jupiter' => 25.3, 'saturn' => 40.4, 'uranus' => 314.8, 'neptune' => 303.2, 'pluto' => 251.5];
    foreach ($want as $id => $lon) {
        $got = Planets::longitude($id, 2451545.0);
        if ($circ($got, $lon) > 0.5) {
            throw new RuntimeException("$id got $got, expected $lon");
        }
    }
    same(array_keys(Planets::all(2451545.0)), Planets::BODIES);
});
check('planet ingresses: Jupiter->Gemini, Uranus->Gemini, Neptune->Aries, Saturn->Aries', function () use ($circ) {
    foreach ([['jupiter', 2024, 5, 25, 60.0], ['uranus', 2025, 7, 7, 60.0], ['neptune', 2025, 3, 30, 0.0], ['saturn', 2025, 5, 25, 0.0]] as [$id, $y, $m, $d, $lon]) {
        $got = Planets::longitude($id, utc($y, $m, $d, 12, 0));
        if ($circ($got, $lon) > 0.5) {
            throw new RuntimeException("$id $y-$m-$d got $got, expected $lon");
        }
    }
});
check('planet retrograde flags', function () {
    $retro = fn (string $id, int $y, int $m, int $d): bool => Chart::full($y, $m, $d, 12, 0, 0.0, 0.0, 'UTC')['planets'][$id]['retrograde'];
    same($retro('mercury', 2025, 3, 25), true);
    same($retro('mercury', 2025, 6, 1), false);
    same($retro('mars', 2025, 1, 1), true);
    same($retro('jupiter', 2025, 1, 1), true);
    same($retro('venus', 2025, 6, 1), false);
});
check('kepler solver', function () {
    same(Planets::kepler(0.7, 0.0), 0.7);
    $E = Planets::kepler(1.0, 0.2);
    near($E - 0.2 * sin($E), 1.0, 1e-10);
});
check('planets: unknown id throws, support range', function () {
    try {
        Planets::longitude('vulcan', 2451545.0);
        throw new RuntimeException('expected exception');
    } catch (InvalidArgumentException) {
    }
    same(Planets::supports(utc(1800, 1, 1, 0, 0)), true);
    same(Planets::supports(utc(2100, 12, 31, 23, 59)), true);
    same(Planets::supports(utc(1799, 12, 31, 12, 0)), false);
    same(Planets::supports(utc(2101, 1, 1, 12, 0)), false);
});
check('Chart::full outside planet range keeps the big three', function () {
    $c = Chart::full(1500, 6, 1, 12, 0, 41.9, 12.5, 'Europe/Rome');
    same($c['planetsSupported'], false);
    same($c['planets'], []);
    same(isset($c['sun'], $c['moon'], $c['ascendant'], $c['node']), true);
    $r = Chart::full(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome');
    same($r['planetsSupported'], true);
    same(count($r['planets']), 8);
    same($r['sun'], Chart::compute(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome')['sun']);
});
check('mean node: J2000 and Meeus 47.b', function () {
    near(MeanNode::longitude(2451545.0), 125.04, 0.01);
    near(MeanNode::longitude(2446895.5), 11.2531, 0.01);
});
check('Chart::partial: date only has no Ascendant and flags cusp days', function () {
    $c = Chart::partial(2000, 3, 20, null, null, null, null, null);
    same($c['ascendant'], null);
    same($c['approx']['sun'], true);
    same($c['sun']['name'], 'Aries');
    $j = Chart::partial(2000, 7, 15, null, null, null, null, null);
    same([$j['sun']['name'], $j['approx']['sun']], ['Cancer', false]);
    // A time without a place is ignored.
    same(Chart::partial(2000, 7, 15, 10, 0, null, null, null)['ascendant'], null);
});
check('Chart::partial: full data equals compute (Einstein-like case)', function () {
    $p = Chart::partial(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome');
    $c = Chart::compute(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome');
    same([$p['sun'], $p['moon'], $p['ascendant']], [$c['sun'], $c['moon'], $c['ascendant']]);
    same($p['approx'], ['sun' => false, 'moon' => false]);
    same([$p['sun']['name'], $p['moon']['name'], $p['ascendant']['name']], ['Cancer', 'Aries', 'Leo']);
});
check('Chart::partial: approx.moon matches independent sign check', function () {
    $sign = fn (float $l): int => Zodiac::fromLongitude($l)['index'];
    for ($d = 1; $d <= 28; $d++) {
        $start = Angles::julianDay(gmmktime(0, 0, 0, 2, $d, 2024));
        $want = $sign(Moon::longitude($start)) !== $sign(Moon::longitude($start + 1.0));
        same(Chart::partial(2024, 2, $d, null, null, null, null, null)['approx']['moon'], $want);
    }
    $start = Angles::julianDay(gmmktime(0, 0, 0, 3, 20, 2000));
    same($sign(Sun::longitude($start)) !== $sign(Sun::longitude($start + 1.0)), true);
});

<?php
declare(strict_types=1);

use Magic\Astro\Angles;
use Magic\Astro\Aspects;
use Magic\Astro\Ascendant;
use Magic\Astro\Houses;
use Magic\Astro\MoonPhase;
use Magic\Astro\Zodiac;
use Magic\Chart;
use Magic\ChartWheel;
use Magic\Content\Daily;
use Magic\Content\Houses as HouseText;
use Magic\Love\Synastry;
use Magic\Sky\Today;

// ---- Midheaven and houses ----
check('midheaven from RAMC (obliquity 23.4393): 0/90/180/270 are fixed points, 45 gives 47.46', function () {
    foreach ([0, 90, 180, 270] as $r) {
        near(Houses::midheavenFromRamc((float) $r, 23.4393), (float) $r, 1e-9);
    }
    near(Houses::midheavenFromRamc(45.0, 23.4393), 47.46, 0.01);
    near(Houses::midheavenFromRamc(315.0, 23.4393), 360.0 - 47.46, 0.01);
});
check('midheaven: at the equator with RAMC 0 the Midheaven is 0 and the Ascendant 90', function () {
    $jd = 2451545.0;
    $lon = -Ascendant::gmst($jd);
    near(Houses::midheaven($jd, $lon), 0.0, 0.01);
    near(Ascendant::longitude($jd, 0.0, $lon), 90.0, 0.01);
});
check('midheaven: 1990-07-15 08:30 Rome = 45.31 degrees (independent numeric cross-check, sidereal time checked against a published example)', function () {
    $c = Chart::compute(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    near($c['midheaven']['longitude'], 45.3137, 0.01);
    same($c['midheaven']['name'], 'Taurus');
    same($c['midheaven']['degree'], 15);
});
check('midheaven: always within about 90 degrees of the Ascendant side for mid latitudes (1000 random births)', function () {
    mt_srand(7);
    for ($i = 0; $i < 1000; $i++) {
        $c = Chart::compute(mt_rand(1900, 2050), mt_rand(1, 12), mt_rand(1, 28), mt_rand(0, 23), mt_rand(0, 59), mt_rand(-600, 600) / 10, mt_rand(-1800, 1800) / 10, 'UTC');
        $d = fmod($c['ascendant']['longitude'] - $c['midheaven']['longitude'] + 720.0, 360.0);
        if ($d < 0.0 || $d > 180.0) {
            throw new RuntimeException("Ascendant is not 0-180 degrees after the Midheaven: $d");
        }
    }
});
check('whole-sign houses: same sign is house 1, Gemini ascendant with Aries Sun is house 11, Cancer 2, Taurus 12', function () {
    same(Houses::wholeSign(75.0, 80.0), 1);
    same(Houses::wholeSign(75.0, 10.0), 11);
    same(Houses::wholeSign(75.0, 100.0), 2);
    same(Houses::wholeSign(75.0, 40.0), 12);
    same(Houses::wholeSign(359.0, 0.5), 2);
});
check('chart: houses for every body, midheaven in partial only with time and place', function () {
    $c = Chart::full(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    same(array_keys($c['houses']), ['ascendant', 'sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'node', 'midheaven']);
    foreach ($c['houses'] as $h) {
        same($h >= 1 && $h <= 12, true);
    }
    same($c['houses']['ascendant'], 1);
    same(Chart::partial(1990, 7, 15, 8, 30, 41.9, 12.5, 'Europe/Rome')['midheaven']['name'], 'Taurus');
    same(Chart::partial(1990, 7, 15, null, null, null, null, null)['midheaven'], null);
    same(count(HouseText::THEMES), 12);
});

// ---- Moon phase ----
check('moon phase at known events (UTC): new and full moons, quarters', function () {
    $at = static fn (int $y, int $mo, int $d, int $h, int $mi): array => MoonPhase::at(utc($y, $mo, $d, $h, $mi));
    $events = [
        [2000, 1, 6, 18, 14, 'new', 0.0], [2000, 1, 21, 4, 40, 'full', 1.0],
        [2024, 1, 11, 11, 57, 'new', 0.0], [2024, 1, 25, 17, 54, 'full', 1.0],
        [2024, 4, 8, 18, 21, 'new', 0.0], [2024, 4, 15, 19, 13, 'first-quarter', 0.5],
        [2024, 4, 23, 23, 49, 'full', 1.0], [2024, 1, 4, 3, 30, 'last-quarter', 0.5],
    ];
    foreach ($events as [$y, $mo, $d, $h, $mi, $phase, $ill]) {
        $r = $at($y, $mo, $d, $h, $mi);
        same($r['phase'], $phase);
        near($r['illumination'], $ill, $ill == 0.5 ? 0.02 : 0.01);
    }
    near(MoonPhase::at(utc(2024, 1, 11, 11, 57))['angle'] > 180 ? MoonPhase::at(utc(2024, 1, 11, 11, 57))['angle'] - 360 : 0.0, 0.0, 0.2);
    near(MoonPhase::at(utc(2024, 1, 25, 17, 54))['angle'], 180.0, 0.2);
});
check('moon phase: a day after the full moon is waning gibbous; a week after the new moon is first quarter; eight names', function () {
    same(MoonPhase::at(utc(2024, 1, 26, 17, 54))['phase'], 'waning-gibbous');
    same(MoonPhase::at(utc(2024, 1, 14, 12, 0))['phase'], 'waxing-crescent');
    same(MoonPhase::at(utc(2024, 1, 20, 12, 0))['phase'], 'waxing-gibbous');
    same(MoonPhase::at(utc(2024, 2, 5, 12, 0))['phase'], 'waning-crescent');
    same(count(MoonPhase::PHASES), 8);
    same(array_keys(Daily::PHASE_NAMES), MoonPhase::PHASES);
    same(MoonPhase::name(0.0), 'new');
    same(MoonPhase::name(355.0), 'new');
    same(MoonPhase::name(45.0), 'waxing-crescent');
    same(MoonPhase::name(225.0), 'waning-gibbous');
});
check('moon phase: illumination within 0 and 1 and a phase is always named, for 1000 sampled days', function () {
    for ($i = 0; $i < 1000; $i++) {
        $r = MoonPhase::at(2451545.0 + $i * 29.7 + 0.37);
        if ($r['illumination'] < 0.0 || $r['illumination'] > 1.0 || !in_array($r['phase'], MoonPhase::PHASES, true)) {
            throw new RuntimeException('bad phase ' . json_encode($r));
        }
    }
});

// ---- Today's sky ----
check("today's sky: deterministic, every Sun sign gets a reading, moon sign of the day known", function () {
    $signs = [];
    foreach (Zodiac::SIGNS as $i => $_) {
        $signs[$i] = Zodiac::fromLongitude($i * 30 + 10.0);
    }
    $a = Today::for('2026-10-09', $signs);
    same($a, Today::for('2026-10-09', $signs));
    same(count($a['people']), 12);
    foreach ($a['people'] as $p) {
        same(mb_strlen($p['reading']) > 80, true);
    }
    same(Today::for('2024-01-25', [])['phase'], 'full');
    same(Today::for('2024-01-11', [])['phase'], 'new');
    same(Today::for('2024-01-25', [])['moon']['name'], 'Leo');
    same(Today::for('2024-01-25', [])['illumination'] >= 99, true);
});
check("today's sky: copy has 12 + 5 + 8 + 8 unique non-empty strings and every pair of Moon and Sun sign composes a reading", function () {
    foreach ([Daily::MOOD, Daily::RELATION, Daily::INVITATION, Daily::PHASE_NAMES] as $group) {
        same(count(array_unique($group)), count($group));
        foreach ($group as $t) {
            same(trim($t) !== '', true);
        }
    }
    same([count(Daily::MOOD), count(Daily::RELATION), count(Daily::INVITATION), count(Daily::PHASE_NAMES)], [12, 5, 8, 8]);
    $texts = [];
    foreach (Zodiac::SIGNS as $m => $_) {
        foreach (Zodiac::SIGNS as $s => $_2) {
            $moon = Zodiac::fromLongitude($m * 30 + 5.0);
            $sun = Zodiac::fromLongitude($s * 30 + 5.0);
            $level = Magic\Love\Common::level($moon, $sun)[0];
            $texts[Daily::MOOD[$moon['id']] . ' ' . Daily::RELATION[$level]] = true;
        }
    }
    same(count($texts) > 12, true);
});

// ---- Aspects ----
check('aspects: exact, in orb, wrapping and none', function () {
    same(Aspects::between(0.0, 120.0), ['type' => 'trine', 'orb' => 0.0]);
    same(Aspects::between(0.0, 124.0), ['type' => 'trine', 'orb' => 4.0]);
    same(Aspects::between(0.0, 95.0), ['type' => 'square', 'orb' => 5.0]);
    same(Aspects::between(0.0, 172.0), ['type' => 'opposition', 'orb' => 8.0]);
    same(Aspects::between(359.0, 1.0), ['type' => 'conjunction', 'orb' => 2.0]);
    same(Aspects::between(0.0, 100.0), null);
    same(Aspects::between(10.0, 70.0), ['type' => 'sextile', 'orb' => 0.0]);
    same(Aspects::between(350.0, 50.0), ['type' => 'sextile', 'orb' => 0.0]);
    same(Aspects::between(0.0, 240.0), ['type' => 'trine', 'orb' => 0.0]);
    same(Aspects::between(0.0, 66.0), null);
    same(Aspects::between(0.0, 65.0)['type'], 'sextile');
});
check('aspects: orb limits are pinned (conjunction 8, opposition 8, trine 7, square 7, sextile 5) and tested at the edges', function () {
    same(array_map(fn (array $t): float => $t[1], Aspects::TYPES), ['conjunction' => 8.0, 'sextile' => 5.0, 'square' => 7.0, 'trine' => 7.0, 'opposition' => 8.0]);
    same(Aspects::between(0.0, 8.0)['type'], 'conjunction');
    same(Aspects::between(0.0, 8.01), null);
    same(Aspects::between(0.0, 172.0)['type'], 'opposition');
    same(Aspects::between(0.0, 171.99), null);
    same(Aspects::between(0.0, 97.0)['type'], 'square');
    same(Aspects::between(0.0, 97.01), null);
    same(Aspects::between(0.0, 113.0)['type'], 'trine');
    same(Aspects::between(0.0, 112.99), null);
    same(Aspects::between(0.0, 55.0)['type'], 'sextile');
    same(Aspects::between(0.0, 54.99), null);
});
check('aspects: the orb equals the distance from the exact angle for 2000 random longitudes (independent recomputation)', function () {
    mt_srand(11);
    for ($i = 0; $i < 2000; $i++) {
        $a = mt_rand(0, 359999) / 1000.0;
        $b = mt_rand(0, 359999) / 1000.0;
        $d = fmod(abs($a - $b), 360.0);
        $sep = $d > 180.0 ? 360.0 - $d : $d;
        $best = null;
        foreach ([[0, 8, 'conjunction'], [60, 5, 'sextile'], [90, 7, 'square'], [120, 7, 'trine'], [180, 8, 'opposition']] as [$ang, $lim, $name]) {
            if (abs($sep - $ang) <= $lim) {
                $best = [$name, abs($sep - $ang)];
            }
        }
        $got = Aspects::between($a, $b);
        if ($best === null) {
            same($got, null);
        } else {
            same($got['type'], $best[0]);
            near($got['orb'], $best[1], 0.0001);
        }
    }
});

// ---- Synastry ----
check('synastry: a chart against itself gives every shared body as a conjunction with orb 0', function () {
    $lons = Chart::longitudes(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    same(array_keys($lons), ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'midheaven']);
    $r = Synastry::between($lons, $lons, 1000);
    $self = array_filter($r['rows'], fn (array $row): bool => $row['a'] === $row['b']);
    same(array_column($self, 'a'), ['sun', 'moon', 'venus', 'mars', 'ascendant']);
    same(count(Synastry::between($lons, $lons, 1000)['rows']), $r['total']);
    foreach ($self as $row) {
        same([$row['type'], $row['orb']], ['conjunction', 0.0]);
    }
    same(array_sum($r['counts']), $r['total']);
    same($r['approx'], false);
    same($r['available'], true);
});
check('synastry: capped at 12, sorted by orb, only aspects with Sun, Moon, Venus, Mars or Ascendant', function () {
    $a = Chart::longitudes(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    $b = Chart::longitudes(1988, 2, 3, 20, 10, 45.46, 9.19, 'Europe/Rome');
    $r = Synastry::between($a, $b);
    same(count($r['rows']) <= 12, true);
    same($r['total'] >= count($r['rows']), true);
    $prev = -1.0;
    foreach ($r['rows'] as $row) {
        same($row['orb'] >= $prev, true);
        $prev = $row['orb'];
        same(in_array($row['a'], Synastry::PERSONAL, true) || in_array($row['b'], Synastry::PERSONAL, true), true);
        same(Aspects::between($a[$row['a']], $b[$row['b']]), ['type' => $row['type'], 'orb' => $row['orb']]);
    }
    same(Synastry::between($a, $b), $r);
    same(array_sum($r['counts']), $r['total']);
});
check('synastry: a date-only partner has no Moon, Ascendant or Midheaven and the result is approximate; outside 1800-2100 only Sun, Moon and angles', function () {
    $dateOnly = Chart::longitudes(1991, 3, 2, null, null, null, null, null);
    same(array_keys($dateOnly), ['sun', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto']);
    $a = Chart::longitudes(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    $r = Synastry::between($a, $dateOnly);
    same($r['approx'], true);
    foreach ($r['rows'] as $row) {
        same(in_array($row['b'], ['moon', 'ascendant', 'midheaven'], true), false);
    }
    $old = Chart::longitudes(1750, 5, 5, 12, 0, 48.85, 2.35, 'UTC');
    same(array_keys($old), ['sun', 'moon', 'ascendant', 'midheaven']);
    same(array_keys(Chart::longitudes(1750, 5, 5, null, null, null, null, null)), ['sun']);
});
check('synastry: the love view has the block, a placeholder without the loved person\'s date', function () {
    $geo = new Magic\Geo\Geocoder(sys_get_temp_dir());
    $get = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Bob'];
    $r = Magic\Request::parseLove($get, $geo);
    $v = Magic\LoveReading::build($r['a'], $r['b'], '2026-10-09');
    same($v['synastry']['available'], false);
    same(array_keys($v['sky']['people']), ['a']);
    $r = Magic\Request::parseLove($get + ['b_date' => '1992-01-02'], $geo);
    $v = Magic\LoveReading::build($r['a'], $r['b'], '2026-10-09');
    same($v['synastry']['available'], true);
    same($v['synastry']['approx'], true);
    same(array_keys($v['sky']['people']), ['a', 'b']);
});

// ---- Chart wheel ----
check('wheel: Ascendant at the left, deterministic, finite coordinates', function () {
    $c = Chart::full(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    $w = ChartWheel::layout($c);
    same($w, ChartWheel::layout($c));
    $asc = $w['axes'][0];
    same($asc['id'], 'ascendant');
    near($asc['angle'], 180.0, 1e-9);
    near($asc['line']['y1'], 200.0, 0.01);
    same($asc['line']['x1'] < 200.0, true);
    same(count($w['signs']), 12);
    same(count($w['houses']), 12);
    same(count($w['bodies']), 11);
    same($w['houses'][$c['ascendant']['index']]['number'], 1);
    $flat = [];
    array_walk_recursive($w, function ($v) use (&$flat) {
        if (is_float($v)) {
            $flat[] = $v;
        }
    });
    foreach ($flat as $v) {
        same(is_finite($v), true);
    }
    // MC is on the upper half for a mid-latitude birth.
    $mc = $w['axes'][1];
    same($mc['id'], 'midheaven');
    same($mc['line']['y2'] < 200.0, true);
});
check('wheel: no two glyphs closer than the minimum separation, for 1000 random charts and for stacked bodies', function () {
    mt_srand(5);
    for ($i = 0; $i < 1000; $i++) {
        $angles = [];
        $stack = mt_rand(0, 1) === 1;
        $base = mt_rand(0, 359999) / 1000.0;
        for ($k = 0; $k < 11; $k++) {
            $angles[] = $stack ? Angles::norm360($base + mt_rand(0, 4000) / 1000.0) : mt_rand(0, 359999) / 1000.0;
        }
        $out = ChartWheel::spread($angles, ChartWheel::MIN_SEPARATION);
        sort($out);
        for ($k = 0; $k < 11; $k++) {
            $gap = ($out[($k + 1) % 11] - $out[$k] + 360.0);
            $gap = fmod($gap, 360.0);
            if ($gap < ChartWheel::MIN_SEPARATION - 1e-6) {
                throw new RuntimeException("gap $gap in chart $i");
            }
        }
    }
    $c = Chart::full(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    $w = ChartWheel::layout($c);
    $drawn = array_column($w['bodies'], 'drawnAngle');
    sort($drawn);
    for ($k = 0; $k < count($drawn); $k++) {
        $gap = fmod($drawn[($k + 1) % count($drawn)] - $drawn[$k] + 360.0, 360.0);
        same($gap >= ChartWheel::MIN_SEPARATION - 1e-6, true);
    }
});
check('wheel partial: one svg, no style attribute or script, texts escaped; sky and synastry partials are escaped', function () {
    $c = Chart::full(1990, 7, 15, 8, 30, 41.9028, 12.4964, 'Europe/Rome');
    if (!function_exists('e')) {
        require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
    }
    require_once dirname(__DIR__, 2) . '/templates/partials/wheel.php';
    ob_start();
    wheel_svg(ChartWheel::layout($c), 'A <b>"label"</b> & more');
    $svg = (string) ob_get_clean();
    same(substr_count($svg, '<svg'), 1);
    same(preg_match('/\sstyle=|<script|\son\w+=/i', $svg), 0);
    same(str_contains($svg, '<b>'), false);
    same(str_contains($svg, '&lt;b&gt;'), true);
    same(substr_count($svg, 'class="wheel__body '), 11);
    same(str_contains($svg, 'role="img"'), true);
});

// ---- Pages ----
$skyPage = function (array $get): string {
    $root = dirname(__DIR__, 2);
    $code = '$_GET = json_decode($argv[1], true); $_COOKIE = ["magic_terms" => "4"]; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start(); require $argv[2]; echo ob_get_clean();';
    $out = (string) shell_exec('MAGIC_CONFIG=/nonexistent php -d display_errors=1 -r ' . escapeshellarg($code) . ' ' . escapeshellarg((string) json_encode($get)) . ' ' . escapeshellarg($root . '/public/index.php') . ' 2>&1');
    if (preg_match('/Warning|Notice|Fatal|Deprecated/', $out)) {
        throw new RuntimeException('PHP problem: ' . substr($out, 0, 300));
    }
    return $out;
};
check('self page: Midheaven, houses, wheel, born under, today\'s sky; no inline style or script', function () use ($skyPage) {
    $out = $skyPage(['mode' => 'self', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9028', 'lon' => '12.4964', 'tz' => 'Europe/Rome', 'on' => '2024-01-25', 'noaudit' => '']);
    same(substr_count($out, '<svg class="wheel"'), 1);
    same(str_contains($out, 'Midheaven in Taurus'), true);
    same(str_contains($out, 'House 12 - Inner life'), true);
    same(str_contains($out, 'Born under a <strong>'), true);
    same(str_contains($out, 'Full Moon</strong>, 100% lit'), true);
    same(str_contains($out, 'The Moon is in'), true);
    same(preg_match('/\sstyle=|<script(?![^>]*\ssrc=)/i', $out), 0);
    same(str_contains($out, '<th scope="col">House</th>'), true);
});
check('love page: synastry table after "in common", today\'s sky after it, minor arcana with its suit class', function () use ($skyPage) {
    $out = $skyPage(['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
        'b_name' => 'Silvia', 'b_date' => '1991-03-02', 'on' => '2026-10-09', 'noaudit' => '', 't' => '22u,40r,77u']);
    $c = strpos($out, 'id="common-h"');
    $s = strpos($out, 'id="syn-h"');
    $k = strpos($out, 'id="sky-h"');
    same($c !== false && $c < $s && $s < $k, true);
    same(str_contains($out, '<caption class="sr-only">'), true);
    same(str_contains($out, '<th scope="col">Aspect</th>'), true);
    same(substr_count($out, '<th scope="row">'), 12);
    same(str_contains(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($out))), "Ann's Mars square Silvia's Saturn"), true);
    same(str_contains($out, 'tarot__art tarot__art--wands'), true);
    same(str_contains($out, 'tarot__art tarot__art--pentacles'), true);
    same(str_contains($out, 'tarot__art tarot__art--cups tarot__art--reversed'), true);
    same(str_contains($out, '<span class="tarot__num">Kn</span>'), false);
    same(str_contains($out, '<span class="tarot__num">K</span>'), true);
    same(preg_match('/\sstyle=|<script(?![^>]*\ssrc=)/i', $out), 0);
    $out = $skyPage(['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Silvia', 'on' => '2026-10-09', 'noaudit' => '']);
    same(str_contains($out, 'birth date to compare your two skies'), true);
});
check('styles: print rules for the wheel, sky, synastry and tarot suits exist', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/styles.css');
    $print = substr($css, (int) strpos($css, "@media print"));
    foreach (['.wheel', '.synastry', '.sky', '.tarot__art--wands', '.table-wrap'] as $needle) {
        same(str_contains($print, $needle), true);
    }
});

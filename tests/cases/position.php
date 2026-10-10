<?php
declare(strict_types=1);

use Magic\Audit\AuditRecord;
use Magic\Consent;
use Magic\Earth\Distance;
use Magic\Earth\Geography;
use Magic\Geo\Geocoder;
use Magic\LoveReading;
use Magic\Request;
use Magic\SelfReading;
use Magic\Share\ShareLink;
use Magic\Time\Zone;

// ---- Reading day from the position's time zone (ADR 0005 A) ----
$at = static fn (string $iso): int => gmmktime((int) substr($iso, 11, 2), (int) substr($iso, 14, 2), 0, (int) substr($iso, 5, 2), (int) substr($iso, 8, 2), (int) substr($iso, 0, 4));

check('position: Zone::dateAt around the day boundary', function () use ($at) {
    $late = $at('2026-10-09 23:30');
    same(Zone::dateAt($late, 'UTC'), '2026-10-09');
    same(Zone::dateAt($late, 'Europe/Rome'), '2026-10-10');
    same(Zone::dateAt($late, 'America/Los_Angeles'), '2026-10-09');
    same(Zone::dateAt($late, 'Pacific/Auckland'), '2026-10-10');
    same(Zone::dateAt($at('2026-10-09 00:30'), 'America/Los_Angeles'), '2026-10-08');
    same(Zone::dateAt($at('2026-10-09 10:30'), 'Pacific/Kiritimati'), '2026-10-10');
    same(Zone::dateAt($at('2026-12-31 23:59'), 'Asia/Kolkata'), '2027-01-01');
});
check('position: Zone::offsetMinutes incl. daylight saving', function () use ($at) {
    same(Zone::offsetMinutes($at('2026-10-09 12:00'), 'Asia/Kolkata'), 330);
    same(Zone::offsetMinutes($at('2026-07-01 12:00'), 'Europe/Rome'), 120);
    same(Zone::offsetMinutes($at('2026-01-15 12:00'), 'Europe/Rome'), 60);
    same(Zone::offsetMinutes($at('2026-07-01 12:00'), 'America/New_York'), -240);
    same(Zone::offsetMinutes($at('2026-01-15 12:00'), 'America/New_York'), -300);
    same(Zone::offsetMinutes($at('2026-10-09 12:00'), 'Pacific/Kiritimati'), 840);
    // The clocks change on 2026-10-25 in Europe: the difference to New York is 5 h before and 6 h after.
    same(Zone::offsetMinutes($at('2026-10-30 12:00'), 'Europe/Rome'), 60);
    same(Zone::offsetMinutes($at('2026-10-09 12:00'), 'Europe/Rome'), 120);
});
check('position: Zone rejects an unknown zone', function () {
    $thrown = false;
    try {
        Zone::dateAt(0, '../../etc');
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    same($thrown, true);
});
check('position: resolveToday uses on=, else the zone day, else UTC', function () use ($at) {
    $late = $at('2026-10-09 23:30');
    same(Request::resolveToday(['on' => '2020-02-02'], $late, 'Pacific/Auckland'), '2020-02-02');
    same(Request::resolveToday(['on' => 'nope'], $late, 'Pacific/Auckland'), '2026-10-10');
    same(Request::resolveToday(['on' => '1899-12-31'], $late, 'Pacific/Auckland'), '2026-10-10');
    same(Request::resolveToday([], $late, 'Pacific/Auckland'), '2026-10-10');
    same(Request::resolveToday([], $late, null), '2026-10-09');
    same(Request::resolveToday([], $late, '../bad'), '2026-10-09');
    same(Request::dayBasis([], 'Europe/Rome'), 'current_position');
    same(Request::dayBasis([], null), 'utc');
    same(Request::dayBasis(['on' => '2020-02-02'], 'Europe/Rome'), 'on');
});

// ---- Parsing of pos_* ----
$posGeo = new Geocoder(sys_get_temp_dir());
$selfQ = ['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'];
check('position: Self without pos_* behaves as before', function () use ($posGeo, $selfQ) {
    $r = Request::parse($selfQ, $posGeo);
    same($r['errors'], []);
    same($r['input']['now'], null);
    same($r['notes'], []);
});
check('position: Self with a valid position keeps 5 decimals', function () use ($posGeo, $selfQ) {
    $r = Request::parse($selfQ + ['pos_city' => 'Auckland, New Zealand', 'pos_lat' => '-36.848461234', 'pos_lon' => '174.763332', 'pos_tz' => 'Pacific/Auckland'], $posGeo);
    same($r['errors'], []);
    same($r['input']['now'], ['lat' => -36.84846, 'lon' => 174.76333, 'tz' => 'Pacific/Auckland', 'city' => 'Auckland, New Zealand']);
});
check('position: city text without coordinates is resolved, unknown or bad values are dropped with a note', function () use ($posGeo, $selfQ) {
    $r = Request::parse($selfQ + ['pos_city' => 'Rome'], $posGeo);
    same($r['input']['now']['tz'], 'Europe/Rome');
    same($r['notes'] !== [], true);
    foreach ([
        ['pos_city' => 'Zzqxvbnm Nowhereville'],
        ['pos_city' => str_repeat('x', 81), 'pos_lat' => '1', 'pos_lon' => '1', 'pos_tz' => 'UTC'],
        ['pos_city' => "Ro\x01me", 'pos_lat' => '1', 'pos_lon' => '1', 'pos_tz' => 'UTC'],
    ] as $extra) {
        $r = Request::parse($selfQ + $extra, $posGeo);
        same($r['errors'], []);
        same($r['input']['now'], null);
        same(count($r['notes']), 1);
        same(str_contains($r['notes'][0], 'Current position ignored'), true);
    }
});
check('position: out-of-range or array values never produce an error', function () use ($posGeo, $selfQ) {
    $r = Request::parse($selfQ + ['pos_city' => 'Rome', 'pos_lat' => '123', 'pos_lon' => '12', 'pos_tz' => 'Europe/Rome'], $posGeo);
    same($r['errors'], []);
    same($r['input']['now']['lat'] < 90, true); // coordinates were replaced by the resolved city
    $r = Request::parse($selfQ + ['pos_city' => ['Rome'], 'pos_lat' => ['1']], $posGeo);
    same([$r['errors'], $r['input']['now']], [[], null]);
});
check('position: Love, a loved person may have a position without birth data', function () use ($posGeo) {
    $q = ['a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
        'a_pos_city' => 'Milan', 'a_pos_lat' => '45.46427', 'a_pos_lon' => '9.18951', 'a_pos_tz' => 'Europe/Rome',
        'b_name' => 'Bob', 'b_pos_city' => 'New York', 'b_pos_lat' => '40.71427', 'b_pos_lon' => '-74.00597', 'b_pos_tz' => 'America/New_York'];
    $r = Request::parseLove($q, $posGeo);
    same($r['errors'], []);
    same($r['a']['now']['city'], 'Milan');
    same($r['b']['date'], null);
    same($r['b']['now']['tz'], 'America/New_York');
    unset($q['a_pos_city'], $q['b_pos_city']);
    $r = Request::parseLove($q, $posGeo);
    same([$r['a']['now'], $r['b']['now']], [null, null]);
});

// ---- Distances ----
check('position: great-circle distances against published values', function () {
    near(Distance::km(41.9028, 12.4964, 51.5074, -0.1278), 1434.0, 14.34);     // Rome-London
    near(Distance::km(48.8566, 2.3522, 40.7128, -74.0060), 5837.0, 58.37);     // Paris-New York
    near(Distance::km(41.9028, 12.4964, 40.7128, -74.0060), 6890.0, 69.0);     // Rome-New York, about 6,900 km
    near(Distance::km(0, 0, 0, 1), 111.2, 0.1);
    near(Distance::km(0, 0, 0, 90), 10007.5, 1.0);
    near(Distance::km(0, 0, 0, 180), 20015.0, 2.0);
    near(Distance::km(90, 0, -90, 0), 20015.0, 2.0);
    same(Distance::km(41.9, 12.5, 41.9, 12.5), 0.0);
    near(Distance::km(10, 20, -30, 140), Distance::km(-30, 140, 10, 20), 1e-9);
    near(Distance::miles(1609.344), 1000.0, 1e-9);
});
check('position: time-zone difference and formatting', function () use ($at) {
    $ref = $at('2026-10-09 12:00');
    $rome = ['lat' => 41.9028, 'lon' => 12.4964, 'tz' => 'Europe/Rome'];
    $ny = ['lat' => 40.7128, 'lon' => -74.0060, 'tz' => 'America/New_York'];
    $kol = ['lat' => 22.5726, 'lon' => 88.3639, 'tz' => 'Asia/Kolkata'];
    $g = Geography::between($rome, $ny, $ref);
    same([$g['minutes'], $g['sameZone']], [-360, false]);
    same($g['km'] > 6800 && $g['km'] < 6950, true);
    same(Geography::between($ny, $rome, $ref)['minutes'], 360);
    same(Geography::between($rome, $kol, $ref)['minutes'], 210);
    // After the European clock change the difference to New York is 5 h (their change comes a week later).
    same(Geography::between($rome, $ny, $at('2026-10-28 12:00'))['minutes'], -300);
    $same = Geography::between($rome, $rome, $ref);
    same([$same['km'], $same['minutes'], $same['sameZone']], [0, 0, true]);
    same(Geography::between($rome, null, $ref), null);
    same(Geography::between(null, null, $ref), null);
    same(Geography::formatOffset(330), '+5 h 30 min');
    same(Geography::formatOffset(-360), '-6 h');
    same(Geography::formatOffset(0), '0 h');
});

$posSelf = ['year' => 1990, 'month' => 7, 'day' => 15, 'hour' => 8, 'minute' => 30, 'lat' => 41.9, 'lon' => 12.5, 'tz' => 'Europe/Rome', 'city' => 'Rome',
    'now' => ['lat' => 40.71427, 'lon' => -74.00597, 'tz' => 'America/New_York', 'city' => 'New York']];
check('position: Self geo card appears only with a position', function () use ($posSelf) {
    $v = SelfReading::build($posSelf, '2026-10-09', 'current_position');
    same(count($v['geo']['pairs']), 1);
    same($v['geo']['pairs'][0]['key'], 'birth_to_now');
    same($v['geo']['pairs'][0]['minutes'], -360);
    same($v['dayBasis'], 'current_position');
    same($v['dayZone'], 'America/New_York');
    $noPos = $posSelf;
    $noPos['now'] = null;
    $v = SelfReading::build($noPos, '2026-10-09');
    same([$v['geo'], $v['dayBasis'], $v['dayZone']], [null, 'utc', null]);
    same(SelfReading::build($posSelf, '2026-10-09', 'on')['dayZone'], null);
});
check('position: Love geo card needs only one valid pair', function () use ($posGeo) {
    $base = ['a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Bob'];
    $build = function (array $extra) use ($posGeo, $base) {
        $r = Request::parseLove($base + $extra, $posGeo);
        return LoveReading::build($r['a'], $r['b'], '2026-10-09');
    };
    same($build([])['geo'], null);
    $v = $build(['b_pos_city' => 'Tokyo', 'b_pos_lat' => '35.6895', 'b_pos_lon' => '139.69171', 'b_pos_tz' => 'Asia/Tokyo']);
    same($v['geo'], null); // a loved person's position alone has nothing to be measured against
    $v = $build(['a_pos_city' => 'Milan', 'a_pos_lat' => '45.46427', 'a_pos_lon' => '9.18951', 'a_pos_tz' => 'Europe/Rome']);
    same(array_column($v['geo']['pairs'], 'key'), ['a_birth_to_now']);
    $v = $build(['a_pos_city' => 'Milan', 'a_pos_lat' => '45.46427', 'a_pos_lon' => '9.18951', 'a_pos_tz' => 'Europe/Rome',
        'b_pos_city' => 'Tokyo', 'b_pos_lat' => '35.6895', 'b_pos_lon' => '139.69171', 'b_pos_tz' => 'Asia/Tokyo']);
    same(array_column($v['geo']['pairs'], 'key'), ['between', 'a_birth_to_now']);
    same($v['geo']['pairs'][0]['minutes'], 420);
});

// ---- Share links carry the position ----
check('position: long share links round trip the position; old links are unchanged', function () use ($posGeo, $posSelf) {
    $q = ShareLink::self($posSelf, '2026-10-09');
    same(str_contains($q, '&pos_city=New%20York&pos_lat=40.71427&pos_lon=-74.00597&pos_tz=America/New_York&on='), true);
    parse_str($q, $parsed);
    $r = Request::parse($parsed, $posGeo);
    same($r['input']['now'], $posSelf['now']);
    $live = ShareLink::liveSelf($posSelf);
    same(str_contains($live, 'pos_tz=America/New_York') && !str_contains($live, '&on='), true);
    $noPos = $posSelf;
    $noPos['now'] = null;
    same(str_contains(ShareLink::self($noPos, '2026-10-09'), 'pos_'), false);

    $a = ['name' => 'Ann', 'date' => ['year' => 1990, 'month' => 7, 'day' => 15], 'time' => ['hour' => 8, 'minute' => 30],
        'place' => ['lat' => 41.9, 'lon' => 12.5, 'tz' => 'Europe/Rome', 'city' => 'Rome'], 'now' => $posSelf['now']];
    $b = ['name' => 'Bob', 'date' => null, 'time' => null, 'place' => null,
        'now' => ['lat' => 35.6895, 'lon' => 139.69171, 'tz' => 'Asia/Tokyo', 'city' => 'Tokyo']];
    foreach ([ShareLink::love($a, $b, null, '2026-10-09'), ShareLink::liveLove($a, $b)] as $lq) {
        parse_str($lq, $parsed);
        $r = Request::parseLove($parsed, $posGeo);
        same($r['errors'], []);
        same($r['a'], $a);
        same($r['b'], $b);
    }
});

// ---- Audit format 3 ----
check('position: audit record version 3 carries position, day basis and distances', function () use ($posSelf) {
    same(AuditRecord::FORMAT_VERSION, 3);
    $v = SelfReading::build($posSelf, '2026-10-09', 'current_position');
    $r = AuditRecord::fromSelf($posSelf, $v, '2026-10-09');
    same($r['format_version'], 3);
    $y = $r['response_yaml'];
    same(str_contains($y, "current_position:\n  label: New York\n  lat: 40.7143\n"), true);
    same(str_contains($y, "tz: America/New_York\n"), true);
    same(str_contains($y, 'reading_day_basis: current_position'), true);
    same(preg_match('/distance:\n  birth_to_now_km: 6\d{3}\n/', $y), 1);
    $noPos = $posSelf;
    $noPos['now'] = null;
    $y = AuditRecord::fromSelf($noPos, SelfReading::build($noPos, '2026-10-09'), '2026-10-09')['response_yaml'];
    same(str_contains($y, "current_position: null\n"), true);
    same(str_contains($y, 'reading_day_basis: utc'), true);
    same(str_contains($y, "distance:\n  birth_to_now_km: null\n"), true);
});
check('position: audit love record has positions a/b and distance keys', function () use ($posGeo) {
    $q = ['a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
        'a_pos_city' => 'Milan', 'a_pos_lat' => '45.46427', 'a_pos_lon' => '9.18951', 'a_pos_tz' => 'Europe/Rome', 'b_name' => 'Bob'];
    $r = Request::parseLove($q, $posGeo);
    $rec = AuditRecord::fromLove($r['a'], $r['b'], LoveReading::build($r['a'], $r['b'], '2026-10-09', null, 'current_position'), '2026-10-09');
    $y = $rec['response_yaml'];
    same(str_contains($y, "positions:\n  a:\n    label: Milan\n"), true);
    same(str_contains($y, "  b: null\n"), true);
    same(str_contains($y, 'reading_day_basis: current_position'), true);
    same(str_contains($y, '  between_km: null'), true);
    same(preg_match('/a_birth_to_now_km: \d+/', $y), 1);
    same(str_contains($y, 'b_birth_to_now_km: null'), true);
    // The SQL person rows still describe the birth place only.
    same($rec['persons'][0]['place_label'], 'Rome');
});

// ---- Terms consent value ----
check('position: consent cookie value is 4, the old value asks again', function () {
    same(Consent::VALUE, '4');
    same(Consent::given(['magic_terms' => '4']), true);
    same(Consent::given(['magic_terms' => '2']), false);
    same(Consent::given(['magic_terms' => '3']), false);
    same(Consent::given(['magic_terms' => '1']), false);
});
check('position: terms mention the current position', function () {
    $t = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/partials/terms.php');
    same(str_contains($t, 'the place where you are now'), true);
});

// ---- Geocoding cache cap ----
check('position: Geocoder::prune keeps the cache bounded and touches only its own files', function () {
    $dir = sys_get_temp_dir() . '/magic-prune-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $now = 2000000000;
    for ($i = 0; $i < 520; $i++) {
        $f = sprintf('%s/geo-%03d.json', $dir, $i);
        file_put_contents($f, '[]');
        touch($f, $now - 10000 + $i); // increasing mtimes, all fresh
    }
    $old = $dir . '/geo-old.json';
    file_put_contents($old, '[]');
    touch($old, $now - Geocoder::MAX_FILES * 86400); // expired
    file_put_contents($dir . '/other.txt', 'x');
    $removed = Geocoder::prune($dir, $now);
    $left = glob($dir . '/geo-*.json');
    same(count($left), Geocoder::PRUNE_TO);
    same($removed, 121);
    same(is_file($dir . '/geo-519.json') && is_file($dir . '/geo-120.json') && !is_file($dir . '/geo-119.json'), true);
    same([is_file($old), is_file($dir . '/other.txt')], [false, true]);
    same(Geocoder::prune($dir, $now), 0);
    same(Geocoder::prune($dir . '/missing', $now), 0);
    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
});

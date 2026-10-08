<?php
declare(strict_types=1);

use Arcana\Geo\Geocoder;
use Arcana\LoveReading;
use Arcana\Request;
use Arcana\SelfReading;

$g = new Geocoder(sys_get_temp_dir());
$A = ['a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome'];

check('request: mode selection (old URLs are Self)', function () {
    same(Request::mode([]), null);
    same(Request::mode(['date' => '1990-07-15']), 'self');
    same(Request::mode(['city' => 'Rome']), 'self');
    same(Request::mode(['mode' => 'self']), 'self');
    same(Request::mode(['mode' => 'love']), 'love');
    same(Request::mode(['mode' => 'hax', 'date' => '1990-07-15']), null);
    same(Request::mode(['mode' => ['x']]), null);
});
check('request: parseToday accepts valid on=, ignores invalid', function () {
    same(Request::parseToday(['on' => '2030-02-03'], '2026-10-09'), '2030-02-03');
    foreach (['2030-02-30', '1899-12-31', '2101-01-01', 'tomorrow', ''] as $bad) {
        same(Request::parseToday(['on' => $bad], '2026-10-09'), '2026-10-09');
    }
    same(Request::parseToday([], '2026-10-09'), '2026-10-09');
});
check('request love: missing a_name is an error', function () use ($g, $A) {
    $q = $A + ['b_name' => 'Bob'];
    unset($q['a_name']);
    $r = Request::parseLove($q, $g);
    same([$r['a'], $r['b']], [null, null]);
    same(count($r['errors']), 1);
});
check('request love: loved person with only a name is valid, no notes', function () use ($g, $A) {
    $r = Request::parseLove($A + ['b_name' => 'Bob'], $g);
    same($r['errors'], []);
    same($r['notes'], []);
    same($r['b'], ['name' => 'Bob', 'date' => null, 'time' => null, 'place' => null]);
    same($r['a']['place']['tz'], 'Europe/Rome');
    same($r['a']['time'], ['hour' => 8, 'minute' => 30]);
});
check('request love: time without date/city is ignored with a note', function () use ($g, $A) {
    $r = Request::parseLove($A + ['b_name' => 'Bob', 'b_time' => '10:00'], $g);
    same($r['errors'], []);
    same($r['b']['time'], null);
    same(count($r['notes']), 1);
    if (!str_contains($r['notes'][0], 'Birth time ignored: it needs a date and a place.')) {
        throw new RuntimeException($r['notes'][0]);
    }
});
check('request love: date + city without time is valid and never geocoded', function () use ($g, $A) {
    // A city that no fallback list contains: any geocoding attempt would yield an error.
    $r = Request::parseLove($A + ['b_name' => 'Bob', 'b_date' => '1991-03-02', 'b_city' => 'Zzyzxville'], $g);
    same($r['errors'], []);
    same($r['b']['date'], ['year' => 1991, 'month' => 3, 'day' => 2]);
    same([$r['b']['time'], $r['b']['place']], [null, null]);
});
check('request love: B with date, time and city is fully parsed', function () use ($g, $A) {
    $r = Request::parseLove($A + ['b_name' => 'Bob', 'b_date' => '1991-03-02', 'b_time' => '22:10', 'b_city' => 'Paris', 'b_lat' => '48.85', 'b_lon' => '2.35', 'b_tz' => 'Europe/Paris'], $g);
    same($r['errors'], []);
    same($r['b']['place']['tz'], 'Europe/Paris');
});
check('request love: tampered b_tz falls back to server-side geocoding', function () use ($g, $A) {
    $r = Request::parseLove($A + ['b_name' => 'Bob', 'b_date' => '1991-03-02', 'b_time' => '22:10', 'b_city' => 'Rome', 'b_lat' => '41.9', 'b_lon' => '12.5', 'b_tz' => '../../etc'], $g);
    if (($r['b']['place']['tz'] ?? null) === '../../etc') {
        throw new RuntimeException('tz accepted');
    }
});
check('request love: invalid optional fields are errors', function () use ($g, $A) {
    same(count(Request::parseLove($A + ['b_name' => 'Bob', 'b_date' => '1991-02-31'], $g)['errors']), 1);
    same(count(Request::parseLove($A + ['b_name' => 'Bob', 'b_date' => '1991-02-01', 'b_time' => '25:99'], $g)['errors']), 1);
});
check('request love: name rules', function () use ($g, $A) {
    $try = fn (string $name): int => count(Request::parseLove($A + ['b_name' => $name], $g)['errors']);
    same($try(str_repeat('a', 40)), 0);
    same($try(str_repeat('a', 41)), 1);
    same($try(str_repeat('é', 40)), 0);
    same($try('12345'), 1);
    same($try('   '), 1);
    same($try("Bob\x07"), 1);
    same($try("\xff\xfe"), 1);
    same($try("José María"), 0);
});
check('request self: old queries still parse; mode key is ignored by parse()', function () use ($g) {
    $r = Request::parse(['mode' => 'self', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'], $g);
    same($r['errors'], []);
    same(array_keys($r['input']), ['year', 'month', 'day', 'hour', 'minute', 'lat', 'lon', 'tz', 'city']);
});
check('builders: SelfReading and LoveReading (pure, deterministic)', function () use ($g, $A) {
    $in = Request::parse(['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'], $g)['input'];
    $s = SelfReading::build($in, '2026-10-09');
    same(count($s['refs']), 5);
    same(count($s['affinity']['mostAffine']), 3);
    same(SelfReading::build($in, '2026-10-09'), $s);
    $old = SelfReading::build(['year' => 1500] + $in, '2026-10-09');
    same(count($old['refs']), 3);
    $p = Request::parseLove($A + ['b_name' => 'Silvia Pellico'], $g);
    $l = LoveReading::build($p['a'], $p['b'], '2026-10-09');
    same($l['affinity']['percent'] >= 0, true);
    same([$l['common'], $l['bio'], $l['charts']['b']], [null, null, null]);
    same(count($l['tarot']), 3);
    $p = Request::parseLove(array_merge($A, ['a_name' => 'Andrea Lorenzani']) + ['b_name' => 'Silvia Pellico', 'b_date' => '1991-03-02'], $g);
    $l = LoveReading::build($p['a'], $p['b'], '2026-10-09');
    same($l['affinity']['percent'], 48);
    same(count($l['bio']['cycles']), 3);
    same(array_column($l['common']['items'], 'body'), ['sun', 'moon']);
});

check('request: array query values do not raise warnings', function () use ($g) {
    set_error_handler(static function (int $n, string $s): never { throw new ErrorException($s); });
    try {
        $r = Request::parsePerson(['date' => ['x'], 'time' => ['y'], 'city' => ['z'], 'lat' => ['1'], 'tz' => ['q']], '', $g, true, '');
        same($r['person'], null);
        $r = Request::parsePerson(['b_date' => ['x'], 'b_city' => ['z']], 'b_', $g, false, '');
        same($r['errors'], []);
    } finally {
        restore_error_handler();
    }
});

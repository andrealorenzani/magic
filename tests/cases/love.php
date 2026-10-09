<?php
declare(strict_types=1);

use Magic\Astro\Zodiac;
use Magic\Content\Traits;
use Magic\Love\Common;
use Magic\Love\NameAffinity;
use Magic\Love\SignAffinity;

check('name affinity: owner example from counts', function () {
    $r = NameAffinity::fromCounts(['A' => 4, 'M' => 0, 'O' => 2, 'R' => 2, 'E' => 3]);
    same($r['slots'], [4, 0, 2, 2, 3]);
    same($r['start'], 40223);
    same($r['chain'], [40223, 4245, 669, 135, 48]);
    same($r['percent'], 48);
});
check('name affinity: Andrea Lorenzani + Silvia Pellico = 48 (case, accents, NFD, order)', function () {
    $r = NameAffinity::compute('Andrea Lorenzani', 'Silvia Pellico');
    same($r['counts'], ['A' => 4, 'M' => 0, 'O' => 2, 'R' => 2, 'E' => 3]);
    same($r['percent'], 48);
    same(NameAffinity::compute('ANDRÉA  lorenzani', 'silvia PELLICO')['percent'], 48);
    same(NameAffinity::compute("Andre\u{0301}a Lorenzani", "Silvia Pe\u{0301}llico")['percent'], 48);
    same(NameAffinity::compute('Silvia Pellico', 'Andrea Lorenzani'), $r);
    same(NameAffinity::normalize('Ærøy Straße Œuvre'), 'aeroystrasseoeuvre');
});
check('name affinity: step', function () {
    same([NameAffinity::step(40223), NameAffinity::step(4245), NameAffinity::step(669), NameAffinity::step(135)], [4245, 669, 135, 48]);
});
check('name affinity: carries', function () {
    $r = NameAffinity::fromCounts(['A' => 3, 'M' => 2, 'O' => 12, 'R' => 2, 'E' => 1]);
    same([$r['slots'], $r['start']], [[3, 3, 2, 2, 1], 33221]);
    $r = NameAffinity::fromCounts(['A' => 12, 'M' => 0, 'O' => 0, 'R' => 0, 'E' => 0]);
    same([$r['slots'], $r['chain'], $r['percent']], [[2, 1, 0, 0, 0], [21000, 3100, 410, 51], 51]);
    $r = NameAffinity::fromCounts(['A' => 0, 'M' => 9, 'O' => 15, 'R' => 0, 'E' => 0]);
    same([$r['slots'], $r['chain'], $r['percent']], [[0, 10, 5, 0, 0], [10500, 1550, 705, 75], 75]);
});
check('name affinity: degenerate cases', function () {
    $r = NameAffinity::fromCounts(['A' => 0, 'M' => 0, 'O' => 0, 'R' => 0, 'E' => 0]);
    same([$r['percent'], $r['noLetters']], [0, true]);
    $r = NameAffinity::fromCounts(['A' => 0, 'M' => 0, 'O' => 0, 'R' => 0, 'E' => 5]);
    same([$r['chain'], $r['percent'], $r['noLetters']], [[5], 5, false]);
    $r = NameAffinity::fromCounts(['A' => 0, 'M' => 0, 'O' => 1, 'R' => 0, 'E' => 0]);
    same([$r['start'], $r['chain'], $r['percent']], [100, [100], 100]);
    same(NameAffinity::compute('Xyz', 'Qwt')['noLetters'], true);
});
check('name affinity: termination property (1000 random count tuples)', function () {
    mt_srand(1);
    for ($i = 0; $i < 1000; $i++) {
        $r = NameAffinity::fromCounts(['A' => mt_rand(0, 80), 'M' => mt_rand(0, 80), 'O' => mt_rand(0, 80), 'R' => mt_rand(0, 80), 'E' => mt_rand(0, 80)]);
        if ($r['percent'] < 0 || $r['percent'] > 100) {
            throw new RuntimeException('percent out of range: ' . json_encode($r));
        }
        for ($k = 1; $k < count($r['chain']); $k++) {
            if ($r['chain'][$k] >= $r['chain'][$k - 1]) {
                throw new RuntimeException('chain not decreasing: ' . json_encode($r['chain']));
            }
        }
    }
});

check('sign affinity: hand-computed scores with all references in Aries', function () {
    $r = SignAffinity::rank(['sun' => 0, 'moon' => 0, 'ascendant' => 0, 'venus' => 0, 'mars' => 0]);
    $by = [];
    foreach ($r['ranking'] as $e) {
        $by[$e['sign']['name']] = $e;
    }
    same(count($r['ranking']), 11);
    same(isset($by['Aries']), false);
    near($by['Leo']['affinity'], 100, 1e-9);
    near($by['Sagittarius']['affinity'], 100, 1e-9);
    near($by['Libra']['affinity'], 59.0, 1e-9);
    near($by['Cancer']['affinity'], 50.25, 1e-9);
    near($by['Taurus']['affinity'], 48.5, 1e-9);
    same([$r['ranking'][0]['sign']['name'], $r['ranking'][1]['sign']['name']], ['Leo', 'Sagittarius']);
    near($by['Libra']['love'], 69.0, 1e-9);
    same($r['descendant'], 6);
    same(count($r['mostAffine']), 3);
    same(isset($r['soulmate']['sign']['name']), true);
    same(SignAffinity::rank(['sun' => 0, 'moon' => 0, 'ascendant' => 0, 'venus' => 0, 'mars' => 0]), $r);
});
check('sign affinity: dropping Venus and Mars renormalises', function () {
    $r = SignAffinity::rank(['sun' => 0, 'moon' => 0, 'ascendant' => 0]);
    same($r['ranking'][0]['sign']['name'], 'Leo');
    near($r['ranking'][0]['affinity'], 100, 1e-9);
});
check('sign affinity: ruler friendship is symmetric, same ruler = 100', function () {
    for ($a = 0; $a < 12; $a++) {
        for ($b = 0; $b < 12; $b++) {
            near(SignAffinity::ruler($a, $b), SignAffinity::ruler($b, $a), 1e-12);
        }
    }
    same(SignAffinity::ruler(1, 6), 100.0);
});

$pos = fn (int $i): array => Zodiac::fromLongitude($i * 30 + 5.0);
$chartOf = fn (int $sun, int $moon, ?int $asc = null): array => [
    'sun' => $pos($sun), 'moon' => $pos($moon), 'ascendant' => $asc === null ? null : $pos($asc), 'approx' => ['sun' => false, 'moon' => false],
];
check('common values: levels and points', function () use ($chartOf, $pos) {
    $level = function (int $other) use ($chartOf): array {
        $r = Common::between($chartOf(0, 0), $chartOf($other, $other));
        return [$r['items'][0]['level'], $r['items'][0]['points'], $r['items'][0]['traits']];
    };
    same($level(0), ['sign', 100, Traits::SIGNS['aries']]);
    same($level(4), ['element', 80, Traits::ELEMENTS['Fire']]);
    same($level(6)[0] . $level(6)[1], 'complement60');
    same($level(3)[0] . $level(3)[1], 'modality40');
    same($level(1)[0] . $level(1)[1], 'none20');
});
check('common values: partial data and score', function () use ($chartOf) {
    $r = Common::between($chartOf(0, 0, 4), $chartOf(0, 4));
    same(array_column($r['items'], 'body'), ['sun', 'moon']);
    same(count($r['basis']), 2);
    same($r['score'], 90);
    $r = Common::between($chartOf(0, 0, 4), $chartOf(0, 4, 4));
    same(array_column($r['items'], 'body'), ['sun', 'moon', 'ascendant']);
    $r = Common::between(['sun' => null, 'moon' => null, 'ascendant' => null], $chartOf(0, 0));
    same([$r['items'], $r['score']], [[], null]);
});

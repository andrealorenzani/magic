<?php
declare(strict_types=1);

use Arcana\Bio\Biorhythm;
use Arcana\Bio\Synchrony;

check('biorhythm: day numbers', function () {
    same(Biorhythm::dayNumber(1970, 1, 1), 0);
    same(Biorhythm::dayNumber(2000, 1, 1), 10957);
    same(Biorhythm::dayNumber(2000, 3, 1) - Biorhythm::dayNumber(2000, 2, 29), 1);
    same(Biorhythm::dayNumber(2000, 2, 29) - Biorhythm::dayNumber(2000, 2, 28), 1);
    same(Biorhythm::dayNumber(1900, 3, 1), -25508);
    same(Biorhythm::dayNumber(1900, 3, 1) - Biorhythm::dayNumber(1900, 2, 28), 1);
    same(Biorhythm::date(10957), '2000-01-01');
    same(Biorhythm::date(Biorhythm::dayNumber(2024, 2, 29)), '2024-02-29');
    same(Biorhythm::date(-25508), '1900-03-01');
});
check('biorhythm: cycle values', function () {
    $b = Biorhythm::dayNumber(1990, 1, 1);
    $v = fn (int $t, int $p): int => (int) round(Biorhythm::value($b, $b + $t, $p));
    same([$v(7, 23), $v(7, 28), $v(7, 33)], [94, 100, 97]);
    same([$v(0, 23), $v(0, 28), $v(0, 33)], [0, 0, 0]);
    same($v(21, 28), -100);
    same(abs($v(14, 28)), 0);
});
check('biorhythm: forPerson peaks, troughs and curve', function () {
    $b = Biorhythm::dayNumber(1990, 1, 1);
    $r = Biorhythm::forPerson($b, $b);
    same([$r['emotional']['nextPeak'], $r['emotional']['nextTrough']], ['1990-01-08', '1990-01-22']);
    same([$r['physical']['nextPeak'], $r['physical']['nextTrough']], ['1990-01-07', '1990-01-18']);
    same([$r['intellectual']['nextPeak'], $r['intellectual']['nextTrough']], ['1990-01-09', '1990-01-26']);
    same(count($r['emotional']['curve']), 31);
    same($r['emotional']['curve'][0], 0);
    same($r['emotional']['trend'], 'rising');
});
check('synchrony: identical birth dates', function () {
    $b = Biorhythm::dayNumber(1990, 1, 1);
    $s = Synchrony::pair($b, $b, $b);
    foreach ($s['cycles'] as $c) {
        near($c['sync'], 100, 1e-9);
        near($c['amplitude'], 100, 1e-9);
        same($c['peaksTogether'], true);
    }
    near($s['overall'], 100, 1e-9);
    same($s['band'], 'tune');
    same($s['cycles']['emotional']['nextBothHigh']['date'], '1990-01-04');
    same($s['cycles']['emotional']['nextBothLow']['date'], '1990-01-18');
});
check('synchrony: 14 days apart', function () {
    $a = Biorhythm::dayNumber(1990, 1, 1);
    $s = Synchrony::pair($a, $a + 14, $a);
    $e = $s['cycles']['emotional'];
    same($e['delta'], 14);
    near($e['sync'], 0.0, 1e-9);
    near($e['amplitude'], 0.0, 1e-9);
    same([$e['flat'], $e['nextCombinedPeak']], [true, null]);
    same($s['cycles']['physical']['delta'], -9);
    near($s['cycles']['physical']['sync'], 11.2, 0.1);
    near($s['cycles']['physical']['amplitude'], 33.5, 0.2);
    same($s['cycles']['intellectual']['delta'], 14);
    near($s['cycles']['intellectual']['sync'], 5.6, 0.1);
    near($s['overall'], 5.6, 0.1);
    same($s['band'], 'apart');
});
check('synchrony: combined-curve dates (delta 2)', function () {
    $a = Biorhythm::dayNumber(1990, 1, 1);
    $s = Synchrony::pair($a, $a + 2, Biorhythm::dayNumber(1990, 2, 1));
    same([$s['cycles']['emotional']['nextCombinedPeak'], $s['cycles']['emotional']['nextCombinedTrough']], ['1990-02-06', '1990-02-20']);
    near($s['cycles']['physical']['sync'], 92.7, 0.2);
});
check('synchrony: 28 days apart is fully in step for the 28-day cycle', function () {
    $a = Biorhythm::dayNumber(1990, 1, 1);
    near(Synchrony::pair($a, $a + 28, $a)['cycles']['emotional']['sync'], 100, 1e-9);
});

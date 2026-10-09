<?php
declare(strict_types=1);

use Magic\Content\TarotDeck;
use Magic\Tarot\Reading;

$cardIds = fn (array $draw): array => array_map(fn (array $d): string => $d['card']['id'] . ($d['reversed'] ? '*' : ''), $draw);

check('tarot: deterministic, three distinct cards, dates roll forward', function () use ($cardIds) {
    $a = Reading::draw('ann|bob|1990-07-15|', '2026-10-09');
    same($a, Reading::draw('ann|bob|1990-07-15|', '2026-10-09'));
    same(count($a), 3);
    same(array_column($a, 'date'), ['2026-10-09', '2026-10-10', '2026-10-11']);
    same(count(array_unique(array_map(fn (array $d): string => $d['card']['id'], $a))), 3);
    $next = Reading::draw('ann|bob|1990-07-15|', '2026-10-10');
    same(array_column($next, 'date'), ['2026-10-10', '2026-10-11', '2026-10-12']);
    if ($cardIds($next) === $cardIds($a)) {
        throw new RuntimeException('draw did not change with the date');
    }
    foreach ($a as $d) {
        same($d['meaning'], $d['reversed'] ? $d['card']['reversed'] : $d['card']['upright']);
    }
});
check('tarot: deck has 22 unique cards with texts', function () {
    same(count(TarotDeck::CARDS), 22);
    same(count(array_unique(array_column(TarotDeck::CARDS, 'id'))), 22);
    same(array_column(TarotDeck::CARDS, 'number'), range(0, 21));
    foreach (TarotDeck::CARDS as $c) {
        if (trim($c['upright']) === '' || trim($c['reversed']) === '' || trim($c['name']) === '') {
            throw new RuntimeException("empty text for {$c['id']}");
        }
    }
    same(TarotDeck::CARDS[6]['name'], 'The Lovers');
});
check('tarot: indexes always in range, golden values frozen', function () use ($cardIds) {
    foreach (range(0, 200) as $i) {
        foreach (Reading::draw("seed$i", '2026-01-01') as $d) {
            if ($d['card']['number'] < 0 || $d['card']['number'] > 21) {
                throw new RuntimeException('index out of range');
            }
        }
    }
    same($cardIds(Reading::draw('ann|bob|1990-07-15|', '2026-10-09')), ['tower', 'hierophant*', 'hermit']);
    same($cardIds(Reading::draw('anna|bob|1990-07-15|', '2026-10-09')), ['fool', 'tower', 'judgement*']);
});

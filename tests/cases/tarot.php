<?php
declare(strict_types=1);

use Magic\Content\TarotDeck;
use Magic\Content\TarotSpread;
use Magic\Share\ShareLink;
use Magic\Tarot\Reading;

$spreadIds = fn (array $spread): array => array_map(fn (array $d): string => $d['card']['id'] . ($d['reversed'] ? '*' : ''), $spread);

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
check('tarot spread: 132 position texts exist, 40-240 characters, all unique', function () {
    $seen = [];
    foreach (TarotDeck::CARDS as $c) {
        foreach (TarotSpread::ORDER as $pos) {
            foreach ([false, true] as $rev) {
                $t = TarotSpread::text($c['id'], $pos, $rev);
                $n = mb_strlen($t, 'UTF-8');
                if ($n < 40 || $n > 240) {
                    throw new RuntimeException("{$c['id']}/$pos length $n");
                }
                $seen[$t] = true;
            }
        }
    }
    same(count($seen), 132);
});
check('tarot spread: a missing card or position throws', function () {
    foreach ([['nope', 'past'], ['fool', 'later']] as [$id, $pos]) {
        try {
            TarotSpread::text($id, $pos, false);
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('expected exception');
    }
    same(array_keys(TarotSpread::POSITIONS), ['past', 'present', 'future']);
});
check('tarot spread: deterministic, three distinct cards in Past, Present, Future order', function () use ($spreadIds) {
    $a = Reading::spread('ann|bob|1990-07-15|', '2026-10-09');
    same($a, Reading::spread('ann|bob|1990-07-15|', '2026-10-09'));
    same(array_column($a, 'position'), ['past', 'present', 'future']);
    foreach ($a as $d) {
        same($d['text'], TarotSpread::text($d['card']['id'], $d['position'], $d['reversed']));
    }
    foreach (range(0, 499) as $i) {
        $s = Reading::spread("seed$i", '2026-01-01');
        same(count(array_unique(array_column(array_column($s, 'card'), 'number'))), 3);
    }
    if ($spreadIds(Reading::spread('ann|bob|1990-07-15|', '2026-10-10')) === $spreadIds($a)) {
        throw new RuntimeException('spread did not change with the day');
    }
});
check('tarot spread: golden values frozen', function () use ($spreadIds) {
    same($spreadIds(Reading::spread('ann|bob|1990-07-15|', '2026-10-09')), ['strength', 'moon', 'judgement']);
    same($spreadIds(Reading::spread('anna|bob|1990-07-15|', '2026-10-09')), ['moon', 'temperance*', 'hermit']);
});
check('tarot code: round trip for 500 spreads', function () {
    foreach (range(0, 499) as $i) {
        $s = Reading::spread("rt$i", '2026-03-0' . (1 + $i % 9));
        $code = ShareLink::tarotCode($s);
        $slots = ShareLink::parseTarot($code);
        same($slots === null, false);
        same(Reading::fromSlots($slots), $s);
    }
});
check('tarot code: parse table', function () {
    same(ShareLink::parseTarot('16u,5r,9u'), [['number' => 16, 'reversed' => false], ['number' => 5, 'reversed' => true], ['number' => 9, 'reversed' => false]]);
    same(ShareLink::parseTarot('0u,1r,21u') !== null, true);
    same(ShareLink::parseTarot('00u,01r,021u'), null);
    foreach (['16u,5r', '16u,16r,9u', '22u,1u,2u', '-1u,1u,2u', '16x,5r,9u', ' 16u,5r,9u', '', str_repeat('1u,', 1000), '16U,5R,9U', '16u,5r,9u,', '16u,5r,9u '] as $bad) {
        same(ShareLink::parseTarot($bad), null);
    }
});

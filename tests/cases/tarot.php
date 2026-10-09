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
    same($spreadIds(Reading::spread('ann|bob|1990-07-15|', '2026-10-09')), ['hanged-man', 'page-of-swords', 'five-of-cups']);
    same($spreadIds(Reading::spread('anna|bob|1990-07-15|', '2026-10-09')), ['seven-of-swords', 'temperance*', 'ten-of-swords']);
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
    same(ShareLink::parseTarot('22u,40r,77u'), [['number' => 22, 'reversed' => false], ['number' => 40, 'reversed' => true], ['number' => 77, 'reversed' => false]]);
    same(ShareLink::parseTarot('00u,01r,021u'), null);
    foreach (['16u,5r', '16u,16r,9u', '78u,1u,2u', '100u,1u,2u', '99u,98u,97u', '-1u,1u,2u', '16x,5r,9u', ' 16u,5r,9u', '', str_repeat('1u,', 1000), '16U,5R,9U', '16u,5r,9u,', '16u,5r,9u '] as $bad) {
        same(ShareLink::parseTarot($bad), null);
    }
});

check('tarot deck: 78 cards, 22 major + 56 minor, unique ids, names and numbers', function () {
    same(TarotDeck::COUNT, 78);
    $ids = [];
    $names = [];
    $suits = [];
    foreach (range(0, 77) as $n) {
        $c = TarotDeck::card($n);
        same($c['number'], $n);
        same($c['arcana'], $n < 22 ? 'major' : 'minor');
        foreach (['id', 'name', 'upright', 'reversed', 'mark'] as $k) {
            if (!is_string($c[$k]) || trim($c[$k]) === '') {
                throw new RuntimeException("card $n has no $k");
            }
        }
        $ids[$c['id']] = true;
        $names[$c['name']] = true;
        if ($n >= 22) {
            $suits[$c['suit']][$c['rank']] = true;
        }
    }
    same([count($ids), count($names)], [78, 78]);
    same(array_keys($suits), ['wands', 'cups', 'swords', 'pentacles']);
    foreach ($suits as $ranks) {
        same(count($ranks), 14);
    }
    same(TarotDeck::card(21)['name'], 'The World');
    same(TarotDeck::card(22)['name'], 'Ace of Wands');
    same(TarotDeck::card(35)['name'], 'King of Wands');
    same(TarotDeck::card(36)['name'], 'Ace of Cups');
    same(TarotDeck::card(50)['name'], 'Ace of Swords');
    same(TarotDeck::card(64)['name'], 'Ace of Pentacles');
    same(TarotDeck::card(77)['name'], 'King of Pentacles');
    foreach ([-1, 78] as $bad) {
        try {
            TarotDeck::card($bad);
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException("card $bad accepted");
    }
});
check('tarot minor: 78 x 3 positions x 2 orientations all have a text of 40-300 characters, minor ones all unique', function () {
    $seen = [];
    foreach (range(22, 77) as $n) {
        $c = TarotDeck::card($n);
        foreach (TarotSpread::ORDER as $pos) {
            foreach ([false, true] as $rev) {
                $t = TarotSpread::text($c['id'], $pos, $rev);
                $len = mb_strlen($t, 'UTF-8');
                if ($len < 40 || $len > 300) {
                    throw new RuntimeException("{$c['id']}/$pos length $len");
                }
                $seen[$t] = true;
            }
        }
    }
    same(count($seen), 56 * 3 * 2);
    foreach (['nope-of-cups', 'ace-of-nothing', 'ace-of-cups-of-cups', 'ace-of-cups'] as $bad) {
        try {
            TarotSpread::text($bad, 'later', false);
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException("$bad / later accepted");
    }
});
check('tarot minor: content counts (14 ranks x 3 x 2, 4 suits x 3, 28 essence lines, 18 names)', function () {
    $m = Magic\Content\TarotMinor::class;
    same(count($m::RANK_TEXT), 14);
    same(count($m::SUIT_TEXT), 4);
    same(count($m::RANKS) + count($m::SUITS), 18);
    $strings = 0;
    foreach ($m::RANK_TEXT as $byPos) {
        same(array_keys($byPos), ['past', 'present', 'future']);
        foreach ($byPos as $o) {
            same(array_keys($o), ['up', 'rev']);
            $strings += 2;
        }
    }
    same($strings, 84);
    same(count($m::RANK_ESSENCE), 14);
    $all = [];
    foreach ($m::RANK_TEXT as $byPos) {
        foreach ($byPos as $o) {
            foreach ($o as $t) {
                $all[$t] = true;
            }
        }
    }
    same(count($all), 84);
});
check('tarot spread: draws from all 78 cards and keeps three distinct cards', function () {
    $seenMinor = false;
    $seenMajor = false;
    foreach (range(0, 499) as $i) {
        $s = Reading::spread("deck$i", '2026-05-05');
        same(count(array_unique(array_column(array_column($s, 'card'), 'number'))), 3);
        foreach ($s as $d) {
            same($d['text'], TarotSpread::text($d['card']['id'], $d['position'], $d['reversed']));
            $seenMinor = $seenMinor || $d['card']['number'] >= 22;
            $seenMajor = $seenMajor || $d['card']['number'] < 22;
        }
    }
    same([$seenMinor, $seenMajor], [true, true]);
});
check('tarot code: old codes keep their meaning, minor cards round trip through the compact code', function () {
    $s = Reading::fromSlots(ShareLink::parseTarot('16u,5r,9u'));
    same(array_column(array_column($s, 'card'), 'id'), ['tower', 'hierophant', 'hermit']);
    $slots = ShareLink::parseTarot('22u,40r,77u');
    $spread = Reading::fromSlots($slots);
    same(array_column(array_column($spread, 'card'), 'name'), ['Ace of Wands', 'Five of Cups', 'King of Pentacles']);
    same(ShareLink::tarotCode($spread), '22u,40r,77u');
    $r = Magic\Request::parseLove(['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Bob', 'b_date' => '1992-01-02'], new Magic\Geo\Geocoder(sys_get_temp_dir()));
    [$a, $b] = [$r['a'], $r['b']];
    $code = Magic\Share\ShareCode::encodeLove($a, $b, $spread, '2026-10-09', true);
    $decoded = Magic\Share\ShareCode::decode($code);
    same($decoded === null, false);
    same($decoded['t'], '22u,40r,77u');
    same(Magic\Request::parseTarot($decoded)['slots'], $slots);
    same(Magic\Request::parseTarot(['t' => '78u,1r,2u'])['invalid'], true);
});

<?php
declare(strict_types=1);

namespace Arcana\Tarot;

use Arcana\Bio\Biorhythm;
use Arcana\Content\TarotDeck;

/** Deterministic multi-day tarot draw: stateless, same input and day give the same cards. */
final class Reading
{
    /** @return list<array{date:string, card:array, reversed:bool, meaning:string}> */
    public static function draw(string $seed, string $today, int $days = 3): array
    {
        $n = count(TarotDeck::CARDS);
        $start = Biorhythm::dayOf($today);
        $used = [];
        $out = [];
        for ($i = 0; $i < $days && $i < $n; $i++) {
            $date = Biorhythm::date($start + $i);
            $h = hash('sha256', $seed . '|' . $date . '|' . $i);
            $card = hexdec(substr($h, 0, 8)) % $n;
            $reversed = hexdec(substr($h, 8, 8)) % 2 === 1;
            while (in_array($card, $used, true)) {
                $card = ($card + 1) % $n;
            }
            $used[] = $card;
            $c = TarotDeck::CARDS[$card];
            $out[] = ['date' => $date, 'card' => $c, 'reversed' => $reversed, 'meaning' => $reversed ? $c['reversed'] : $c['upright']];
        }
        return $out;
    }
}

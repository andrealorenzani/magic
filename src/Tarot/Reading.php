<?php
declare(strict_types=1);

namespace Magic\Tarot;

use Magic\Content\TarotDeck;
use Magic\Content\TarotSpread;

/** Deterministic Past / Present / Future spread: stateless, same input and day give the same cards. */
final class Reading
{
    /** @return list<array{position:string, card:array, reversed:bool, text:string}> */
    public static function spread(string $seed, string $today): array
    {
        $n = count(TarotDeck::CARDS);
        $used = [];
        $slots = [];
        foreach (TarotSpread::ORDER as $i => $position) {
            $h = hash('sha256', $seed . '|' . $today . '|' . $position);
            $card = hexdec(substr($h, 0, 8)) % $n;
            $reversed = hexdec(substr($h, 8, 8)) % 2 === 1;
            while (in_array($card, $used, true)) {
                $card = ($card + 1) % $n;
            }
            $used[] = $card;
            $slots[] = ['number' => $card, 'reversed' => $reversed];
        }
        return self::fromSlots($slots);
    }

    /**
     * Turns three already validated slots (card number 0-21, orientation) into the spread shape.
     * @param list<array{number:int, reversed:bool}> $slots
     * @return list<array{position:string, card:array, reversed:bool, text:string}>
     */
    public static function fromSlots(array $slots): array
    {
        $out = [];
        foreach (TarotSpread::ORDER as $i => $position) {
            $card = TarotDeck::CARDS[$slots[$i]['number']];
            $rev = (bool) $slots[$i]['reversed'];
            $out[] = ['position' => $position, 'card' => $card, 'reversed' => $rev, 'text' => TarotSpread::text($card['id'], $position, $rev)];
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Magic\Love;

/** How peaceful two people are together, as a percentage. */
final class Harmony
{
    private const CALM = ['taurus', 'cancer', 'virgo', 'libra', 'capricorn', 'pisces'];
    private const SOFT_SUITS = ['cups', 'pentacles'];
    private const SOFT_MAJORS = [2, 3, 6, 9, 11, 14, 17, 19, 21];
    private const HARD_MAJORS = [5, 7, 8, 13, 15, 16, 18];

    /**
     * @param ?array<string,mixed> $bio Synchrony::pair shape
     * @param ?array<string,mixed> $chartA Chart::partial shape
     * @param ?array<string,mixed> $chartB Chart::partial shape
     * @param list<array{position:string, card:array, reversed:bool}> $tarot
     */
    public static function compute(?array $bio, ?array $chartA, ?array $chartB, array $tarot): int
    {
        $parts = [];
        if ($bio !== null) {
            $parts[] = [4, self::cycles($bio)];
        }
        $signs = self::signs($chartA, $chartB);
        if ($signs !== null) {
            $parts[] = [3, $signs];
        }
        $parts[] = [3, self::cards($tarot)];
        $w = 0;
        $sum = 0.0;
        foreach ($parts as [$weight, $value]) {
            $w += $weight;
            $sum += $weight * $value;
        }
        return (int) max(1, min(99, round($sum / $w)));
    }

    /** @param array<string,mixed> $bio */
    private static function cycles(array $bio): float
    {
        $s = 0.0;
        foreach ($bio['cycles'] as $c) {
            $s += (100 - ($c['curveA'][0] * $c['curveB'][0]) / 100) / 2;
        }
        return $s / max(1, count($bio['cycles']));
    }

    private static function signs(?array $chartA, ?array $chartB): ?float
    {
        if ($chartA === null || $chartB === null) {
            return null;
        }
        $scores = [];
        foreach (['sun', 'moon'] as $body) {
            $a = $chartA[$body]['id'] ?? null;
            $b = $chartB[$body]['id'] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            $calm = (int) in_array($a, self::CALM, true) + (int) in_array($b, self::CALM, true);
            $scores[] = [20, 85, 100][$calm];
        }
        return $scores ? array_sum($scores) / count($scores) : null;
    }

    /** @param list<array{position:string, card:array, reversed:bool}> $tarot */
    private static function cards(array $tarot): float
    {
        $weights = ['past' => 1, 'present' => 2, 'future' => 3];
        $w = 0;
        $s = 0.0;
        foreach ($tarot as $t) {
            $c = $t['card'];
            if ($c['suit'] !== null) {
                $tone = in_array($c['suit'], self::SOFT_SUITS, true) ? 1 : -1;
            } else {
                $tone = in_array($c['number'], self::SOFT_MAJORS, true) ? 1 : (in_array($c['number'], self::HARD_MAJORS, true) ? -1 : 0);
            }
            $score = match ($tone) { 1 => 100, -1 => 20, default => 55 };
            if ($t['reversed']) {
                $score = ($score + 50) / 2;
            }
            $pw = $weights[$t['position']] ?? 1;
            $w += $pw;
            $s += $pw * $score;
        }
        return $w ? $s / $w : 50.0;
    }
}

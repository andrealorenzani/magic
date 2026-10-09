<?php
declare(strict_types=1);

namespace Magic\Love;

use Magic\Astro\Zodiac;
use Magic\Content\Traits;

/**
 * Transparent sign-vs-sign scoring from tradition: aspect between signs (60%),
 * friendship of the ruling planets (25%) and modality (15%). No randomness.
 */
final class SignAffinity
{
    private const ASPECT = [0 => 70, 1 => 35, 2 => 80, 3 => 40, 4 => 100, 5 => 30, 6 => 65];

    private const RULER = ['Mars', 'Venus', 'Mercury', 'Moon', 'Sun', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Saturn', 'Jupiter'];

    /** Classical friendship: [friends, enemies]; everything else is neutral. */
    private const FRIENDSHIP = [
        'Sun' => [['Moon', 'Mars', 'Jupiter'], ['Venus', 'Saturn']],
        'Moon' => [['Sun', 'Mercury'], []],
        'Mars' => [['Sun', 'Moon', 'Jupiter'], ['Mercury']],
        'Mercury' => [['Sun', 'Venus'], ['Moon']],
        'Jupiter' => [['Sun', 'Moon', 'Mars'], ['Mercury', 'Venus']],
        'Venus' => [['Mercury', 'Saturn'], ['Sun', 'Moon']],
        'Saturn' => [['Mercury', 'Venus'], ['Sun', 'Moon', 'Mars']],
    ];

    private const W_AFFINITY = ['sun' => 3, 'moon' => 3, 'ascendant' => 2, 'venus' => 2, 'mars' => 1];
    private const W_LOVE = ['venus' => 3, 'moon' => 3, 'sun' => 2, 'mars' => 2, 'ascendant' => 1];

    /** Distance (0-6) between two sign indexes. */
    public static function distance(int $r, int $x): int
    {
        $d = (($x - $r) % 12 + 12) % 12;
        return min($d, 12 - $d);
    }

    /** Friendship of the rulers of two signs, 0-100, symmetric. */
    public static function ruler(int $r, int $x): float
    {
        $a = self::RULER[$r];
        $b = self::RULER[$x];
        if ($a === $b) {
            return 100.0;
        }
        return (self::directed($a, $b) + self::directed($b, $a)) / 2;
    }

    private static function directed(string $from, string $to): float
    {
        [$friends, $enemies] = self::FRIENDSHIP[$from];
        return in_array($to, $friends, true) ? 100.0 : (in_array($to, $enemies, true) ? 0.0 : 50.0);
    }

    public static function modality(int $r, int $x): float
    {
        return Zodiac::SIGNS[$r]['modality'] === Zodiac::SIGNS[$x]['modality'] ? 50.0 : 100.0;
    }

    public static function pair(int $r, int $x): float
    {
        return 0.60 * self::ASPECT[self::distance($r, $x)] + 0.25 * self::ruler($r, $x) + 0.15 * self::modality($r, $x);
    }

    /**
     * @param array<string,int> $refs sign indexes by reference: sun, moon, ascendant, venus, mars (missing ones are dropped)
     * @return array{ranking: list<array<string,mixed>>, mostAffine: list<array<string,mixed>>, soulmate: array<string,mixed>, descendant: ?int}
     */
    public static function rank(array $refs): array
    {
        $sunSign = $refs['sun'];
        $descendant = isset($refs['ascendant']) ? ($refs['ascendant'] + 6) % 12 : null;
        $entries = [];
        for ($x = 0; $x < 12; $x++) {
            if ($x === $sunSign) {
                continue;
            }
            $affinity = self::weighted($refs, $x, self::W_AFFINITY);
            $love = self::weighted($refs, $x, self::W_LOVE);
            if ($descendant === $x) {
                $love = min(100.0, $love + 10.0);
            }
            $entries[] = [
                'index' => $x,
                'sign' => Zodiac::SIGNS[$x],
                'affinity' => $affinity,
                'love' => $love,
                'moonPair' => isset($refs['moon']) ? self::pair($refs['moon'], $x) : 0.0,
                'reasons' => self::reasons($refs, $x),
            ];
        }
        $by = static function (string $key) use ($entries): array {
            usort($entries, static fn (array $p, array $q): int =>
                [round($q[$key], 9), round($q['moonPair'], 9), $p['index']] <=> [round($p[$key], 9), round($p['moonPair'], 9), $q['index']]);
            return $entries;
        };
        $ranking = $by('affinity');
        $loved = $by('love');
        return [
            'ranking' => $ranking,
            'mostAffine' => array_slice($ranking, 0, 3),
            'soulmate' => $loved[0],
            'descendant' => $descendant,
        ];
    }

    /** @param array<string,int> $refs @param array<string,int> $weights */
    private static function weighted(array $refs, int $x, array $weights): float
    {
        $sum = 0.0;
        $total = 0;
        foreach ($weights as $ref => $w) {
            if (isset($refs[$ref])) {
                $sum += $w * self::pair($refs[$ref], $x);
                $total += $w;
            }
        }
        return $sum / $total;
    }

    /** The two references contributing most to the affinity score. @return list<array<string,mixed>> */
    private static function reasons(array $refs, int $x): array
    {
        $contrib = [];
        foreach (self::W_AFFINITY as $ref => $w) {
            if (isset($refs[$ref])) {
                $contrib[$ref] = $w * self::pair($refs[$ref], $x);
            }
        }
        arsort($contrib);
        $out = [];
        foreach (array_slice(array_keys($contrib), 0, 2) as $ref) {
            $r = $refs[$ref];
            $rs = self::ruler($r, $x);
            $out[] = [
                'ref' => $ref,
                'refSign' => Zodiac::SIGNS[$r]['name'],
                'aspect' => Traits::ASPECTS[self::distance($r, $x)],
                'rulers' => self::RULER[$r] === self::RULER[$x] ? self::RULER[$r] : self::RULER[$r] . ' and ' . self::RULER[$x],
                'ruler' => self::RULER[$r] === self::RULER[$x] ? 'the same' : ($rs >= 100 ? 'friends' : ($rs > 50 ? 'friendly' : ($rs == 50.0 ? 'neutral' : ($rs > 0 ? 'tense' : 'enemies')))),
            ];
        }
        return $out;
    }
}

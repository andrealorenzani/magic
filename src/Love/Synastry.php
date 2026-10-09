<?php
declare(strict_types=1);

namespace Magic\Love;

use Magic\Astro\Aspects;
use Magic\Content\Aspects as AspectText;

/** Aspects between the charts of two people. */
final class Synastry
{
    public const ORDER = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'midheaven'];
    /** An aspect is kept when at least one of its two bodies is one of these. */
    public const PERSONAL = ['sun', 'moon', 'venus', 'mars', 'ascendant'];
    public const MAX_ROWS = 12;

    /**
     * @param array<string,float> $lonsA Chart::longitudes() of the first person
     * @param array<string,float> $lonsB Chart::longitudes() of the second person
     * @return array{rows: list<array<string,mixed>>, total: int, counts: array{harmonious:int, tense:int, neutral:int}, approx: bool, available: bool}
     */
    public static function between(array $lonsA, array $lonsB, int $limit = self::MAX_ROWS): array
    {
        $all = [];
        foreach (self::ORDER as $ia => $bodyA) {
            if (!isset($lonsA[$bodyA])) {
                continue;
            }
            foreach (self::ORDER as $ib => $bodyB) {
                if (!isset($lonsB[$bodyB]) || !(in_array($bodyA, self::PERSONAL, true) || in_array($bodyB, self::PERSONAL, true))) {
                    continue;
                }
                $asp = Aspects::between($lonsA[$bodyA], $lonsB[$bodyB]);
                if ($asp !== null) {
                    $all[] = [
                        'a' => $bodyA, 'b' => $bodyB, 'type' => $asp['type'], 'orb' => $asp['orb'],
                        'tone' => AspectText::TONE[$asp['type']], 'order' => $ia * 100 + $ib,
                    ];
                }
            }
        }
        usort($all, static fn (array $x, array $y): int => [$x['orb'], $x['order']] <=> [$y['orb'], $y['order']]);
        $counts = ['harmonious' => 0, 'tense' => 0, 'neutral' => 0];
        foreach ($all as $row) {
            $counts[$row['tone']]++;
        }
        return [
            'rows' => array_slice($all, 0, $limit),
            'total' => count($all),
            'counts' => $counts,
            'approx' => !isset($lonsA['ascendant']) || !isset($lonsB['ascendant']),
            'available' => true,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Magic\Astro;

/** Angular relations between two ecliptic longitudes. */
final class Aspects
{
    /** type => [exact angle in degrees, largest orb in degrees] */
    public const TYPES = [
        'conjunction' => [0.0, 8.0],
        'sextile' => [60.0, 5.0],
        'square' => [90.0, 7.0],
        'trine' => [120.0, 7.0],
        'opposition' => [180.0, 8.0],
    ];

    /** @return ?array{type: string, orb: float} null when the two longitudes form no aspect */
    public static function between(float $lonA, float $lonB): ?array
    {
        $d = abs(Angles::norm360($lonA) - Angles::norm360($lonB));
        $sep = $d > 180.0 ? 360.0 - $d : $d;
        foreach (self::TYPES as $type => [$angle, $limit]) {
            $orb = round(abs($sep - $angle), 4);
            if ($orb <= $limit) {
                return ['type' => $type, 'orb' => $orb];
            }
        }
        return null;
    }
}

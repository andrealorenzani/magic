<?php
declare(strict_types=1);

namespace Magic\Earth;

/** Distance between two places, in km; coordinates in degrees at the boundary. */
final class Distance
{
    public const EARTH_RADIUS_KM = 6371.0088;
    public const KM_PER_MILE = 1.609344;

    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lon2 - $lon1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    public static function miles(float $km): float
    {
        return $km / self::KM_PER_MILE;
    }
}

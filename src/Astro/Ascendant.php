<?php
declare(strict_types=1);

namespace Magic\Astro;

final class Ascendant
{
    /** Greenwich sidereal angle, degrees. */
    public static function gmst(float $jd): float
    {
        $T = Angles::centuries($jd);
        return Angles::norm360(
            280.46061837 + 360.98564736629 * ($jd - 2451545.0) + 0.000387933 * $T * $T - $T ** 3 / 38710000
        );
    }

    /**
     * Ecliptic longitude of the Ascendant in degrees.
     * @param float $lat degrees north
     * @param float $lon degrees east
     */
    public static function longitude(float $jd, float $lat, float $lon): float
    {
        $ramc = Angles::rad(Angles::norm360(self::gmst($jd) + $lon));
        $eps = Angles::rad(Angles::obliquity(Angles::centuries($jd)));
        $phi = Angles::rad($lat);
        $asc = atan2(cos($ramc), -(sin($ramc) * cos($eps) + tan($phi) * sin($eps)));
        return Angles::norm360(rad2deg($asc));
    }
}

<?php
declare(strict_types=1);

namespace Magic\Astro;

/** Time and angle helpers shared by all astronomy classes. Angles are in degrees at the API boundary. */
final class Angles
{
    public static function rad(float $deg): float
    {
        return $deg * M_PI / 180.0;
    }

    /** Normalize to [0, 360). */
    public static function norm360(float $deg): float
    {
        $r = fmod($deg, 360.0);
        return $r < 0 ? $r + 360.0 : $r;
    }

    /** Julian Day from a Unix timestamp (UTC seconds). */
    public static function julianDay(int $unix): float
    {
        return $unix / 86400.0 + 2440587.5;
    }

    /** Julian centuries since J2000.0. */
    public static function centuries(float $jd): float
    {
        return ($jd - 2451545.0) / 36525.0;
    }

    /** Approximate nutation in longitude, degrees (±0.003°). */
    public static function nutationLongitude(float $T): float
    {
        return -0.00478 * sin(self::rad(125.04 - 1934.136 * $T));
    }

    /** Mean obliquity of the ecliptic, degrees. */
    public static function obliquity(float $T): float
    {
        return 23.4392911 - 0.0130042 * $T;
    }
}

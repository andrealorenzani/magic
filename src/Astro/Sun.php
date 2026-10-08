<?php
declare(strict_types=1);

namespace Arcana\Astro;

final class Sun
{
    /** Apparent geocentric ecliptic longitude in degrees (Meeus ch. 25, low precision). */
    public static function longitude(float $jd): float
    {
        $T = Angles::centuries($jd);
        $L0 = 280.46646 + 36000.76983 * $T + 0.0003032 * $T * $T;
        $M = Angles::rad(357.52911 + 35999.05029 * $T - 0.0001537 * $T * $T);
        $C = (1.914602 - 0.004817 * $T - 0.000014 * $T * $T) * sin($M)
            + (0.019993 - 0.000101 * $T) * sin(2 * $M)
            + 0.000289 * sin(3 * $M);
        $omega = Angles::rad(125.04 - 1934.136 * $T);
        return Angles::norm360($L0 + $C - 0.00569 - 0.00478 * sin($omega));
    }
}

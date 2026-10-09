<?php
declare(strict_types=1);

namespace Magic\Astro;

final class MeanNode
{
    /** Mean longitude of the Moon's ascending (north) node, degrees (Meeus 47.7). South node = +180. */
    public static function longitude(float $jd): float
    {
        $T = Angles::centuries($jd);
        return Angles::norm360(125.04452 - 1934.136261 * $T + 0.0020708 * $T * $T + $T * $T * $T / 450000.0);
    }
}

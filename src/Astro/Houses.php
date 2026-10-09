<?php
declare(strict_types=1);

namespace Magic\Astro;

/** Midheaven and whole-sign houses. */
final class Houses
{
    /**
     * Ecliptic longitude of the Midheaven in degrees.
     * @param float $lon degrees east
     */
    public static function midheaven(float $jd, float $lon): float
    {
        $ramc = Angles::norm360(Ascendant::gmst($jd) + $lon);
        return self::midheavenFromRamc($ramc, Angles::obliquity(Angles::centuries($jd)));
    }

    /** Midheaven longitude (degrees) for a right ascension of the Midheaven and an obliquity, both in degrees. */
    public static function midheavenFromRamc(float $ramcDeg, float $obliquityDeg): float
    {
        $ramc = Angles::rad($ramcDeg);
        return Angles::norm360(rad2deg(atan2(sin($ramc), cos($ramc) * cos(Angles::rad($obliquityDeg)))));
    }

    /** House number 1-12 of a body: the sign of the Ascendant is house 1, the next sign house 2, and so on. */
    public static function wholeSign(float $ascLon, float $bodyLon): int
    {
        $asc = (int) floor(Angles::norm360($ascLon) / 30.0);
        $body = (int) floor(Angles::norm360($bodyLon) / 30.0);
        return ($body - $asc + 12) % 12 + 1;
    }
}

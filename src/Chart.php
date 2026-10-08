<?php
declare(strict_types=1);

namespace Arcana;

use Arcana\Astro\Angles;
use Arcana\Astro\Ascendant;
use Arcana\Astro\Moon;
use Arcana\Astro\Sun;
use Arcana\Astro\Zodiac;
use Arcana\Time\Zone;

/** Computes the "big three" for a birth. This is the single public entry point of the core. */
final class Chart
{
    /**
     * @return array{sun: array, moon: array, ascendant: array, utc: string, polar: bool}
     */
    public static function compute(
        int $year, int $month, int $day, int $hour, int $minute,
        float $lat, float $lon, string $timeZone
    ): array {
        $unix = Zone::toUnix($year, $month, $day, $hour, $minute, $timeZone);
        $jd = Angles::julianDay($unix);
        return [
            'utc' => gmdate('Y-m-d H:i', $unix),
            'sun' => Zodiac::fromLongitude(Sun::longitude($jd)),
            'moon' => Zodiac::fromLongitude(Moon::longitude($jd)),
            'ascendant' => Zodiac::fromLongitude(Ascendant::longitude($jd, $lat, $lon)),
            // The Ascendant is unreliable beyond the polar circles.
            'polar' => abs($lat) > 66.0,
        ];
    }
}

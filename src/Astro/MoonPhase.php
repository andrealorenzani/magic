<?php
declare(strict_types=1);

namespace Magic\Astro;

/** Phase of the Moon from the angle between the Moon and the Sun. */
final class MoonPhase
{
    /** Half-width in degrees of the window around new, first quarter, full and last quarter. */
    public const MAIN_HALF_WIDTH = 10.0;

    public const PHASES = [
        'new', 'waxing-crescent', 'first-quarter', 'waxing-gibbous',
        'full', 'waning-gibbous', 'last-quarter', 'waning-crescent',
    ];

    /**
     * @return array{angle: float, illumination: float, phase: string} angle 0-360 degrees (Moon ahead of the Sun),
     *         illumination 0-1
     */
    public static function at(float $jd): array
    {
        $angle = Angles::norm360(Moon::longitude($jd) - Sun::longitude($jd));
        return [
            'angle' => $angle,
            'illumination' => (1.0 - cos(Angles::rad($angle))) / 2.0,
            'phase' => self::name($angle),
        ];
    }

    public static function name(float $angle): string
    {
        $a = Angles::norm360($angle);
        $w = self::MAIN_HALF_WIDTH;
        foreach ([0 => 'new', 90 => 'first-quarter', 180 => 'full', 270 => 'last-quarter'] as $centre => $name) {
            $d = abs($a - $centre);
            if (min($d, 360.0 - $d) <= $w) {
                return $name;
            }
        }
        // Between the main phases: crescent, gibbous.
        return match (true) {
            $a < 90 => 'waxing-crescent',
            $a < 180 => 'waxing-gibbous',
            $a < 270 => 'waning-gibbous',
            default => 'waning-crescent',
        };
    }
}

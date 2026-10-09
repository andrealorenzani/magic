<?php
declare(strict_types=1);

namespace Magic;

use Magic\Astro\Angles;
use Magic\Astro\Ascendant;
use Magic\Astro\MeanNode;
use Magic\Astro\Moon;
use Magic\Astro\Planets;
use Magic\Astro\Sun;
use Magic\Astro\Zodiac;
use Magic\Time\Zone;

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

    /**
     * compute() plus the other planets (1800-2100 only) and the mean north node.
     * @return array<string,mixed>
     */
    public static function full(
        int $year, int $month, int $day, int $hour, int $minute,
        float $lat, float $lon, string $timeZone
    ): array {
        $chart = self::compute($year, $month, $day, $hour, $minute, $lat, $lon, $timeZone);
        $jd = Angles::julianDay(Zone::toUnix($year, $month, $day, $hour, $minute, $timeZone));
        $supported = Planets::supports($jd);
        $planets = [];
        if ($supported) {
            foreach (Planets::BODIES as $id) {
                $diff = Planets::longitude($id, $jd + 0.5) - Planets::longitude($id, $jd - 0.5);
                if ($diff > 180.0) {
                    $diff -= 360.0;
                } elseif ($diff < -180.0) {
                    $diff += 360.0;
                }
                $planets[$id] = [
                    'position' => Zodiac::fromLongitude(Planets::longitude($id, $jd)),
                    'retrograde' => $diff < 0,
                ];
            }
        }
        return $chart + [
            'planetsSupported' => $supported,
            'planets' => $planets,
            'node' => Zodiac::fromLongitude(MeanNode::longitude($jd)),
        ];
    }

    /**
     * Chart from possibly incomplete data. With time AND place it equals compute() (three signs).
     * Otherwise there is no Ascendant and Sun/Moon are taken at 12:00 UTC of the date;
     * approx[body] is true when the sign changes during that UTC day.
     * @return array{sun: ?array, moon: ?array, ascendant: ?array, approx: array{sun: bool, moon: bool}}
     */
    public static function partial(
        int $year, int $month, int $day, ?int $hour, ?int $minute,
        ?float $lat, ?float $lon, ?string $timeZone
    ): array {
        if ($hour !== null && $minute !== null && $lat !== null && $lon !== null && $timeZone !== null) {
            $c = self::compute($year, $month, $day, $hour, $minute, $lat, $lon, $timeZone);
            return ['sun' => $c['sun'], 'moon' => $c['moon'], 'ascendant' => $c['ascendant'], 'approx' => ['sun' => false, 'moon' => false]];
        }
        $start = Angles::julianDay(gmmktime(0, 0, 0, $month, $day, $year));
        $sign = static fn (float $l): int => Zodiac::fromLongitude($l)['index'];
        return [
            'sun' => Zodiac::fromLongitude(Sun::longitude($start + 0.5)),
            'moon' => Zodiac::fromLongitude(Moon::longitude($start + 0.5)),
            'ascendant' => null,
            'approx' => [
                'sun' => $sign(Sun::longitude($start)) !== $sign(Sun::longitude($start + 1.0)),
                'moon' => $sign(Moon::longitude($start)) !== $sign(Moon::longitude($start + 1.0)),
            ],
        ];
    }
}

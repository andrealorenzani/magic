<?php
declare(strict_types=1);

namespace Magic\Astro;

use InvalidArgumentException;

/** Geocentric ecliptic longitudes of Mercury..Pluto (1800-2100), accurate enough for sign placement. */
final class Planets
{
    public const BODIES = ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];

    /** JD at 1800-01-01 00:00 UT and at 2101-01-01 00:00 UT (end of 2100-12-31). */
    private const JD_FROM = 2378496.5;
    private const JD_TO = 2488434.5;

    /** [a, da, e, de, I, dI, L, dL, varpi, dvarpi, Omega, dOmega]; rates per Julian century. */
    private const ELEMENTS = [
        'mercury' => [0.38709927, 0.00000037, 0.20563593, 0.00001906, 7.00497902, -0.00594749, 252.25032350, 149472.67411175, 77.45779628, 0.16047689, 48.33076593, -0.12534081],
        'venus' => [0.72333566, 0.00000390, 0.00677672, -0.00004107, 3.39467605, -0.00078890, 181.97909950, 58517.81538729, 131.60246718, 0.00268329, 76.67984255, -0.27769418],
        'earth' => [1.00000261, 0.00000562, 0.01671123, -0.00004392, -0.00001531, -0.01294668, 100.46457166, 35999.37244981, 102.93768193, 0.32327364, 0.0, 0.0],
        'mars' => [1.52371034, 0.00001847, 0.09339410, 0.00007882, 1.84969142, -0.00813131, -4.55343205, 19140.30268499, -23.94362959, 0.44441088, 49.55953891, -0.29257343],
        'jupiter' => [5.20288700, -0.00011607, 0.04838624, -0.00013253, 1.30439695, -0.00183714, 34.39644051, 3034.74612775, 14.72847983, 0.21252668, 100.47390909, 0.20469106],
        'saturn' => [9.53667594, -0.00125060, 0.05386179, -0.00050991, 2.48599187, 0.00193609, 49.95424423, 1222.49362201, 92.59887831, -0.41897216, 113.66242448, -0.28867794],
        'uranus' => [19.18916464, -0.00196176, 0.04725744, -0.00004397, 0.77263783, -0.00242939, 313.23810451, 428.48202785, 170.95427630, 0.40805281, 74.01692503, 0.04240589],
        'neptune' => [30.06992276, 0.00026291, 0.00859048, 0.00005105, 1.77004347, 0.00035372, -55.12002969, 218.45945325, 44.96476227, -0.32241464, 131.78422574, -0.00508664],
        'pluto' => [39.48211675, -0.00031596, 0.24882730, 0.00005170, 17.14001206, 0.00004818, 238.92903833, 145.20780515, 224.06891629, -0.04062942, 110.30393684, -0.01183482],
    ];

    public static function supports(float $jd): bool
    {
        return $jd >= self::JD_FROM && $jd < self::JD_TO;
    }

    /** Tropical geocentric longitude of date, degrees in [0, 360). */
    public static function longitude(string $id, float $jd): float
    {
        if (!in_array($id, self::BODIES, true)) {
            throw new InvalidArgumentException("Unknown planet: $id");
        }
        $T = Angles::centuries($jd);
        [$px, $py] = self::heliocentric($id, $T);
        [$ex, $ey] = self::heliocentric('earth', $T);
        $lambda = rad2deg(atan2($py - $ey, $px - $ex));
        $precession = 1.3969713 * $T + 0.0003086 * $T * $T;
        return Angles::norm360($lambda + $precession + Angles::nutationLongitude($T));
    }

    /** @return array<string,float> */
    public static function all(float $jd): array
    {
        $out = [];
        foreach (self::BODIES as $id) {
            $out[$id] = self::longitude($id, $jd);
        }
        return $out;
    }

    /** Eccentric anomaly E (radians) from mean anomaly M and eccentricity e. */
    public static function kepler(float $M, float $e): float
    {
        $E = $M + $e * sin($M);
        for ($i = 0; $i < 30; $i++) {
            $dE = ($M - ($E - $e * sin($E))) / (1 - $e * cos($E));
            $E += $dE;
            if (abs($dE) < 1e-12) {
                break;
            }
        }
        return $E;
    }

    /** Heliocentric x, y (AU) in the J2000 ecliptic frame. @return array{0: float, 1: float} */
    private static function heliocentric(string $id, float $T): array
    {
        [$a0, $a1, $e0, $e1, $i0, $i1, $l0, $l1, $w0, $w1, $o0, $o1] = self::ELEMENTS[$id];
        $a = $a0 + $a1 * $T;
        $e = $e0 + $e1 * $T;
        $I = Angles::rad($i0 + $i1 * $T);
        $L = $l0 + $l1 * $T;
        $varpi = $w0 + $w1 * $T;
        $Om = $o0 + $o1 * $T;
        $w = Angles::rad($varpi - $Om);
        $O = Angles::rad($Om);
        $M = fmod($L - $varpi, 360.0);
        if ($M > 180.0) {
            $M -= 360.0;
        } elseif ($M <= -180.0) {
            $M += 360.0;
        }
        $E = self::kepler(Angles::rad($M), $e);
        $xp = $a * (cos($E) - $e);
        $yp = $a * sqrt(1 - $e * $e) * sin($E);
        $cw = cos($w); $sw = sin($w); $cO = cos($O); $sO = sin($O); $cI = cos($I);
        return [
            ($cw * $cO - $sw * $sO * $cI) * $xp + (-$sw * $cO - $cw * $sO * $cI) * $yp,
            ($cw * $sO + $sw * $cO * $cI) * $xp + (-$sw * $sO + $cw * $cO * $cI) * $yp,
        ];
    }
}

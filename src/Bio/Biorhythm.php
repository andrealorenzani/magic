<?php
declare(strict_types=1);

namespace Arcana\Bio;

/** Classic 23/28/33-day biorhythms. Popular but not scientifically validated: entertainment. */
final class Biorhythm
{
    public const CYCLES = ['physical' => 23, 'emotional' => 28, 'intellectual' => 33];

    /** Whole days since 1970-01-01 (days-from-civil, proleptic Gregorian). */
    public static function dayNumber(int $y, int $m, int $d): int
    {
        $y -= $m <= 2 ? 1 : 0;
        $era = intdiv($y >= 0 ? $y : $y - 399, 400);
        $yoe = $y - $era * 400;
        $doy = intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5) + $d - 1;
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;
        return $era * 146097 + $doe - 719468;
    }

    /** Inverse of dayNumber(): 'Y-m-d'. */
    public static function date(int $day): string
    {
        $z = $day + 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $doe = $z - $era * 146097;
        $yoe = intdiv($doe - intdiv($doe, 1460) + intdiv($doe, 36524) - intdiv($doe, 146096), 365);
        $y = $yoe + $era * 400;
        $doy = $doe - (365 * $yoe + intdiv($yoe, 4) - intdiv($yoe, 100));
        $mp = intdiv(5 * $doy + 2, 153);
        $d = $doy - intdiv(153 * $mp + 2, 5) + 1;
        $m = $mp + ($mp < 10 ? 3 : -9);
        $y += $m <= 2 ? 1 : 0;
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** Day number of a 'Y-m-d' string (must be valid). */
    public static function dayOf(string $ymd): int
    {
        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        return self::dayNumber($y, $m, $d);
    }

    public static function value(int $birthDay, int $day, int $period): float
    {
        return 100.0 * sin(2 * M_PI * ($day - $birthDay) / $period);
    }

    /** Half-up rounding to a whole day number. */
    public static function roundDay(float $x): int
    {
        return (int) floor($x + 0.5);
    }

    /**
     * First day >= $today at which the cycle (anchored at $anchor, possibly fractional) reaches
     * phase $phase (0.25 = peak, 0.75 = trough) of a period.
     */
    public static function nextPhaseDay(float $anchor, int $period, float $phase, int $today): int
    {
        $k = (int) floor(($today - $anchor) / $period) - 1;
        while (true) {
            $day = self::roundDay($anchor + $period * ($k + $phase));
            if ($day >= $today) {
                return $day;
            }
            $k++;
        }
    }

    /** @return array<string, array{period:int,value:int,trend:string,nextPeak:string,nextTrough:string,curve:list<int>}> */
    public static function forPerson(int $birthDay, int $today, int $horizon = 30): array
    {
        $out = [];
        foreach (self::CYCLES as $name => $p) {
            $curve = [];
            for ($i = 0; $i <= $horizon; $i++) {
                $curve[] = (int) round(self::value($birthDay, $today + $i, $p));
            }
            $out[$name] = [
                'period' => $p,
                'value' => $curve[0],
                'trend' => cos(2 * M_PI * ($today - $birthDay) / $p) >= 0 ? 'rising' : 'falling',
                'nextPeak' => self::date(self::nextPhaseDay((float) $birthDay, $p, 0.25, $today)),
                'nextTrough' => self::date(self::nextPhaseDay((float) $birthDay, $p, 0.75, $today)),
                'curve' => $curve,
            ];
        }
        return $out;
    }
}

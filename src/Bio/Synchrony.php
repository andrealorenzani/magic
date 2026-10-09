<?php
declare(strict_types=1);

namespace Magic\Bio;

/**
 * How two people's biorhythms relate. Two curves of the same period are one sinusoid shifted
 * by a constant `delta` days, so the offset never changes; we report that offset, how
 * similar the curves are, and the dates where the combined curve peaks or dips.
 */
final class Synchrony
{
    public const THRESHOLD = 50;

    /** @return array{cycles: array<string, array<string,mixed>>, overall: float, band: string} */
    public static function pair(int $bA, int $bB, int $today, int $horizon = 120): array
    {
        $cycles = [];
        $sum = 0.0;
        foreach (Biorhythm::CYCLES as $name => $P) {
            $delta = (($bB - $bA) % $P + $P) % $P;
            if ($delta * 2 > $P) {
                $delta -= $P;
            }
            $sync = (1 + cos(2 * M_PI * $delta / $P)) / 2 * 100;
            $amplitude = 100 * cos(M_PI * $delta / $P);
            $flat = $amplitude < 10;
            $anchor = $bA + $delta / 2;
            $curveA = $curveB = [];
            for ($i = 0; $i <= 30; $i++) {
                $curveA[] = (int) round(Biorhythm::value($bA, $today + $i, $P));
                $curveB[] = (int) round(Biorhythm::value($bB, $today + $i, $P));
            }
            $cycles[$name] = [
                'period' => $P,
                'delta' => $delta,
                'peaksTogether' => abs($delta) <= 1,
                'sync' => $sync,
                'amplitude' => $amplitude,
                'flat' => $flat,
                'nextCombinedPeak' => $flat ? null : Biorhythm::date(Biorhythm::nextPhaseDay($anchor, $P, 0.25, $today)),
                'nextCombinedTrough' => $flat ? null : Biorhythm::date(Biorhythm::nextPhaseDay($anchor, $P, 0.75, $today)),
                'nextBothHigh' => self::both($bA, $bB, $P, $today, $horizon, true),
                'nextBothLow' => self::both($bA, $bB, $P, $today, $horizon, false),
                'curveA' => $curveA,
                'curveB' => $curveB,
            ];
            $sum += $sync;
        }
        $overall = $sum / count($cycles);
        return ['cycles' => $cycles, 'overall' => $overall, 'band' => $overall >= 75 ? 'tune' : ($overall >= 40 ? 'complementary' : 'apart')];
    }

    /** @return ?array{date:string,a:int,b:int} */
    private static function both(int $bA, int $bB, int $P, int $today, int $horizon, bool $high): ?array
    {
        for ($i = 0; $i <= $horizon; $i++) {
            $a = Biorhythm::value($bA, $today + $i, $P);
            $b = Biorhythm::value($bB, $today + $i, $P);
            $ok = $high ? ($a >= self::THRESHOLD && $b >= self::THRESHOLD) : ($a <= -self::THRESHOLD && $b <= -self::THRESHOLD);
            if ($ok) {
                return ['date' => Biorhythm::date($today + $i), 'a' => (int) round($a), 'b' => (int) round($b)];
            }
        }
        return null;
    }
}

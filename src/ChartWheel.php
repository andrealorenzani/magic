<?php
declare(strict_types=1);

namespace Magic;

use Magic\Astro\Angles;
use Magic\Astro\Zodiac;
use Magic\Content\Bodies;

/** Geometry of the chart wheel (Ascendant on the left, signs counter-clockwise). Pure: numbers only. */
final class ChartWheel
{
    public const SIZE = 400;
    public const CENTER = 200.0;
    public const R_OUTER = 184.0;
    public const R_SIGN_INNER = 154.0;
    public const R_SIGN_LABEL = 169.0;
    public const R_BODY = 132.0;
    public const R_TICK_INNER = 150.0;
    public const R_HOUSE_INNER = 92.0;
    public const R_HOUSE_LABEL = 108.0;
    /** Smallest angular distance, in degrees, between two glyphs on the drawing. */
    public const MIN_SEPARATION = 7.0;

    /**
     * @param array<string,mixed> $chart Chart::full() result
     * @return array{size:int, ascendant:float, signs:list<array<string,mixed>>, houses:list<array<string,mixed>>, axes:list<array<string,mixed>>, bodies:list<array<string,mixed>>}
     */
    public static function layout(array $chart): array
    {
        $asc = (float) $chart['ascendant']['longitude'];
        $at = static fn (float $lon): float => Angles::norm360(180.0 + $lon - $asc); // screen angle, counter-clockwise from the right
        $signs = [];
        $houses = [];
        $firstSign = (int) $chart['ascendant']['index'];
        for ($i = 0; $i < 12; $i++) {
            $sign = Zodiac::SIGNS[$i];
            $start = $at($i * 30.0);
            $mid = $at($i * 30.0 + 15.0);
            $signs[] = [
                'id' => $sign['id'], 'name' => $sign['name'], 'symbol' => $sign['symbol'], 'element' => strtolower($sign['element']),
                'angle' => $start,
                'line' => self::segment($start, self::R_HOUSE_INNER, self::R_OUTER),
                'label' => self::point($mid, self::R_SIGN_LABEL),
            ];
            $n = (($i - $firstSign + 12) % 12) + 1;
            $houses[] = ['number' => $n, 'angle' => $mid, 'label' => self::point($mid, self::R_HOUSE_LABEL)];
        }

        $axes = [
            ['id' => 'ascendant', 'label' => 'AC', 'angle' => $at($asc), 'line' => self::segment($at($asc), self::R_HOUSE_INNER, self::R_OUTER), 'text' => self::point($at($asc), self::R_OUTER + 9.0)],
        ];
        if (isset($chart['midheaven'])) {
            $mc = (float) $chart['midheaven']['longitude'];
            $axes[] = ['id' => 'midheaven', 'label' => 'MC', 'angle' => $at($mc), 'line' => self::segment($at($mc), self::R_HOUSE_INNER, self::R_OUTER), 'text' => self::point($at($mc), self::R_OUTER + 9.0)];
        }

        $items = [['id' => 'sun', 'lon' => (float) $chart['sun']['longitude']], ['id' => 'moon', 'lon' => (float) $chart['moon']['longitude']]];
        foreach ($chart['planets'] ?? [] as $id => $pl) {
            $items[] = ['id' => $id, 'lon' => (float) $pl['position']['longitude']];
        }
        if (isset($chart['node'])) {
            $items[] = ['id' => 'node', 'lon' => (float) $chart['node']['longitude']];
        }
        $true = array_map(static fn (array $it): float => $at($it['lon']), $items);
        $drawn = self::spread($true, self::MIN_SEPARATION);
        $bodies = [];
        foreach ($items as $i => $it) {
            $info = Bodies::INFO[$it['id']];
            $bodies[] = [
                'id' => $it['id'], 'glyph' => $info['glyph'], 'title' => $info['title'], 'longitude' => $it['lon'],
                'angle' => $true[$i], 'drawnAngle' => $drawn[$i],
                'position' => self::point($drawn[$i], self::R_BODY),
                'tick' => self::segment($true[$i], self::R_TICK_INNER, self::R_SIGN_INNER),
            ];
        }
        return ['size' => self::SIZE, 'ascendant' => $asc, 'signs' => $signs, 'houses' => $houses, 'axes' => $axes, 'bodies' => $bodies];
    }

    /**
     * Moves angles (degrees, circular) apart until neighbours are at least $min degrees away; the order is kept.
     * @param list<float> $angles @return list<float> same indexes
     */
    public static function spread(array $angles, float $min): array
    {
        $n = count($angles);
        if ($n < 2) {
            return $angles;
        }
        $order = array_keys($angles);
        usort($order, static fn (int $a, int $b): int => $angles[$a] <=> $angles[$b] ?: $a <=> $b);
        $pos = [];
        foreach ($order as $k => $idx) {
            $pos[$k] = $angles[$idx];
        }
        for ($pass = 0; $pass < 2000; $pass++) {
            $moved = false;
            for ($k = 0; $k < $n; $k++) {
                $next = ($k + 1) % $n;
                $gap = $pos[$next] - $pos[$k] + ($next === 0 ? 360.0 : 0.0);
                if ($gap < $min - 1e-9) {
                    $push = ($min - $gap) / 2.0 + 1e-9;
                    $pos[$k] -= $push;
                    $pos[$next] += $push;
                    $moved = true;
                }
            }
            if (!$moved) {
                break;
            }
        }
        $out = [];
        foreach ($order as $k => $idx) {
            $out[$idx] = Angles::norm360($pos[$k]);
        }
        ksort($out);
        return array_values($out);
    }

    /** @return array{x: float, y: float} */
    public static function point(float $angleDeg, float $radius): array
    {
        $a = Angles::rad($angleDeg);
        return ['x' => round(self::CENTER + $radius * cos($a), 2), 'y' => round(self::CENTER - $radius * sin($a), 2)];
    }

    /** @return array{x1: float, y1: float, x2: float, y2: float} */
    private static function segment(float $angleDeg, float $r1, float $r2): array
    {
        $a = self::point($angleDeg, $r1);
        $b = self::point($angleDeg, $r2);
        return ['x1' => $a['x'], 'y1' => $a['y'], 'x2' => $b['x'], 'y2' => $b['y']];
    }
}

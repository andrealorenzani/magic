<?php
declare(strict_types=1);

namespace Arcana\Love;

use Arcana\Content\Traits;

/** What two people's Sun, Moon and Ascendant have in common. */
final class Common
{
    private const WEIGHT = ['sun' => 2, 'moon' => 2, 'ascendant' => 1];
    private const BODY_NAME = ['sun' => 'Sun', 'moon' => 'Moon', 'ascendant' => 'Ascendant'];

    /**
     * @param array<string,mixed> $chartA Chart::partial shape
     * @param array<string,mixed> $chartB Chart::partial shape
     * @return array{items: list<array<string,mixed>>, score: ?int, basis: list<string>}
     */
    public static function between(array $chartA, array $chartB): array
    {
        $items = [];
        $used = [];
        $missing = [];
        foreach (self::WEIGHT as $body => $w) {
            $a = $chartA[$body] ?? null;
            $b = $chartB[$body] ?? null;
            if ($a === null || $b === null) {
                $missing[] = self::BODY_NAME[$body];
                continue;
            }
            $used[] = self::BODY_NAME[$body];
            [$level, $points, $traits] = self::level($a, $b);
            $items[] = [
                'body' => $body, 'a' => $a, 'b' => $b, 'level' => $level, 'points' => $points, 'traits' => $traits,
                'approx' => (bool) (($chartA['approx'][$body] ?? false) || ($chartB['approx'][$body] ?? false)),
            ];
        }
        $weights = 0;
        $sum = 0;
        foreach ($items as $it) {
            $weights += self::WEIGHT[$it['body']];
            $sum += self::WEIGHT[$it['body']] * $it['points'];
        }
        $basis = $used ? ['Compared: ' . implode(', ', $used) . '.'] : [];
        if ($missing) {
            $basis[] = 'Not compared: ' . implode(', ', $missing) . ' (needs both birth times and places).';
        }
        return ['items' => $items, 'score' => $weights ? (int) round($sum / $weights) : null, 'basis' => $basis];
    }

    /** @return array{0: string, 1: int, 2: list<string>} */
    private static function level(array $a, array $b): array
    {
        if ($a['index'] === $b['index']) {
            return ['sign', 100, Traits::SIGNS[$a['id']]];
        }
        if ($a['element'] === $b['element']) {
            return ['element', 80, Traits::ELEMENTS[$a['element']]];
        }
        $pair = [$a['element'], $b['element']];
        sort($pair);
        $key = implode('|', $pair);
        if (isset(Traits::COMPLEMENT[$key])) {
            return ['complement', 60, Traits::COMPLEMENT[$key]];
        }
        if ($a['modality'] === $b['modality']) {
            return ['modality', 40, Traits::MODALITIES[$a['modality']]];
        }
        return ['none', 20, [Traits::LEVELS['none']]];
    }
}

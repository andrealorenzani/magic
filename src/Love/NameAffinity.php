<?php
declare(strict_types=1);

namespace Magic\Love;

/** Name affinity between two names, as a percentage with its working values. */
final class NameAffinity
{
    public const LETTERS = ['A', 'M', 'O', 'R', 'E'];

    /** @return array<string,mixed> */
    public static function compute(string $nameA, string $nameB): array
    {
        $norm = self::normalize($nameA . ' ' . $nameB);
        $counts = [];
        foreach (self::LETTERS as $l) {
            $counts[$l] = substr_count($norm, strtolower($l));
        }
        return self::fromCounts($counts);
    }

    /** @param array<string,int> $counts keys A, M, O, R, E @return array<string,mixed> */
    public static function fromCounts(array $counts): array
    {
        $c = [];
        foreach (self::LETTERS as $l) {
            $c[] = (int) ($counts[$l] ?? 0);
        }
        $slots = [0, 0, 0, 0, 0];
        foreach ($c as $i => $n) {
            if ($n < 10) {
                $slots[$i] += $n;
                continue;
            }
            $slots[$i] += $n % 10;
            $slots[$i === 0 ? 1 : $i - 1] += (int) substr((string) $n, 0, 1);
        }
        $start = 0;
        foreach ($slots as $i => $s) {
            $start += $s * 10 ** (4 - $i);
        }
        $chain = [$start];
        $v = $start;
        while ($v > 100) {
            $v = self::step($v);
            $chain[] = $v;
        }
        return [
            'counts' => array_combine(self::LETTERS, $c),
            'slots' => $slots,
            'start' => $start,
            'chain' => $chain,
            'percent' => $v,
            'noLetters' => array_sum($c) === 0,
        ];
    }

    /** One pairwise-sum step: 6,6,9 -> 12,15 -> 12*10 + 15 = 135. */
    public static function step(int $value): int
    {
        $d = array_map('intval', str_split((string) $value));
        $n = count($d);
        $out = 0;
        for ($i = 0; $i < $n - 1; $i++) {
            $out += ($d[$i] + $d[$i + 1]) * 10 ** ($n - 2 - $i);
        }
        return $out;
    }

    /** Lowercase ASCII letters only; accents and case folded, no intl/mbstring needed. */
    public static function normalize(string $name): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            $groups = [
                'a' => 'àáâãäåāăąǎǻÀÁÂÃÄÅĀĂĄǍǺ', 'e' => 'èéêëēĕėęěÈÉÊËĒĔĖĘĚ',
                'o' => 'òóôõöøōŏőơǒǿÒÓÔÕÖØŌŎŐƠǑǾ', 'r' => 'ŕŗřŔŖŘ',
            ];
            foreach ($groups as $plain => $chars) {
                foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
                    $map[$ch] = $plain;
                }
            }
            $map += ['æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe', 'ß' => 'ss', 'ẞ' => 'ss'];
        }
        $s = preg_replace('/\p{Mn}+/u', '', strtr($name, $map));
        return preg_replace('/[^a-z]/', '', strtolower((string) $s)) ?? '';
    }
}

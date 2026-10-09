<?php
declare(strict_types=1);

namespace Magic\Earth;

use Magic\Time\Zone;

/** Builds the "Distance and geography" model from places. Pure: the reference instant is passed in. */
final class Geography
{
    /**
     * @param ?array{lat:float|int,lon:float|int,tz:string} $a
     * @param ?array{lat:float|int,lon:float|int,tz:string} $b
     * @return ?array{km:int, miles:int, minutes:int, sameZone:bool}
     */
    public static function between(?array $a, ?array $b, int $refUnix): ?array
    {
        if ($a === null || $b === null) {
            return null;
        }
        $km = Distance::km((float) $a['lat'], (float) $a['lon'], (float) $b['lat'], (float) $b['lon']);
        $minutes = Zone::offsetMinutes($refUnix, $b['tz']) - Zone::offsetMinutes($refUnix, $a['tz']);
        return [
            'km' => (int) round($km),
            'miles' => (int) round(Distance::miles($km)),
            'minutes' => $minutes,
            'sameZone' => $minutes === 0,
        ];
    }

    /**
     * @param array<string,mixed> $input Request::parse()['input'] (with optional 'now')
     * @return ?array{pairs: list<array<string,mixed>>}
     */
    public static function forSelf(array $input, string $today): ?array
    {
        $ref = self::reference($today);
        $pairs = [];
        self::add($pairs, 'birth_to_now', 'Birth place to where you are now', self::place($input), $input['now'] ?? null, $ref);
        return $pairs ? ['pairs' => $pairs] : null;
    }

    /**
     * @param array<string,mixed> $a person shape (see Request::parseLove)
     * @param array<string,mixed> $b person shape
     * @return ?array{pairs: list<array<string,mixed>>}
     */
    public static function forLove(array $a, array $b, string $today): ?array
    {
        $ref = self::reference($today);
        $pairs = [];
        $nameB = (string) $b['name'];
        self::add($pairs, 'between', 'You and ' . $nameB . ', where you are now', $a['now'] ?? null, $b['now'] ?? null, $ref);
        self::add($pairs, 'a_birth_to_now', 'You: birth place to where you are now', $a['place'] ?? null, $a['now'] ?? null, $ref);
        self::add($pairs, 'b_birth_to_now', $nameB . ': birth place to where they are now', $b['place'] ?? null, $b['now'] ?? null, $ref);
        return $pairs ? ['pairs' => $pairs] : null;
    }

    /** 12:00 UTC of the reading day. */
    private static function reference(string $today): int
    {
        return gmmktime(12, 0, 0, (int) substr($today, 5, 2), (int) substr($today, 8, 2), (int) substr($today, 0, 4));
    }

    /** @param array<string,mixed> $input @return ?array<string,mixed> */
    private static function place(array $input): ?array
    {
        return ['lat' => $input['lat'], 'lon' => $input['lon'], 'tz' => $input['tz'], 'city' => (string) ($input['city'] ?? '')];
    }

    /** @param list<array<string,mixed>> $pairs @param ?array<string,mixed> $from @param ?array<string,mixed> $to */
    private static function add(array &$pairs, string $key, string $label, ?array $from, ?array $to, int $ref): void
    {
        $g = self::between($from, $to, $ref);
        if ($g !== null) {
            $pairs[] = ['key' => $key, 'label' => $label, 'from' => (string) ($from['city'] ?? ''), 'to' => (string) ($to['city'] ?? '')] + $g;
        }
    }

    /** "+5 h 30 min", "-6 h", "0 h". */
    public static function formatOffset(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '+';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        if ($abs === 0) {
            return '0 h';
        }
        return $sign . $h . ' h' . ($m > 0 ? ' ' . $m . ' min' : '');
    }
}

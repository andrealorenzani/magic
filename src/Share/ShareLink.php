<?php
declare(strict_types=1);

namespace Magic\Share;

use Magic\Content\TarotDeck;

/**
 * Builds canonical share queries from validated models (never from the raw query) and
 * reads/writes the tarot code. Pure: no I/O.
 */
final class ShareLink
{
    public const MAX_URL_FOR_QR = 520;
    public const MAX_CITY_BYTES = 48;

    /** Query for the exact Self reading (without a leading '?'). @param array<string,mixed> $input Request::parse()['input'] */
    public static function self(array $input, string $today): string
    {
        return self::join(array_merge(['mode' => 'self'], self::selfFields($input), ['on' => $today], ['noaudit' => '']));
    }

    /** Self reading with today's values (no `on`). @param array<string,mixed> $input */
    public static function liveSelf(array $input): string
    {
        return self::join(array_merge(['mode' => 'self'], self::selfFields($input), ['noaudit' => '']));
    }

    /**
     * Query for the exact Love reading, with the tarot spread when given.
     * @param array<string,mixed> $a @param array<string,mixed> $b persons as returned by Request::parseLove
     * @param ?list<array{card:array, reversed:bool}> $tarot spread (Reading::spread shape)
     */
    public static function love(array $a, array $b, ?array $tarot, string $today): string
    {
        $f = array_merge(['mode' => 'love'], self::personFields('a_', $a), self::personFields('b_', $b), ['on' => $today]);
        if ($tarot !== null) {
            $f['t'] = self::tarotCode($tarot);
        }
        $f['noaudit'] = '';
        return self::join($f);
    }

    /** Love reading for the same people but today's values (no `on`, no `t`). @param array<string,mixed> $a @param array<string,mixed> $b */
    public static function liveLove(array $a, array $b): string
    {
        return self::join(array_merge(['mode' => 'love'], self::personFields('a_', $a), self::personFields('b_', $b), ['noaudit' => '']));
    }

    /** Query part carrying a compact code. */
    public static function codeQuery(string $code): string
    {
        return 'c=' . $code;
    }

    /** "16u,5r,9u" @param list<array{card:array, reversed:bool}> $spread */
    public static function tarotCode(array $spread): string
    {
        return implode(',', array_map(static fn (array $s): string => $s['card']['number'] . ($s['reversed'] ? 'r' : 'u'), $spread));
    }

    /** @return ?list<array{number:int, reversed:bool}> null when the code is not exactly three distinct cards 0-77 */
    public static function parseTarot(string $code): ?array
    {
        if (strlen($code) > 20 || !preg_match('/^(\d{1,2})([ur]),(\d{1,2})([ur]),(\d{1,2})([ur])$/', $code, $m)) {
            return null;
        }
        $slots = [];
        for ($i = 1; $i <= 5; $i += 2) {
            $n = (int) $m[$i];
            if ($n >= TarotDeck::COUNT) {
                return null;
            }
            $slots[] = ['number' => $n, 'reversed' => $m[$i + 1] === 'r'];
        }
        if (count(array_unique(array_column($slots, 'number'))) !== 3) {
            return null;
        }
        return $slots;
    }

    /** City label cut (by characters) so that its percent-encoded form is at most MAX_CITY_BYTES. */
    public static function trimCity(string $city): string
    {
        $city = trim($city);
        while ($city !== '' && strlen(rawurlencode($city)) > self::MAX_CITY_BYTES) {
            $city = rtrim(mb_substr($city, 0, mb_strlen($city, 'UTF-8') - 1, 'UTF-8'));
        }
        return $city;
    }

    /** @param array<string,mixed> $in @return array<string,string> */
    private static function selfFields(array $in): array
    {
        return [
            'date' => sprintf('%04d-%02d-%02d', $in['year'], $in['month'], $in['day']),
            'time' => sprintf('%02d:%02d', $in['hour'], $in['minute']),
            'city' => self::trimCity((string) $in['city']),
            'lat' => self::coord((float) $in['lat']),
            'lon' => self::coord((float) $in['lon']),
            'tz' => (string) $in['tz'],
        ] + self::nowFields('', $in['now'] ?? null);
    }

    /** The optional current position. @param ?array<string,mixed> $now @return array<string,string> */
    private static function nowFields(string $prefix, ?array $now): array
    {
        if ($now === null) {
            return [];
        }
        return [
            $prefix . 'pos_city' => self::trimCity((string) $now['city']),
            $prefix . 'pos_lat' => self::coord((float) $now['lat']),
            $prefix . 'pos_lon' => self::coord((float) $now['lon']),
            $prefix . 'pos_tz' => (string) $now['tz'],
        ];
    }

    /** Only the fields that exist. @param array<string,mixed> $p @return array<string,string> */
    private static function personFields(string $prefix, array $p): array
    {
        $out = [$prefix . 'name' => (string) $p['name']];
        if ($p['date'] !== null) {
            $out[$prefix . 'date'] = sprintf('%04d-%02d-%02d', $p['date']['year'], $p['date']['month'], $p['date']['day']);
            if ($p['time'] !== null && $p['place'] !== null) {
                $out[$prefix . 'time'] = sprintf('%02d:%02d', $p['time']['hour'], $p['time']['minute']);
                $out[$prefix . 'city'] = self::trimCity((string) $p['place']['city']);
                $out[$prefix . 'lat'] = self::coord((float) $p['place']['lat']);
                $out[$prefix . 'lon'] = self::coord((float) $p['place']['lon']);
                $out[$prefix . 'tz'] = (string) $p['place']['tz'];
            }
        }
        return $out + self::nowFields($prefix, $p['now'] ?? null);
    }

    private static function coord(float $v): string
    {
        $s = sprintf('%.5f', round($v, 5));
        return $s === '-0.00000' ? '0.00000' : $s;
    }

    /** @param array<string,string> $fields */
    private static function join(array $fields): string
    {
        $parts = [];
        foreach ($fields as $k => $v) {
            $parts[] = $v === '' && $k === 'noaudit' ? $k : $k . '=' . strtr(rawurlencode($v), ['%2F' => '/', '%3A' => ':', '%2C' => ',']);
        }
        return implode('&', $parts);
    }
}

<?php
declare(strict_types=1);

namespace Magic\Share;

use InvalidArgumentException;
use Magic\Time\Zone;

/**
 * Compact share code (`c=`): base64url of a fixed bit layout. Pure: no I/O.
 *
 * Layout (version 1), most significant bit first, zero padded to a whole byte:
 *   header 8   version(4) mode(1) noaudit(1) hasOn(1) hasTarot(1)
 *   on 17      days since 1900-01-01, when hasOn
 *   tarot 24   3 x [card(7) reversed(1)], when hasTarot
 *   person     Love only: nameBytes(8) 1..160 + UTF-8 bytes
 *              Self and Love A: date(19) time(11) PLACE; Love B: hasDate(1) [date(19) hasBirth(1) [time(11) PLACE]]
 *              hasNow(1) [PLACE]
 *   place      lat(25) lon(26) tz(10) [tzBytes(6) + ASCII when tz is 1023] labelBytes(6) 1..32 + UTF-8 bytes
 * lat = (latitude + 90) * 100000, lon = (longitude + 180) * 100000, date = days since 1000-01-01.
 */
final class ShareCode
{
    public const VERSION = 1;
    public const MAX_CHARS = 400;
    public const MAX_LABEL_BYTES = 32;
    public const TZ_LITERAL = 1023;

    private const LAT_MAX = 18000000;
    private const LON_MAX = 36000000;
    private const BIRTH_DAYS_MAX = 402131;
    private const ON_DAYS_MAX = 73413;
    private const CARD_MAX = 77;

    /** @param array<string,mixed> $input Request::parse()['input'] */
    public static function encodeSelf(array $input, string $today, bool $frozen, bool $noAudit = true): string
    {
        $flat = ['mode' => 'self'] + self::placeFlat('', $input) + [
            'date' => sprintf('%04d-%02d-%02d', $input['year'], $input['month'], $input['day']),
            'time' => sprintf('%02d:%02d', $input['hour'], $input['minute']),
        ] + self::nowFlat('', $input['now'] ?? null);
        if ($frozen) {
            $flat['on'] = $today;
        }
        if ($noAudit) {
            $flat['noaudit'] = '';
        }
        return self::pack($flat);
    }

    /**
     * @param array<string,mixed> $a @param array<string,mixed> $b persons as returned by Request::parseLove
     * @param ?list<array{card:array, reversed:bool}> $tarotSpread
     */
    public static function encodeLove(array $a, array $b, ?array $tarotSpread, string $today, bool $frozen, bool $noAudit = true): string
    {
        $flat = ['mode' => 'love'] + self::personFlat('a_', $a) + self::personFlat('b_', $b);
        if ($frozen) {
            $flat['on'] = $today;
            if ($tarotSpread !== null) {
                $flat['t'] = ShareLink::tarotCode($tarotSpread);
            }
        }
        if ($noAudit) {
            $flat['noaudit'] = '';
        }
        return self::pack($flat);
    }

    /** The long-query array for a valid canonical code, else null. Never throws. @return ?array<string,string> */
    public static function decode(string $code): ?array
    {
        try {
            if ($code === '' || strlen($code) > self::MAX_CHARS || preg_match('/^[A-Za-z0-9_-]+$/', $code) !== 1 || strlen($code) % 4 === 1) {
                return null;
            }
            $bytes = base64_decode(strtr($code, '-_', '+/') . str_repeat('=', (4 - strlen($code) % 4) % 4), true);
            if ($bytes === false || $bytes === '') {
                return null;
            }
            $bits = '';
            for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
                $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
            }
            $pos = 0;
            $flat = self::read($bits, $pos);
            $rest = substr($bits, $pos);
            if (strlen($rest) >= 8 || strpos($rest, '1') !== false) {
                return null;
            }
            return self::pack($flat) === $code ? $flat : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- reading ----

    /** @return array<string,string> */
    private static function read(string $bits, int &$pos): array
    {
        if (self::take($bits, $pos, 4) !== self::VERSION) {
            throw new InvalidArgumentException('version');
        }
        $love = self::take($bits, $pos, 1) === 1;
        $noAudit = self::take($bits, $pos, 1) === 1;
        $hasOn = self::take($bits, $pos, 1) === 1;
        $hasTarot = self::take($bits, $pos, 1) === 1;
        if ($hasTarot && !$love) {
            throw new InvalidArgumentException('tarot');
        }
        $flat = ['mode' => $love ? 'love' : 'self'];
        $on = null;
        if ($hasOn) {
            $d = self::take($bits, $pos, 17);
            if ($d > self::ON_DAYS_MAX) {
                throw new InvalidArgumentException('on');
            }
            $on = self::dateFromDays($d, 1900);
        }
        $tarot = null;
        if ($hasTarot) {
            $slots = [];
            for ($i = 0; $i < 3; $i++) {
                $card = self::take($bits, $pos, 7);
                $slots[] = $card . (self::take($bits, $pos, 1) === 1 ? 'r' : 'u');
                if ($card > self::CARD_MAX) {
                    throw new InvalidArgumentException('card');
                }
            }
            $tarot = implode(',', $slots);
            if (count(array_unique(array_map(static fn (string $s): int => (int) $s, $slots))) !== 3) {
                throw new InvalidArgumentException('cards');
            }
        }
        if ($love) {
            $flat += self::readPerson($bits, $pos, 'a_', true, true);
            $flat += self::readPerson($bits, $pos, 'b_', true, false);
        } else {
            $flat += self::readPerson($bits, $pos, '', false, true);
        }
        if ($on !== null) {
            $flat['on'] = $on;
        }
        if ($tarot !== null) {
            $flat['t'] = $tarot;
        }
        if ($noAudit) {
            $flat['noaudit'] = '';
        }
        return $flat;
    }

    /** @return array<string,string> */
    private static function readPerson(string $bits, int &$pos, string $p, bool $named, bool $complete): array
    {
        $out = [];
        if ($named) {
            $len = self::take($bits, $pos, 8);
            $name = self::takeBytes($bits, $pos, $len);
            if ($len < 1 || $len > 160 || !self::validName($name)) {
                throw new InvalidArgumentException('name');
            }
            $out[$p . 'name'] = $name;
        }
        $hasDate = $complete || self::take($bits, $pos, 1) === 1;
        if ($hasDate) {
            $days = self::take($bits, $pos, 19);
            if ($days > self::BIRTH_DAYS_MAX) {
                throw new InvalidArgumentException('date');
            }
            $out[$p . 'date'] = self::dateFromDays($days, 1000);
            $hasBirth = $complete || self::take($bits, $pos, 1) === 1;
            if ($hasBirth) {
                $min = self::take($bits, $pos, 11);
                if ($min > 1439) {
                    throw new InvalidArgumentException('time');
                }
                $out[$p . 'time'] = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
                $out += self::readPlace($bits, $pos, $p, '');
            }
        }
        if (self::take($bits, $pos, 1) === 1) {
            $out += self::readPlace($bits, $pos, $p, 'pos_');
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function readPlace(string $bits, int &$pos, string $p, string $kind): array
    {
        $lat = self::take($bits, $pos, 25);
        $lon = self::take($bits, $pos, 26);
        if ($lat > self::LAT_MAX || $lon > self::LON_MAX) {
            throw new InvalidArgumentException('coordinates');
        }
        $idx = self::take($bits, $pos, 10);
        if ($idx === self::TZ_LITERAL) {
            $len = self::take($bits, $pos, 6);
            $tz = self::takeBytes($bits, $pos, $len);
            if ($len < 1 || preg_match('/^[A-Za-z0-9_+\/-]+$/', $tz) !== 1) {
                throw new InvalidArgumentException('tz');
            }
        } else {
            $tz = TimeZoneTable::at($idx) ?? throw new InvalidArgumentException('tz index');
        }
        if (!Zone::isValid($tz)) {
            throw new InvalidArgumentException('tz');
        }
        $len = self::take($bits, $pos, 6);
        $label = self::takeBytes($bits, $pos, $len);
        if ($len < 1 || $len > self::MAX_LABEL_BYTES || !self::validLabel($label)) {
            throw new InvalidArgumentException('label');
        }
        return [
            $p . $kind . 'city' => $label,
            $p . $kind . 'lat' => self::coordString($lat / 100000 - 90),
            $p . $kind . 'lon' => self::coordString($lon / 100000 - 180),
            $p . $kind . 'tz' => $tz,
        ];
    }

    private static function take(string $bits, int &$pos, int $n): int
    {
        if ($pos + $n > strlen($bits)) {
            throw new InvalidArgumentException('truncated');
        }
        $v = bindec(substr($bits, $pos, $n));
        $pos += $n;
        return (int) $v;
    }

    private static function takeBytes(string $bits, int &$pos, int $n): string
    {
        $s = '';
        for ($i = 0; $i < $n; $i++) {
            $s .= chr(self::take($bits, $pos, 8));
        }
        return $s;
    }

    // ---- writing ----

    /** @param array<string,string> $f long-query array @return string */
    private static function pack(array $f): string
    {
        $love = ($f['mode'] ?? '') === 'love';
        $hasOn = isset($f['on']);
        $hasTarot = isset($f['t']);
        if ($hasTarot && !$love) {
            throw new InvalidArgumentException('tarot');
        }
        $bits = '';
        self::put($bits, self::VERSION, 4);
        self::put($bits, $love ? 1 : 0, 1);
        self::put($bits, isset($f['noaudit']) ? 1 : 0, 1);
        self::put($bits, $hasOn ? 1 : 0, 1);
        self::put($bits, $hasTarot ? 1 : 0, 1);
        if ($hasOn) {
            $d = self::daysFromDate($f['on'], 1900);
            if ($d < 0 || $d > self::ON_DAYS_MAX) {
                throw new InvalidArgumentException('on');
            }
            self::put($bits, $d, 17);
        }
        if ($hasTarot) {
            if (preg_match('/^(\d{1,2})([ur]),(\d{1,2})([ur]),(\d{1,2})([ur])$/', $f['t'], $m) !== 1) {
                throw new InvalidArgumentException('t');
            }
            $cards = [];
            for ($i = 1; $i <= 5; $i += 2) {
                $n = (int) $m[$i];
                if ($n > self::CARD_MAX) {
                    throw new InvalidArgumentException('card');
                }
                $cards[] = $n;
                self::put($bits, $n, 7);
                self::put($bits, $m[$i + 1] === 'r' ? 1 : 0, 1);
            }
            if (count(array_unique($cards)) !== 3) {
                throw new InvalidArgumentException('cards');
            }
        }
        if ($love) {
            self::packPerson($bits, $f, 'a_', true, true);
            self::packPerson($bits, $f, 'b_', true, false);
        } else {
            self::packPerson($bits, $f, '', false, true);
        }
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = '';
        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $bytes .= chr((int) bindec(substr($bits, $i, 8)));
        }
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** @param array<string,string> $f */
    private static function packPerson(string &$bits, array $f, string $p, bool $named, bool $complete): void
    {
        if ($named) {
            $name = $f[$p . 'name'] ?? '';
            if (!self::validName($name) || strlen($name) > 160) {
                throw new InvalidArgumentException('name');
            }
            self::put($bits, strlen($name), 8);
            self::putBytes($bits, $name);
        }
        $hasDate = isset($f[$p . 'date']);
        if (!$complete) {
            self::put($bits, $hasDate ? 1 : 0, 1);
        } elseif (!$hasDate) {
            throw new InvalidArgumentException('date');
        }
        if ($hasDate) {
            $d = self::daysFromDate($f[$p . 'date'], 1000);
            if ($d < 0 || $d > self::BIRTH_DAYS_MAX) {
                throw new InvalidArgumentException('date');
            }
            self::put($bits, $d, 19);
            $hasBirth = isset($f[$p . 'time'], $f[$p . 'city']);
            if (!$complete) {
                self::put($bits, $hasBirth ? 1 : 0, 1);
            } elseif (!$hasBirth) {
                throw new InvalidArgumentException('birth');
            }
            if ($hasBirth) {
                if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $f[$p . 'time'], $m) !== 1) {
                    throw new InvalidArgumentException('time');
                }
                self::put($bits, (int) $m[1] * 60 + (int) $m[2], 11);
                self::packPlace($bits, $f, $p);
            }
        }
        $hasNow = isset($f[$p . 'pos_city']);
        self::put($bits, $hasNow ? 1 : 0, 1);
        if ($hasNow) {
            self::packPlace($bits, $f, $p . 'pos_');
        }
    }

    /** @param array<string,string> $f */
    private static function packPlace(string &$bits, array $f, string $k): void
    {
        $lat = (int) round(((float) ($f[$k . 'lat'] ?? 'x') + 90) * 100000);
        $lon = (int) round(((float) ($f[$k . 'lon'] ?? 'x') + 180) * 100000);
        if (!isset($f[$k . 'lat'], $f[$k . 'lon'], $f[$k . 'tz'], $f[$k . 'city']) || $lat < 0 || $lat > self::LAT_MAX || $lon < 0 || $lon > self::LON_MAX) {
            throw new InvalidArgumentException('place');
        }
        $label = $f[$k . 'city'];
        if ($label === '' || strlen($label) > self::MAX_LABEL_BYTES || !self::validLabel($label)) {
            throw new InvalidArgumentException('label');
        }
        self::put($bits, $lat, 25);
        self::put($bits, $lon, 26);
        $tz = $f[$k . 'tz'];
        $idx = TimeZoneTable::indexOf($tz);
        if ($idx !== null) {
            self::put($bits, $idx, 10);
        } else {
            if (strlen($tz) < 1 || strlen($tz) > 63 || preg_match('/^[A-Za-z0-9_+\/-]+$/', $tz) !== 1) {
                throw new InvalidArgumentException('tz');
            }
            self::put($bits, self::TZ_LITERAL, 10);
            self::put($bits, strlen($tz), 6);
            self::putBytes($bits, $tz);
        }
        self::put($bits, strlen($label), 6);
        self::putBytes($bits, $label);
    }

    private static function put(string &$bits, int $v, int $n): void
    {
        $bits .= str_pad(decbin($v), $n, '0', STR_PAD_LEFT);
    }

    private static function putBytes(string &$bits, string $s): void
    {
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            self::put($bits, ord($s[$i]), 8);
        }
    }

    // ---- models ----

    /** @param array<string,mixed> $in @return array<string,string> */
    private static function placeFlat(string $p, array $in): array
    {
        return [
            $p . 'city' => self::cutLabel((string) $in['city']),
            $p . 'lat' => self::coordString((float) $in['lat']),
            $p . 'lon' => self::coordString((float) $in['lon']),
            $p . 'tz' => (string) $in['tz'],
        ];
    }

    /** @param ?array<string,mixed> $now @return array<string,string> */
    private static function nowFlat(string $p, ?array $now): array
    {
        if ($now === null) {
            return [];
        }
        return [
            $p . 'pos_city' => self::cutLabel((string) $now['city']),
            $p . 'pos_lat' => self::coordString((float) $now['lat']),
            $p . 'pos_lon' => self::coordString((float) $now['lon']),
            $p . 'pos_tz' => (string) $now['tz'],
        ];
    }

    /** @param array<string,mixed> $x @return array<string,string> */
    private static function personFlat(string $p, array $x): array
    {
        $out = [$p . 'name' => (string) $x['name']];
        if ($x['date'] !== null) {
            $out[$p . 'date'] = sprintf('%04d-%02d-%02d', $x['date']['year'], $x['date']['month'], $x['date']['day']);
            if ($x['time'] !== null && $x['place'] !== null) {
                $out[$p . 'time'] = sprintf('%02d:%02d', $x['time']['hour'], $x['time']['minute']);
                $out += self::placeFlat($p, $x['place']);
            }
        }
        return $out + self::nowFlat($p, $x['now'] ?? null);
    }

    // ---- helpers ----

    /** Label cut to MAX_LABEL_BYTES on a character boundary. */
    public static function cutLabel(string $label): string
    {
        $label = trim($label);
        while ($label !== '' && strlen($label) > self::MAX_LABEL_BYTES) {
            $label = rtrim(mb_substr($label, 0, mb_strlen($label, 'UTF-8') - 1, 'UTF-8'));
        }
        return $label;
    }

    private static function coordString(float $v): string
    {
        $s = sprintf('%.5f', round($v, 5));
        return $s === '-0.00000' ? '0.00000' : $s;
    }

    private static function validName(string $name): bool
    {
        $len = preg_match_all('/./u', $name);
        return $name === trim($name) && $len !== false && $len >= 1 && $len <= 40
            && preg_match('/\p{Cc}/u', $name) === 0 && preg_match('/\p{L}/u', $name) === 1;
    }

    private static function validLabel(string $label): bool
    {
        return preg_match('//u', $label) === 1 && preg_match('/\p{Cc}/u', $label) === 0 && $label === trim($label);
    }

    private static function daysFromDate(string $date, int $baseYear): int
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException('date');
        }
        return intdiv(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) - gmmktime(0, 0, 0, 1, 1, $baseYear), 86400);
    }

    private static function dateFromDays(int $days, int $baseYear): string
    {
        return gmdate('Y-m-d', gmmktime(0, 0, 0, 1, 1, $baseYear) + $days * 86400);
    }
}

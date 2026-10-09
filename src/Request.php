<?php
declare(strict_types=1);

namespace Magic;

use Magic\Geo\Geocoder;
use Magic\Share\ShareCode;
use Magic\Share\ShareLink;
use Magic\Time\Zone;

/** Validates the query string of the main page and turns it into chart inputs. */
final class Request
{
    public const MODES = ['self', 'love'];

    /**
     * Which mode the query asks for: 'self' | 'love' | null (chooser). A missing mode with
     * date/city present is Self, so old shared links keep working; hidden details (`h`, `import`) without a
     * mode mean Love; an unknown mode is null.
     * @param array<string,mixed> $q
     */
    public static function mode(array $q): ?string
    {
        if (isset($q['mode'])) {
            $m = $q['mode'];
            return is_string($m) && in_array($m, self::MODES, true) ? $m : null;
        }
        if (isset($q['h']) || (isset($q['import']) && is_string($q['import']) && trim($q['import']) !== '')) {
            return 'love';
        }
        return (isset($q['date']) || isset($q['city'])) ? 'self' : null;
    }

    /**
     * The hidden person of a request: `h` (a version 3 code) wins over `import` (a pasted link or code).
     * An empty `import` is ignored. `invalid` is true when something was given but is not usable.
     * @param array<string,mixed> $q already filtered to plain strings
     * @return array{code: ?string, person: ?array<string,string>, invalid: bool}
     */
    public static function hiddenCode(array $q): array
    {
        $raw = null;
        if (isset($q['h']) && is_string($q['h'])) {
            $raw = $q['h'];
        } elseif (isset($q['import']) && is_string($q['import']) && trim($q['import']) !== '') {
            $raw = ShareCode::extractHidden($q['import']);
            if ($raw === null) {
                return ['code' => null, 'person' => null, 'invalid' => true];
            }
        }
        if ($raw === null) {
            return ['code' => null, 'person' => null, 'invalid' => false];
        }
        $person = ShareCode::decodeHidden($raw);
        return $person === null
            ? ['code' => null, 'person' => null, 'invalid' => true]
            : ['code' => $raw, 'person' => $person, 'invalid' => false];
    }

    /**
     * `noaudit` (any value, plain string) asks not to record the request. Not a security control.
     * @param array<string,mixed> $q already filtered to plain strings
     */
    public static function noAudit(array $q): bool
    {
        return isset($q['noaudit']);
    }

    /**
     * The shared tarot spread (`t=`): validated slots, or `invalid` when a value was given but is not usable.
     * @param array<string,mixed> $q
     * @return array{slots: ?list<array{number:int, reversed:bool}>, invalid: bool}
     */
    public static function parseTarot(array $q): array
    {
        if (!isset($q['t']) || !is_string($q['t'])) {
            return ['slots' => null, 'invalid' => false];
        }
        $slots = ShareLink::parseTarot($q['t']);
        return ['slots' => $slots, 'invalid' => $slots === null];
    }

    /**
     * Reference "today" (Y-m-d): a valid `on=` override (1900-2100), else the real date.
     * @param array<string,mixed> $q
     */
    public static function parseToday(array $q, string $realToday): string
    {
        $on = $q['on'] ?? null;
        if (is_string($on) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $on, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 1900 && (int) $m[1] <= 2100) {
            return $on;
        }
        return $realToday;
    }

    /**
     * Reading day (Y-m-d): a valid `on=` override, else the calendar day at the current position's
     * time zone for the given instant, else the UTC date.
     * @param array<string,mixed> $q
     */
    public static function resolveToday(array $q, int $nowUnix, ?string $tz): string
    {
        $on = self::validOn($q);
        if ($on !== null) {
            return $on;
        }
        return Zone::dateAt($nowUnix, $tz !== null && Zone::isValid($tz) ? $tz : 'UTC');
    }

    /** Where the reading day comes from: 'on' | 'current_position' | 'utc'. @param array<string,mixed> $q */
    public static function dayBasis(array $q, ?string $tz): string
    {
        if (self::validOn($q) !== null) {
            return 'on';
        }
        return $tz !== null && Zone::isValid($tz) ? 'current_position' : 'utc';
    }

    /** @param array<string,mixed> $q */
    private static function validOn(array $q): ?string
    {
        $on = $q['on'] ?? null;
        if (is_string($on) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $on, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 1900 && (int) $m[1] <= 2100) {
            return $on;
        }
        return null;
    }

    /**
     * @param array<string,mixed> $q usually $_GET
     * @return array{input: ?array, errors: list<string>, notes: list<string>}
     */
    public static function parse(array $q, Geocoder $geocoder): array
    {
        $errors = [];
        $name = '';
        if (is_string($q['name'] ?? null) && trim($q['name']) !== '') {
            $nameErr = null;
            $name = self::parseName($q['name'], $nameErr);
            if ($nameErr !== null) {
                $errors[] = $nameErr;
            }
        }
        $r = self::parsePerson($q, '', $geocoder, true, '');
        $p = $r['person'];
        if ($p === null || $errors) {
            return ['input' => null, 'errors' => array_merge($errors, $r['errors']), 'notes' => $r['notes']];
        }
        $input = [
            'year' => $p['date']['year'], 'month' => $p['date']['month'], 'day' => $p['date']['day'],
            'hour' => $p['time']['hour'], 'minute' => $p['time']['minute'],
            'lat' => $p['place']['lat'], 'lon' => $p['place']['lon'], 'tz' => $p['place']['tz'], 'city' => $p['place']['city'],
            'now' => $p['now'],
        ];
        if ($name !== '') {
            $input['name'] = $name;
        }
        return ['input' => $input, 'errors' => [], 'notes' => $r['notes']];
    }

    /**
     * Love mode: persons `a_` (you) and `b_` (loved one).
     * @param array<string,mixed> $q
     * @return array{a: ?array, b: ?array, errors: list<string>, notes: list<string>}
     */
    public static function parseLove(array $q, Geocoder $geocoder): array
    {
        $errors = [];
        $notes = [];
        $out = ['a' => null, 'b' => null];
        foreach (['a' => ['a_', true, 'You: '], 'b' => ['b_', false, 'Loved person: ']] as $k => [$p, $required, $who]) {
            $nameErr = null;
            $name = self::parseName($q[$p . 'name'] ?? '', $nameErr);
            if ($nameErr !== null) {
                $errors[] = $who . $nameErr;
            }
            $r = self::parsePerson($q, $p, $geocoder, $required, $who);
            array_push($errors, ...$r['errors']);
            array_push($notes, ...$r['notes']);
            if ($nameErr === null && !$r['errors'] && $r['person'] !== null) {
                $out[$k] = ['name' => $name] + $r['person'];
            }
        }
        if ($errors) {
            $out = ['a' => null, 'b' => null];
        }
        return $out + ['errors' => $errors, 'notes' => $notes];
    }

    /** @param mixed $raw @param-out ?string $error */
    private static function parseName(mixed $raw, ?string &$error): string
    {
        $error = null;
        $name = trim(is_string($raw) ? $raw : '');
        $len = preg_match_all('/./u', $name);
        if ($len === false || preg_match('/\p{Cc}/u', $name) === 1) {
            $error = 'Please enter a valid name.';
        } elseif ($len < 1) {
            $error = 'Please enter a name.';
        } elseif ($len > 40) {
            $error = 'The name can have at most 40 characters.';
        } elseif (preg_match('/\p{L}/u', $name) !== 1) {
            $error = 'The name must contain at least one letter.';
        }
        return $name;
    }

    /**
     * One person's birth data. Required: date, time and city all needed (strict).
     * Optional: each part validated when present; a time is used only with date and place,
     * a place only with date and time (otherwise ignored with a note).
     * @param array<string,mixed> $q
     * @return array{person: ?array, errors: list<string>, notes: list<string>}
     */
    public static function parsePerson(array $q, string $p, Geocoder $geocoder, bool $required, string $who): array
    {
        $errors = [];
        $notes = [];
        $str = static fn (string $k): string => is_string($q[$k] ?? null) ? $q[$k] : '';
        $date = trim($str($p . 'date'));
        $time = trim($str($p . 'time'));
        $city = trim($str($p . 'city'));
        if ($required) {
            // Same strict behaviour and messages as before (no trimming of date/time).
            $date = $str($p . 'date');
            $time = $str($p . 'time');
        }

        $d = $t = null;
        if ($required || $date !== '') {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1000 || (int) $m[1] > 2100) {
                $errors[] = $who . 'Please enter a valid birth date.';
            } else {
                $d = ['year' => (int) $m[1], 'month' => (int) $m[2], 'day' => (int) $m[3]];
            }
        }
        $timeOk = false;
        if ($required || $time !== '') {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
                $errors[] = $who . 'Please enter the birth time as hh:mm.';
            } else {
                $t = ['hour' => (int) $m[1], 'minute' => (int) $m[2]];
                $timeOk = true;
            }
        }
        if ($required && $city === '') {
            $errors[] = $who . 'Please enter your birth city.';
        }
        if ($errors) {
            return ['person' => null, 'errors' => $errors, 'notes' => $notes];
        }

        if (!$required) {
            if ($t !== null && ($d === null || $city === '')) {
                $notes[] = $who . 'Birth time ignored: it needs a date and a place.';
                $t = null;
            }
            if ($city !== '' && ($d === null || !$timeOk)) {
                $notes[] = $who . 'Birth place ignored: it needs a date and a time.';
                $city = '';
            }
        }

        $place = null;
        if ($city !== '' && $d !== null && $t !== null) {
            $lat = filter_var(is_scalar($q[$p . 'lat'] ?? null) ? $q[$p . 'lat'] : null, FILTER_VALIDATE_FLOAT);
            $lon = filter_var(is_scalar($q[$p . 'lon'] ?? null) ? $q[$p . 'lon'] : null, FILTER_VALIDATE_FLOAT);
            $tz = $str($p . 'tz');
            $hasPlace = $lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && Zone::isValid($tz);
            if (!$hasPlace) {
                // No JavaScript (or tampered fields): resolve the city text on the server.
                $found = $geocoder->search($city)[0] ?? null;
                if ($found === null) {
                    return ['person' => null, 'errors' => [$who . "We couldn't find a city called “{$city}”. Try another spelling."], 'notes' => $notes];
                }
                [$lat, $lon, $tz] = [$found['lat'], $found['lon'], $found['timeZone']];
                $city = Geocoder::label($found);
                $notes[] = $who . "Using {$city}.";
            }
            $place = ['lat' => round((float) $lat, 5), 'lon' => round((float) $lon, 5), 'tz' => $tz, 'city' => $city];
        }

        $pos = self::parsePosition($q, $p, $geocoder, $who);
        array_push($notes, ...$pos['notes']);

        return ['person' => ['date' => $d, 'time' => $t, 'place' => $place, 'now' => $pos['now']], 'errors' => [], 'notes' => $notes];
    }

    /**
     * The optional current position (`pos_city`, `pos_lat`, `pos_lon`, `pos_tz`, with the person prefix).
     * Never an error: an unusable position is dropped with a note.
     * @param array<string,mixed> $q
     * @return array{now: ?array{lat:float,lon:float,tz:string,city:string}, notes: list<string>}
     */
    private static function parsePosition(array $q, string $p, Geocoder $geocoder, string $who): array
    {
        $str = static fn (string $k): string => is_string($q[$k] ?? null) ? $q[$k] : '';
        $city = trim($str($p . 'pos_city'));
        if ($city === '') {
            return ['now' => null, 'notes' => []];
        }
        $ignored = ['now' => null, 'notes' => [$who . "Current position ignored: we couldn't find “" . mb_substr($city, 0, 80, 'UTF-8') . '”.']];
        if (mb_strlen($city, 'UTF-8') > 80 || preg_match('/\p{Cc}/u', $city) === 1) {
            return $ignored;
        }
        $lat = filter_var(is_scalar($q[$p . 'pos_lat'] ?? null) ? $q[$p . 'pos_lat'] : null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var(is_scalar($q[$p . 'pos_lon'] ?? null) ? $q[$p . 'pos_lon'] : null, FILTER_VALIDATE_FLOAT);
        $tz = $str($p . 'pos_tz');
        $notes = [];
        if (!($lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && Zone::isValid($tz))) {
            $found = $geocoder->search($city)[0] ?? null;
            if ($found === null) {
                return $ignored;
            }
            [$lat, $lon, $tz] = [$found['lat'], $found['lon'], $found['timeZone']];
            $city = Geocoder::label($found);
            $notes[] = $who . "Using {$city} as the current position.";
        }
        return ['now' => ['lat' => round((float) $lat, 5), 'lon' => round((float) $lon, 5), 'tz' => $tz, 'city' => $city], 'notes' => $notes];
    }
}

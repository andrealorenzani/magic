<?php
declare(strict_types=1);

namespace Magic\Audit;

/** Pure builder of the audit record (persons + YAML summary of the response). No I/O. */
final class AuditRecord
{
    public const FORMAT_VERSION = 1;
    public const MAX_YAML_BYTES = 65536;

    /**
     * @param array<string,mixed> $input Request::parse()['input'] (an optional 'name' is stored; the Self form has none)
     * @param array<string,mixed> $view SelfReading::build()
     * @return array<string,mixed>
     */
    public static function fromSelf(array $input, array $view, string $today): array
    {
        $chart = $view['chart'];
        $planets = [];
        foreach ($chart['planets'] as $id => $p) {
            $planets[$id] = self::pos($p['position']) + ['retrograde' => (bool) $p['retrograde']];
        }
        $bio = [];
        foreach ($view['bio'] as $cycle => $c) {
            $bio[$cycle] = $c['value'];
        }
        $doc = [
            'functionality' => 'self',
            'on_date' => $today,
            'chart' => [
                'utc' => $chart['utc'],
                'polar' => (bool) $chart['polar'],
                'sun' => self::pos($chart['sun']),
                'ascendant' => self::pos($chart['ascendant']),
                'moon' => self::pos($chart['moon']),
                'planets_supported' => (bool) $chart['planetsSupported'],
                'planets' => $planets,
                'north_node' => self::pos($chart['node']),
            ],
            'affinity' => [
                'most_affine' => array_map(static fn (array $e): string => $e['sign']['name'], $view['affinity']['mostAffine']),
                'soulmate' => $view['affinity']['soulmate']['sign']['name'],
            ],
            'biorhythm' => $bio,
            'notes' => array_values($view['notes']),
        ];
        $person = [
            'role' => 'self',
            'name' => (string) ($input['name'] ?? ''),
            'birth_date' => sprintf('%04d-%02d-%02d', $input['year'], $input['month'], $input['day']),
            'birth_time' => sprintf('%02d:%02d:00', $input['hour'], $input['minute']),
            'place_label' => self::label($input['city'] ?? null),
            'lat' => round((float) $input['lat'], 5),
            'lon' => round((float) $input['lon'], 5),
            'tz' => (string) $input['tz'],
        ];
        return self::record('self', $today, [$person], $doc);
    }

    /**
     * @param array<string,mixed> $a Request::parseLove() person A (role user)
     * @param array<string,mixed> $b person B (role loved)
     * @param array<string,mixed> $view LoveReading::build()
     * @param ?array<string,mixed> $self optional Self-discovery input (role self)
     * @return array<string,mixed>
     */
    public static function fromLove(array $a, array $b, array $view, string $today, ?array $self = null): array
    {
        $aff = $view['affinity'];
        $counts = [];
        foreach ($aff['counts'] as $letter => $n) {
            $counts[strtolower((string) $letter)] = $n;
        }
        $charts = [];
        foreach (['a', 'b'] as $k) {
            $c = $view['charts'][$k];
            $charts[$k] = $c === null ? null : [
                'sun' => $c['sun']['name'] ?? null,
                'moon' => $c['moon']['name'] ?? null,
                'ascendant' => $c['ascendant']['name'] ?? null,
                'sun_approx' => (bool) ($c['approx']['sun'] ?? false),
                'moon_approx' => (bool) ($c['approx']['moon'] ?? false),
            ];
        }
        $tarot = [];
        foreach ($view['tarot'] as $t) {
            $tarot[] = ['date' => $t['date'], 'card' => $t['card']['name'], 'reversed' => (bool) $t['reversed']];
        }
        $doc = [
            'functionality' => 'love',
            'on_date' => $today,
            'name_affinity' => ['percent' => $aff['percent'], 'counts' => $counts, 'chain' => $aff['chain']],
            'charts' => $charts,
            'common' => ['score' => $view['common']['score'] ?? null],
            'synchrony' => [
                'overall' => isset($view['bio']['overall']) ? round((float) $view['bio']['overall'], 1) : null,
                'band' => $view['bio']['band'] ?? null,
            ],
            'tarot' => $tarot,
            'notes' => array_values($view['notes']),
        ];
        $persons = [];
        if ($self !== null) {
            $persons[] = self::fromInput('self', $self);
        }
        $persons[] = self::fromPerson('user', $a);
        $persons[] = self::fromPerson('loved', $b);
        return self::record('love', $today, $persons, $doc);
    }

    /** @param list<array<string,mixed>> $persons @param array<string,mixed> $doc @return array<string,mixed> */
    private static function record(string $functionality, string $today, array $persons, array $doc): array
    {
        $yaml = Yaml::dump($doc);
        if (strlen($yaml) > self::MAX_YAML_BYTES) {
            $yaml = Yaml::dump(['functionality' => $functionality, 'on_date' => $today, 'truncated' => true]);
        }
        return [
            'functionality' => $functionality,
            'on_date' => $today,
            'format_version' => self::FORMAT_VERSION,
            'persons' => $persons,
            'response_yaml' => $yaml,
        ];
    }

    /** @param array<string,mixed> $in Request::parse() input shape @return array<string,mixed> */
    private static function fromInput(string $role, array $in): array
    {
        return [
            'role' => $role, 'name' => (string) ($in['name'] ?? ''),
            'birth_date' => sprintf('%04d-%02d-%02d', $in['year'], $in['month'], $in['day']),
            'birth_time' => sprintf('%02d:%02d:00', $in['hour'], $in['minute']),
            'place_label' => self::label($in['city'] ?? null),
            'lat' => round((float) $in['lat'], 5), 'lon' => round((float) $in['lon'], 5), 'tz' => (string) $in['tz'],
        ];
    }

    /** @param array<string,mixed> $p Request::parsePerson() person + name @return array<string,mixed> */
    private static function fromPerson(string $role, array $p): array
    {
        $d = $p['date'] ?? null;
        $t = $p['time'] ?? null;
        $pl = $p['place'] ?? null;
        // A time is stored only when the request used it (needs date and place).
        $timeUsed = $t !== null && $d !== null && $pl !== null;
        return [
            'role' => $role,
            'name' => (string) $p['name'],
            'birth_date' => $d === null ? null : sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']),
            'birth_time' => $timeUsed ? sprintf('%02d:%02d:00', $t['hour'], $t['minute']) : null,
            'place_label' => $pl === null ? null : self::label($pl['city'] ?? null),
            'lat' => $pl === null ? null : round((float) $pl['lat'], 5),
            'lon' => $pl === null ? null : round((float) $pl['lon'], 5),
            'tz' => $pl === null ? null : (string) $pl['tz'],
        ];
    }

    private static function label(mixed $city): ?string
    {
        return is_string($city) && $city !== '' ? mb_substr($city, 0, 255, 'UTF-8') : null;
    }

    /** @param array<string,mixed> $pos @return array<string,mixed> */
    private static function pos(array $pos): array
    {
        return ['sign' => $pos['name'], 'degree' => $pos['degree'], 'minute' => $pos['minute']];
    }
}

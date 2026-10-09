<?php
declare(strict_types=1);

namespace Magic;

use Magic\Bio\Biorhythm;
use Magic\Bio\Synchrony;
use Magic\Earth\Geography;
use Magic\Love\Common;
use Magic\Love\NameAffinity;
use Magic\Love\Synastry;
use Magic\Sky\Today;
use Magic\Tarot\Reading;

/** Pure builder of the Love view-model. */
final class LoveReading
{
    /**
     * @param array<string,mixed> $a person shape (see Request::parseLove)
     * @param array<string,mixed> $b person shape; date/time/place may be null
     * @param ?list<array{number:int, reversed:bool}> $tarotSlots a validated shared spread, used instead of the deterministic one
     * @param string $dayBasis where the reading day came from: 'utc' | 'current_position' | 'on'
     * @return array<string,mixed>
     */
    public static function build(array $a, array $b, string $today, ?array $tarotSlots = null, string $dayBasis = 'utc'): array
    {
        $chartA = self::chart($a);
        $chartB = self::chart($b);
        $notes = [];
        if ($b['date'] === null) {
            $notes[] = 'Add the loved person\'s birth date to compare signs and biorhythms.';
        }
        $bio = null;
        if ($a['date'] !== null && $b['date'] !== null) {
            $bio = Synchrony::pair(self::day($a['date']), self::day($b['date']), Biorhythm::dayOf($today));
        }
        $common = ($chartA !== null && $chartB !== null) ? Common::between($chartA, $chartB) : null;
        $seed = strtolower(trim($a['name'])) . '|' . strtolower(trim($b['name'])) . '|' . self::ymd($a['date']) . '|' . self::ymd($b['date']);
        return [
            'names' => ['a' => $a['name'], 'b' => $b['name']],
            'affinity' => NameAffinity::compute($a['name'], $b['name']),
            'charts' => ['a' => $chartA, 'b' => $chartB],
            'common' => $common,
            'bio' => $bio,
            'tarot' => $tarotSlots !== null ? Reading::fromSlots($tarotSlots) : Reading::spread($seed, $today),
            'tarotShared' => $tarotSlots !== null,
            'today' => $today,
            'dayBasis' => $dayBasis,
            'dayZone' => $dayBasis === 'current_position' ? ($a['now']['tz'] ?? null) : null,
            'sky' => Today::for($today, array_filter(['a' => $chartA['sun'] ?? null, 'b' => $chartB['sun'] ?? null])),
            'synastry' => self::synastry($a, $b),
            'geo' => Geography::forLove($a, $b, $today),
            'notes' => $notes,
        ];
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @return array<string,mixed> */
    private static function synastry(array $a, array $b): array
    {
        if ($a['date'] === null || $b['date'] === null) {
            return ['rows' => [], 'total' => 0, 'counts' => ['harmonious' => 0, 'tense' => 0, 'neutral' => 0], 'approx' => true, 'available' => false];
        }
        return Synastry::between(self::longitudes($a), self::longitudes($b));
    }

    /** @param array<string,mixed> $p @return array<string,float> */
    private static function longitudes(array $p): array
    {
        $d = $p['date'];
        $full = $p['time'] !== null && $p['place'] !== null;
        return Chart::longitudes(
            $d['year'], $d['month'], $d['day'],
            $full ? $p['time']['hour'] : null, $full ? $p['time']['minute'] : null,
            $full ? $p['place']['lat'] : null, $full ? $p['place']['lon'] : null, $full ? $p['place']['tz'] : null
        );
    }

    /** @param array<string,mixed> $p @return ?array<string,mixed> */
    private static function chart(array $p): ?array
    {
        if ($p['date'] === null) {
            return null;
        }
        $d = $p['date'];
        $full = $p['time'] !== null && $p['place'] !== null;
        return Chart::partial(
            $d['year'], $d['month'], $d['day'],
            $full ? $p['time']['hour'] : null, $full ? $p['time']['minute'] : null,
            $full ? $p['place']['lat'] : null, $full ? $p['place']['lon'] : null, $full ? $p['place']['tz'] : null
        );
    }

    /** @param array{year:int,month:int,day:int} $d */
    private static function day(array $d): int
    {
        return Biorhythm::dayNumber($d['year'], $d['month'], $d['day']);
    }

    private static function ymd(?array $d): string
    {
        return $d === null ? '' : sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
    }
}

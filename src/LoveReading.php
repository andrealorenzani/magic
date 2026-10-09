<?php
declare(strict_types=1);

namespace Magic;

use Magic\Bio\Biorhythm;
use Magic\Bio\Synchrony;
use Magic\Love\Common;
use Magic\Love\NameAffinity;
use Magic\Tarot\Reading;

/** Pure builder of the Love view-model. */
final class LoveReading
{
    /**
     * @param array<string,mixed> $a person shape (see Request::parseLove)
     * @param array<string,mixed> $b person shape; date/time/place may be null
     * @return array<string,mixed>
     */
    public static function build(array $a, array $b, string $today): array
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
            'tarot' => Reading::draw($seed, $today),
            'today' => $today,
            'notes' => $notes,
        ];
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

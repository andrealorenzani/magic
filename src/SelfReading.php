<?php
declare(strict_types=1);

namespace Magic;

use Magic\Bio\Biorhythm;
use Magic\Earth\Geography;
use Magic\Astro\Angles;
use Magic\Love\SignAffinity;
use Magic\Sky\Today;
use Magic\Time\Zone;

/** Pure builder of the Self-discovery view-model. */
final class SelfReading
{
    /**
     * @param array<string,mixed> $input Request::parse()['input']
     * @param string $today 'Y-m-d' (supplied by the controller)
     * @param string $dayBasis where the reading day came from: 'utc' | 'current_position' | 'on'
     * @return array<string,mixed>
     */
    public static function build(array $input, string $today, string $dayBasis = 'utc'): array
    {
        $chart = Chart::full(
            $input['year'], $input['month'], $input['day'], $input['hour'], $input['minute'],
            $input['lat'], $input['lon'], $input['tz']
        );
        $refs = [
            'sun' => $chart['sun']['index'],
            'moon' => $chart['moon']['index'],
            'ascendant' => $chart['ascendant']['index'],
        ];
        if ($chart['planetsSupported']) {
            $refs['venus'] = $chart['planets']['venus']['position']['index'];
            $refs['mars'] = $chart['planets']['mars']['position']['index'];
        }
        $notes = ["Computed for {$chart['utc']} UTC at {$input['tz']}."];
        if ($chart['polar']) {
            $notes[] = 'Born beyond the polar circle: the Ascendant, Midheaven and houses are only approximate here.';
        }
        if (!$chart['planetsSupported']) {
            $notes[] = 'Planets are available for births between 1800 and 2100 only.';
        }
        return [
            'chart' => $chart,
            'refs' => $refs,
            'affinity' => SignAffinity::rank($refs),
            'bio' => Biorhythm::forPerson(
                Biorhythm::dayNumber($input['year'], $input['month'], $input['day']),
                Biorhythm::dayOf($today)
            ),
            'today' => $today,
            'dayBasis' => $dayBasis,
            'dayZone' => $dayBasis === 'current_position' ? ($input['now']['tz'] ?? null) : null,
            'wheel' => ChartWheel::layout($chart),
            'sky' => Today::for($today, ['self' => $chart['sun']]),
            'moonAtBirth' => Today::phaseAt(Angles::julianDay(Zone::toUnix(
                $input['year'], $input['month'], $input['day'], $input['hour'], $input['minute'], $input['tz']
            ))),
            'geo' => Geography::forSelf($input, $today),
            'notes' => $notes,
        ];
    }
}

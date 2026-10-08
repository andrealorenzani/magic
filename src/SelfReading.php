<?php
declare(strict_types=1);

namespace Arcana;

use Arcana\Bio\Biorhythm;
use Arcana\Love\SignAffinity;

/** Pure builder of the Self-discovery view-model. */
final class SelfReading
{
    /**
     * @param array<string,mixed> $input Request::parse()['input']
     * @param string $today 'Y-m-d' (supplied by the controller)
     * @return array<string,mixed>
     */
    public static function build(array $input, string $today): array
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
            $notes[] = 'Born beyond the polar circle: the Ascendant is only approximate here.';
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
            'notes' => $notes,
        ];
    }
}

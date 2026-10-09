<?php
declare(strict_types=1);

namespace Magic\Sky;

use Magic\Astro\Angles;
use Magic\Astro\Moon;
use Magic\Astro\MoonPhase;
use Magic\Astro\Zodiac;
use Magic\Content\Daily;
use Magic\Love\Common;

/** The sky of a reading day, taken at 12:00 UTC so that the same day always gives the same card. */
final class Today
{
    /**
     * @param string $day 'Y-m-d'
     * @param array<string,array<string,mixed>> $sunSigns Zodiac::fromLongitude() results, by any key (e.g. 'self', 'a', 'b')
     * @return array{day:string, phase:string, phaseName:string, symbol:string, illumination:int, moon:array<string,mixed>, people:array<string,array{level:string, relation:string, reading:string}>}
     */
    public static function for(string $day, array $sunSigns): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $day));
        $jd = Angles::julianDay(gmmktime(12, 0, 0, $m, $d, $y));
        $phase = MoonPhase::at($jd);
        $moon = Zodiac::fromLongitude(Moon::longitude($jd));
        $people = [];
        foreach ($sunSigns as $key => $sun) {
            $level = Common::level($moon, $sun)[0];
            $relation = Daily::RELATION[$level];
            $people[$key] = [
                'level' => $level,
                'relation' => $relation,
                'reading' => Daily::MOOD[$moon['id']] . ' ' . $relation . ' ' . Daily::INVITATION[$phase['phase']],
            ];
        }
        return [
            'day' => $day,
            'phase' => $phase['phase'],
            'phaseName' => Daily::PHASE_NAMES[$phase['phase']],
            'symbol' => Daily::PHASE_SYMBOLS[$phase['phase']],
            'illumination' => (int) round($phase['illumination'] * 100),
            'moon' => $moon,
            'people' => $people,
        ];
    }

    /** Phase of the Moon at a Julian Day (for "born under"). @return array{phase:string, phaseName:string, symbol:string, illumination:int} */
    public static function phaseAt(float $jd): array
    {
        $phase = MoonPhase::at($jd);
        return [
            'phase' => $phase['phase'],
            'phaseName' => Daily::PHASE_NAMES[$phase['phase']],
            'symbol' => Daily::PHASE_SYMBOLS[$phase['phase']],
            'illumination' => (int) round($phase['illumination'] * 100),
        ];
    }
}

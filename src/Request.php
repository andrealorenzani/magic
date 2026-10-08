<?php
declare(strict_types=1);

namespace Arcana;

use Arcana\Geo\Geocoder;
use Arcana\Time\Zone;

/** Validates the query string of the main page and turns it into chart inputs. */
final class Request
{
    /**
     * @param array<string,mixed> $q usually $_GET
     * @return array{input: ?array, errors: list<string>, notes: list<string>}
     */
    public static function parse(array $q, Geocoder $geocoder): array
    {
        $errors = [];
        $notes = [];
        $date = (string) ($q['date'] ?? '');
        $time = (string) ($q['time'] ?? '');
        $city = trim((string) ($q['city'] ?? ''));

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1]) || (int) $d[1] < 1000 || (int) $d[1] > 2100) {
            $errors[] = 'Please enter a valid birth date.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $t)) {
            $errors[] = 'Please enter the birth time as hh:mm.';
        }
        if ($city === '') {
            $errors[] = 'Please enter your birth city.';
        }
        if ($errors) {
            return ['input' => null, 'errors' => $errors, 'notes' => $notes];
        }

        $lat = filter_var($q['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($q['lon'] ?? null, FILTER_VALIDATE_FLOAT);
        $tz = (string) ($q['tz'] ?? '');
        $hasPlace = $lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && Zone::isValid($tz);

        if (!$hasPlace) {
            // No JavaScript (or tampered fields): resolve the city text on the server.
            $found = $geocoder->search($city)[0] ?? null;
            if ($found === null) {
                return ['input' => null, 'errors' => ["We couldn't find a city called “{$city}”. Try another spelling."], 'notes' => $notes];
            }
            [$lat, $lon, $tz] = [$found['lat'], $found['lon'], $found['timeZone']];
            $city = Geocoder::label($found);
            $notes[] = "Using {$city}.";
        }

        return [
            'input' => [
                'year' => (int) $d[1], 'month' => (int) $d[2], 'day' => (int) $d[3],
                'hour' => (int) $t[1], 'minute' => (int) $t[2],
                'lat' => (float) $lat, 'lon' => (float) $lon, 'tz' => $tz, 'city' => $city,
            ],
            'errors' => [],
            'notes' => $notes,
        ];
    }
}

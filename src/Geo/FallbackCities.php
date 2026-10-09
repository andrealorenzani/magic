<?php
declare(strict_types=1);

namespace Magic\Geo;

/** Bundled major cities, used when the geocoding API is unreachable. */
final class FallbackCities
{
    /** [name, country, lat, lon, IANA time zone] */
    private const RAW = [
        ['Rome', 'Italy', 41.8919, 12.5113, 'Europe/Rome'], ['Milan', 'Italy', 45.4643, 9.1895, 'Europe/Rome'],
        ['Naples', 'Italy', 40.8522, 14.2681, 'Europe/Rome'], ['Turin', 'Italy', 45.0703, 7.6869, 'Europe/Rome'],
        ['Palermo', 'Italy', 38.1157, 13.3615, 'Europe/Rome'], ['London', 'United Kingdom', 51.5085, -0.1257, 'Europe/London'],
        ['Paris', 'France', 48.8534, 2.3488, 'Europe/Paris'], ['Berlin', 'Germany', 52.5244, 13.4105, 'Europe/Berlin'],
        ['Madrid', 'Spain', 40.4165, -3.7026, 'Europe/Madrid'], ['Lisbon', 'Portugal', 38.7167, -9.1333, 'Europe/Lisbon'],
        ['Amsterdam', 'Netherlands', 52.3740, 4.8897, 'Europe/Amsterdam'], ['Vienna', 'Austria', 48.2085, 16.3721, 'Europe/Vienna'],
        ['Athens', 'Greece', 37.9838, 23.7278, 'Europe/Athens'], ['Istanbul', 'Turkey', 41.0138, 28.9497, 'Europe/Istanbul'],
        ['Moscow', 'Russia', 55.7522, 37.6156, 'Europe/Moscow'], ['Cairo', 'Egypt', 30.0626, 31.2497, 'Africa/Cairo'],
        ['Lagos', 'Nigeria', 6.4531, 3.3958, 'Africa/Lagos'], ['Johannesburg', 'South Africa', -26.2023, 28.0436, 'Africa/Johannesburg'],
        ['Mumbai', 'India', 19.0728, 72.8826, 'Asia/Kolkata'], ['Delhi', 'India', 28.6519, 77.2315, 'Asia/Kolkata'],
        ['Dubai', 'United Arab Emirates', 25.0657, 55.1713, 'Asia/Dubai'], ['Beijing', 'China', 39.9075, 116.3972, 'Asia/Shanghai'],
        ['Shanghai', 'China', 31.2222, 121.4581, 'Asia/Shanghai'], ['Tokyo', 'Japan', 35.6895, 139.6917, 'Asia/Tokyo'],
        ['Seoul', 'South Korea', 37.5683, 126.9778, 'Asia/Seoul'], ['Singapore', 'Singapore', 1.2897, 103.8501, 'Asia/Singapore'],
        ['Sydney', 'Australia', -33.8678, 151.2073, 'Australia/Sydney'], ['Auckland', 'New Zealand', -36.8485, 174.7633, 'Pacific/Auckland'],
        ['New York', 'United States', 40.7143, -74.006, 'America/New_York'], ['Chicago', 'United States', 41.85, -87.65, 'America/Chicago'],
        ['Los Angeles', 'United States', 34.0522, -118.2437, 'America/Los_Angeles'], ['Toronto', 'Canada', 43.7001, -79.4163, 'America/Toronto'],
        ['Mexico City', 'Mexico', 19.4285, -99.1277, 'America/Mexico_City'], ['São Paulo', 'Brazil', -23.5475, -46.6361, 'America/Sao_Paulo'],
        ['Buenos Aires', 'Argentina', -34.6132, -58.3772, 'America/Argentina/Buenos_Aires'],
    ];

    /** @return list<array{name:string,admin:string,country:string,lat:float,lon:float,timeZone:string}> */
    public static function search(string $query): array
    {
        $q = self::fold($query);
        $out = [];
        foreach (self::RAW as [$name, $country, $lat, $lon, $tz]) {
            if ($q !== '' && str_starts_with(self::fold($name), $q)) {
                $out[] = ['name' => $name, 'admin' => '', 'country' => $country, 'lat' => $lat, 'lon' => $lon, 'timeZone' => $tz];
            }
        }
        return $out;
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, ['ã' => 'a', 'á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ç' => 'c']);
    }
}

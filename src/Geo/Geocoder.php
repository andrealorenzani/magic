<?php
declare(strict_types=1);

namespace Arcana\Geo;

/**
 * City search through the Open-Meteo geocoding API (no key). Returns coordinates and the IANA time zone.
 * Successful responses are cached on disk (cache/) to be polite to the API and to speed up repeat queries.
 */
final class Geocoder
{
    private const ENDPOINT = 'https://geocoding-api.open-meteo.com/v1/search';
    private const TTL = 30 * 86400;

    public function __construct(private readonly string $cacheDir)
    {
    }

    /** @return list<array{name:string,admin:string,country:string,lat:float,lon:float,timeZone:string}> */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 80) {
            return [];
        }
        $cacheFile = $this->cacheDir . '/geo-' . sha1(mb_strtolower($query)) . '.json';
        if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) < self::TTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $results = $this->fetch($query);
        if ($results === null) {
            return FallbackCities::search($query);
        }
        if (is_dir($this->cacheDir) && is_writable($this->cacheDir)) {
            @file_put_contents($cacheFile, json_encode($results, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $results;
    }

    /** @return list<array>|null null on transport/API failure */
    private function fetch(string $query): ?array
    {
        $url = self::ENDPOINT . '?' . http_build_query(['name' => $query, 'count' => 8, 'language' => 'en', 'format' => 'json']);
        $body = $this->httpGet($url);
        $data = $body === null ? null : json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        $out = [];
        foreach ($data['results'] ?? [] as $r) {
            if (empty($r['timezone']) || !isset($r['latitude'], $r['longitude'])) {
                continue;
            }
            $out[] = [
                'name' => (string) $r['name'],
                'admin' => (string) ($r['admin1'] ?? ''),
                'country' => (string) ($r['country'] ?? ''),
                'lat' => (float) $r['latitude'],
                'lon' => (float) $r['longitude'],
                'timeZone' => (string) $r['timezone'],
            ];
        }
        return $out;
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false]);
            $body = curl_exec($ch);
            $ok = $body !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
            curl_close($ch);
            return $ok ? (string) $body : null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => false]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    public static function label(array $c): string
    {
        return implode(', ', array_filter([$c['name'], $c['admin'], $c['country']], static fn ($x) => $x !== ''));
    }
}

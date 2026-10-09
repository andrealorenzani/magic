<?php
declare(strict_types=1);

namespace Magic\Geo;

/**
 * City search through the Open-Meteo geocoding API (no key). Returns coordinates and the IANA time zone.
 * Successful responses are cached on disk (cache/) to be polite to the API and to speed up repeat queries.
 */
final class Geocoder
{
    private const ENDPOINT = 'https://geocoding-api.open-meteo.com/v1/search';
    private const TTL = 30 * 86400;
    public const MAX_FILES = 500;
    public const PRUNE_TO = 400;
    public const PRUNE_EVERY = 86400;

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
            self::pruneDaily($this->cacheDir, time());
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

    /**
     * Keeps the cache bounded: removes expired files first, then the oldest until at most PRUNE_TO remain.
     * Touches only geo-*.json files and never throws.
     * @return int files removed
     */
    public static function prune(string $dir, int $now): int
    {
        $removed = 0;
        try {
            $files = glob(rtrim($dir, '/') . '/geo-*.json') ?: [];
            $info = [];
            foreach ($files as $f) {
                $m = @filemtime($f);
                if ($m === false) {
                    continue;
                }
                if ($now - $m >= self::TTL) {
                    if (@unlink($f)) {
                        $removed++;
                    }
                    continue;
                }
                $info[$f] = $m;
            }
            if (count($info) > self::MAX_FILES) {
                asort($info);
                $excess = count($info) - self::PRUNE_TO;
                foreach (array_keys($info) as $f) {
                    if ($excess-- <= 0) {
                        break;
                    }
                    if (@unlink($f)) {
                        $removed++;
                    }
                }
            }
        } catch (\Throwable) {
            // best effort only
        }
        return $removed;
    }

    /** Runs prune() at most once per PRUNE_EVERY seconds (the marker file's mtime is the throttle). */
    private static function pruneDaily(string $dir, int $now): void
    {
        $marker = rtrim($dir, '/') . '/.pruned';
        $last = @filemtime($marker);
        if ($last !== false && $now - $last < self::PRUNE_EVERY) {
            return;
        }
        @touch($marker, $now);
        self::prune($dir, $now);
    }

    public static function label(array $c): string
    {
        return implode(', ', array_filter([$c['name'], $c['admin'], $c['country']], static fn ($x) => $x !== ''));
    }
}

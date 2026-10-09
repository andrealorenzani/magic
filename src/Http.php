<?php
declare(strict_types=1);

namespace Magic;

/** Request facts derived from a server array passed in by the caller (this class reads no globals). */
final class Http
{
    /** True for HTTPS, also behind a TLS-terminating proxy that sends X-Forwarded-Proto. @param array<string,mixed> $server */
    public static function isSecure(array $server): bool
    {
        $https = $server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        $fwd = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
        return is_string($fwd) && strtolower(trim(explode(',', $fwd)[0])) === 'https';
    }

    /**
     * Directory of the front page as a path without trailing slash ('' at the site root), taken from the
     * requested path; null when it contains anything unusual (callers then use relative links).
     * @param array<string,mixed> $server
     */
    public static function basePath(array $server): ?string
    {
        $uri = $server['REQUEST_URI'] ?? '/';
        if (!is_string($uri)) {
            return null;
        }
        $head = strtok($uri, '?');
        $path = $head === false ? '/' : (string) strtok($head, '#');
        $pos = strpos($path, '/index.php');
        if ($pos !== false) {
            $base = substr($path, 0, $pos);
        } elseif (str_ends_with($path, '/')) {
            $base = rtrim($path, '/');
        } else {
            $base = dirname($path) === '/' ? '' : dirname($path);
        }
        return preg_match('#^(/[A-Za-z0-9._~-]+)*$#', $base) === 1 ? $base : null;
    }

    /** "https://host" or null when the Host header is missing or odd. @param array<string,mixed> $server */
    public static function origin(array $server): ?string
    {
        $host = $server['HTTP_HOST'] ?? '';
        if (!is_string($host) || preg_match('/^[A-Za-z0-9.-]+(:[0-9]{1,5})?$/', $host) !== 1) {
            return null;
        }
        return (self::isSecure($server) ? 'https' : 'http') . '://' . $host;
    }
}

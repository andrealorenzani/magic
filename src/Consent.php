<?php
declare(strict_types=1);

namespace Magic;

/** Terms and Conditions consent: cookie name, lifetime and a safe way to carry the original query through the redirect. */
final class Consent
{
    public const COOKIE = 'magic_terms';
    public const VALUE = '5';
    public const LIFETIME = 31536000; // one year

    /** @param array<string,mixed> $cookies */
    public static function given(array $cookies): bool
    {
        return ($cookies[self::COOKIE] ?? null) === self::VALUE;
    }

    /** Keeps a query string usable in a relative redirect: no control characters, no scheme, bounded length. */
    public static function safeQuery(string $q): string
    {
        $q = ltrim($q, '?');
        if (strlen($q) > 2000 || preg_match('/[\x00-\x20\x7f\\\\]/', $q)) {
            return '';
        }
        return $q;
    }
}

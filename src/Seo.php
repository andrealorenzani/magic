<?php
declare(strict_types=1);

namespace Magic;

/** Search and share metadata for the public pages: head data, structured data, sitemap and robots text (pure, no I/O). */
final class Seo
{
    public const SITE = 'Magic';
    public const IMAGE = 'assets/og-image.png';

    /** Titles by mode ('' = home). Without the Terms cookie every mode shows the same gate and home text, so only the home is indexable and listed in the sitemap. */
    private const PAGES = [
        '' => [
            'title' => 'Sun, Moon & Ascendant Sign Calculator · Magic',
            'description' => 'Find your Sun, Moon and Ascendant signs from your birth date, time and city, then explore your planets, biorhythms and love compatibility.',
            'h1' => 'Read your sky: Sun, Moon & Ascendant',
            'keywords' => 'sun sign calculator, moon sign calculator, ascendant calculator, rising sign, birth chart, biorhythm, zodiac compatibility, tarot reading',
        ],
        'self' => [
            'title' => 'Birth Chart & Biorhythms: Self Discovery · Magic',
            'description' => 'Enter your birth date, time and city to see your Sun, Moon and Ascendant, planets, houses, chart wheel, moon phase and your three biorhythms.',
            'keywords' => 'birth chart, natal chart, sun moon ascendant, planets in signs, houses, midheaven, moon phase, biorhythm',
        ],
        'love' => [
            'title' => 'Soul Affinity: Love Compatibility & Tarot · Magic',
            'description' => 'Compare two souls: attraction, harmony and fights, a Past, Present and Future tarot spread, biorhythm synchrony and the signs you have in common.',
            'keywords' => 'love compatibility, zodiac compatibility, synastry, name affinity, attraction, harmony, biorhythm compatibility, tarot spread',
        ],
    ];

    /**
     * Head data for one request. $rawQuery is the query string as sent; only the home page without any query is indexable.
     * @return array{title:string,description:string,keywords:string,h1:string,index:bool,canonical:?string,image:?string,path:string,mode:?string}
     */
    public static function forRequest(?string $mode, bool $submitted, string $rawQuery, ?string $origin, ?string $base): array
    {
        $key = $mode !== null && isset(self::PAGES[$mode]) ? $mode : '';
        $page = self::PAGES[$key] ?? self::PAGES[''];
        $index = !$submitted && $rawQuery === '' && $mode === null;
        if ($mode === 'friends') {
            $page = ['title' => 'Friends hidden codes · Magic', 'description' => self::PAGES['']['description'], 'keywords' => self::PAGES['']['keywords']];
            $index = false;
        }
        $path = self::path($base, $key);
        return [
            'title' => $page['title'],
            'description' => $page['description'],
            'keywords' => $page['keywords'],
            'h1' => $page['h1'] ?? self::PAGES['']['h1'],
            'index' => $index,
            'canonical' => $origin !== null && $base !== null ? $origin . $path : null,
            'image' => $origin !== null && $base !== null ? $origin . $base . '/' . self::IMAGE : null,
            'path' => $path,
            'mode' => $key === '' ? null : $key,
        ];
    }

    /** Site-relative path of a page: "/" for the home, "/?mode=self" for the others. */
    public static function path(?string $base, string $key): string
    {
        return ($base ?? '') . '/' . ($key === '' ? '' : '?mode=' . $key);
    }

    /** JSON-LD for the page (a single @graph). $faq is a list of [question, answer] shown visibly on the page; $updated is Y-m-d. */
    public static function jsonLd(array $seo, string $updated, array $faq): string
    {
        $url = $seo['canonical'];
        $graph = [];
        if ($url !== null && $seo['mode'] === null) {
            $siteUrl = $url;
            $graph[] = ['@type' => 'WebSite', '@id' => $siteUrl . '#website', 'url' => $siteUrl, 'name' => self::SITE, 'inLanguage' => 'en'];
            $graph[] = [
                '@type' => 'WebApplication', 'name' => self::SITE . ': Sun, Moon & Ascendant', 'url' => $siteUrl,
                'description' => $seo['description'], 'applicationCategory' => 'LifestyleApplication',
                'operatingSystem' => 'Any', 'browserRequirements' => 'Requires a modern web browser', 'inLanguage' => 'en',
            ];
        }
        if ($url !== null) {
            $graph[] = [
                '@type' => 'WebPage', 'url' => $url, 'name' => $seo['title'], 'description' => $seo['description'],
                'inLanguage' => 'en', 'dateModified' => $updated,
            ];
        }
        if ($faq !== []) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $f): array => [
                '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faq)];
        }
        // "<" is escaped so the data can never close the script element.
        return str_replace('<', '<', (string) json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** sitemap.xml listing every public page. */
    public static function sitemap(string $origin, string $base, string $lastmod): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '  <url><loc>' . htmlspecialchars($origin . self::path($base, ''), ENT_XML1) . '</loc><lastmod>' . $lastmod . '</lastmod></url>' . "\n";
        return $xml . "</urlset>\n";
    }

    /** robots.txt: everything public is allowed, the form endpoints are not crawled, the sitemap is announced. */
    public static function robots(string $origin, string $base): string
    {
        return "User-agent: *\nAllow: /\nDisallow: $base/consent.php\nDisallow: $base/hidden.php\nDisallow: $base/api/\n\nSitemap: $origin$base/sitemap.xml\n";
    }
}

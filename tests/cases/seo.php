<?php
declare(strict_types=1);

use Magic\Seo;

check('seo: only the bare home page is indexable and has a canonical built from the request origin', function () {
    $home = Seo::forRequest(null, false, '', 'https://x.test', '');
    same($home['index'], true);
    same($home['canonical'], 'https://x.test/');
    same($home['image'], 'https://x.test/assets/og-image.png');
    foreach ([['self', 'mode=self'], ['love', 'mode=love'], ['friends', 'mode=friends'], [null, 'date=1990-01-01'], [null, 'c=abc']] as [$mode, $q]) {
        same(Seo::forRequest($mode, false, $q, 'https://x.test', '')['index'], false);
    }
    same(Seo::forRequest('self', true, 'mode=self&date=1990-01-01', 'https://x.test', '')['index'], false);
});

check('seo: titles and descriptions have search-friendly lengths and are unique per mode', function () {
    $seen = [];
    foreach ([null, 'self', 'love', 'friends'] as $mode) {
        $s = Seo::forRequest($mode, false, '', null, null);
        same(strlen($s['title']) >= 28 && strlen($s['title']) <= 60, true);
        same(strlen($s['description']) >= 70 && strlen($s['description']) <= 160, true);
        $seen[$s['title']] = true;
    }
    same(count($seen), 4);
});

check('seo: JSON-LD parses, never closes the script element and carries the FAQ and the update date', function () {
    $seo = Seo::forRequest(null, false, '', 'https://x.test', '/m');
    $json = Seo::jsonLd($seo, '2026-10-11', [['Why <b>?', 'Because.']]);
    same(str_contains($json, '</'), false);
    $data = json_decode($json, true);
    $types = array_column($data['@graph'], '@type');
    same(in_array('FAQPage', $types, true) && in_array('WebSite', $types, true), true);
    same($data['@graph'][2]['dateModified'], '2026-10-11');
    same($data['@graph'][0]['url'], 'https://x.test/m/');
});

check('seo: sitemap and robots point at the request origin and keep form endpoints out', function () {
    $xml = Seo::sitemap('https://x.test', '/m', '2026-10-11');
    same(preg_match('#^<\?xml[^>]*\?>\s*<urlset[^>]*>\s*<url><loc>[^<]+</loc><lastmod>[^<]+</lastmod></url>\s*</urlset>\s*$#', $xml), 1);
    same(str_contains($xml, '<loc>https://x.test/m/</loc><lastmod>2026-10-11</lastmod>'), true);
    $robots = Seo::robots('https://x.test', '/m');
    same(str_contains($robots, "Sitemap: https://x.test/m/sitemap.xml\n"), true);
    same(str_contains($robots, 'Disallow: /m/consent.php'), true);
});

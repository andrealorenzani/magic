<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Http;
use Magic\Seo;

// Served as /sitemap.xml (see .htaccess). Host and path come from the request, so no address is stored in the code.
$origin = Http::origin($_SERVER);
if ($origin === null) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo Seo::sitemap($origin, Http::basePath($_SERVER) ?? '', gmdate('Y-m-d', (int) filemtime(MAGIC_ROOT . '/templates/home.php')));

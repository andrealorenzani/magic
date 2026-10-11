<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Http;
use Magic\Seo;

// Served as /robots.txt (see .htaccess). Host and path come from the request.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$origin = Http::origin($_SERVER);
echo $origin === null ? "User-agent: *\nAllow: /\n" : Seo::robots($origin, Http::basePath($_SERVER) ?? '');

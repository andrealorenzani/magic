<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Magic\Geo\Geocoder;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$results = (new Geocoder(MAGIC_ROOT . '/cache'))->search((string) ($_GET['q'] ?? ''));
foreach ($results as &$r) {
    $r['label'] = Geocoder::label($r);
}
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

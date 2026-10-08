<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Arcana\Chart;
use Arcana\Geo\Geocoder;
use Arcana\Request;

$form = [
    'date' => (string) ($_GET['date'] ?? ''), 'time' => (string) ($_GET['time'] ?? '12:00'),
    'city' => (string) ($_GET['city'] ?? ''), 'lat' => (string) ($_GET['lat'] ?? ''),
    'lon' => (string) ($_GET['lon'] ?? ''), 'tz' => (string) ($_GET['tz'] ?? ''),
];
$chart = null;
$errors = [];
$notes = [];

if (isset($_GET['date']) || isset($_GET['city'])) {
    $parsed = Request::parse($_GET, new Geocoder(ARCANA_ROOT . '/cache'));
    $errors = $parsed['errors'];
    $notes = $parsed['notes'];
    if ($parsed['input'] !== null) {
        $in = $parsed['input'];
        $chart = Chart::compute($in['year'], $in['month'], $in['day'], $in['hour'], $in['minute'], $in['lat'], $in['lon'], $in['tz']);
        $notes[] = "Computed for {$chart['utc']} UTC at {$in['tz']}.";
        if ($chart['polar']) {
            $notes[] = 'Born beyond the polar circle: the Ascendant is only approximate here.';
        }
        $form = array_merge($form, ['city' => $in['city'], 'lat' => (string) $in['lat'], 'lon' => (string) $in['lon'], 'tz' => $in['tz']]);
    }
}

require ARCANA_ROOT . '/templates/home.php';

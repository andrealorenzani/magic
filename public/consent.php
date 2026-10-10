<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Consent;
use Magic\Http;

header('Cache-Control: private, no-store');
header('Vary: Cookie');

// Accept or withdraw the Terms and Conditions. Always answers with a redirect to this site's home page.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accept = ($_POST['action'] ?? '') === 'accept';
    setcookie(Consent::COOKIE, $accept ? Consent::VALUE : '', [
        'expires' => $accept ? time() + Consent::LIFETIME : 1,
        'path' => '/',
        'secure' => Http::isSecure($_SERVER),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $next = is_string($_POST['next'] ?? null) ? Consent::safeQuery($_POST['next']) : '';
    if ($accept) {
        header('Location: ./' . ($next !== '' ? '?' . $next : '') . '#results', true, 303);
    } else {
        header('Location: ./?withdrawn=1', true, 303);
    }
    exit;
}
header('Location: ./', true, 303);

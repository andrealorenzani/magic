<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Magic\Consent;

// Accept or withdraw the Terms and Conditions. Always answers with a redirect to this site's home page.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $secure = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    $accept = ($_POST['action'] ?? '') === 'accept';
    setcookie(Consent::COOKIE, $accept ? Consent::VALUE : '', [
        'expires' => $accept ? time() + Consent::LIFETIME : 1,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $next = is_string($_POST['next'] ?? null) ? Consent::safeQuery($_POST['next']) : '';
    header('Location: ./' . ($accept && $next !== '' ? '?' . $next : '') . ($accept ? '#results' : ''), true, 303);
    exit;
}
header('Location: ./', true, 303);

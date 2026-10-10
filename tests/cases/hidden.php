<?php
declare(strict_types=1);

// ADR 0006 phase B: hidden-details code (version 3), import flow, no-leak page, audit of hidden links,
// hidden.php, consent.php withdraw, menu, and static checks of the scripts.
use Magic\Audit\AuditRecord;
use Magic\Consent;
use Magic\Content\Help;
use Magic\LoveReading;
use Magic\Request;
use Magic\Share\ShareCode;

$hiRoot = dirname(__DIR__, 2);
$hiPerson = ['name' => 'Zerbinetta', 'date' => '1987-11-23', 'time' => '04:17', 'city' => 'Reykjavik', 'lat' => '64.14000', 'lon' => '-21.94000', 'tz' => 'Atlantic/Reykjavik'];
$hiPos = ['pos_city' => 'Paris', 'pos_lat' => '48.85000', 'pos_lon' => '2.35000', 'pos_tz' => 'Europe/Paris'];
$hiGolden = [
    'named' => 'MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS1gA',
    'named+pos' => 'MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS12nvJCLHzwrIqgwuTS5g',
    'anonymous' => 'MACwLoQF1mXgeJcYJQSpMry1tTC7NLWA',
    'accents' => 'MA1ab8OrIMOFbHZhcmV6sC6EBdZl4HiXGCUEqTK8tbUwuzS1gA',
];
$hiCode = $hiGolden['named'];
$hiFlip = function (string $code, callable $edit): string {
    $bytes = base64_decode(strtr($code, '-_', '+/') . str_repeat('=', (4 - strlen($code) % 4) % 4));
    return rtrim(strtr(base64_encode($edit($bytes)), '+/', '-_'), '=');
};
$hiLove = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'on' => '2026-10-09'];
$hiSecrets = ['Zerbinetta', 'Reykjavik', '1987-11-23', '23 November', '04:17', 'Atlantic', '64.14', '21.94'];

check('hidden code v3: goldens, round trips, versions 1 and 2 untouched', function () use ($hiPerson, $hiPos, $hiGolden) {
    same(ShareCode::encodeHidden($hiPerson), $hiGolden['named']);
    same(ShareCode::encodeHidden($hiPerson + $hiPos), $hiGolden['named+pos']);
    same(ShareCode::encodeHidden(['name' => ''] + $hiPerson), $hiGolden['anonymous']);
    same(ShareCode::encodeHidden(['name' => 'Zoë Ålvarez'] + $hiPerson), $hiGolden['accents']);
    foreach ($hiGolden as $code) {
        $dec = ShareCode::decodeHidden($code);
        same($dec !== null && ShareCode::encodeHidden($dec) === $code, true);
    }
    same(ShareCode::decodeHidden($hiGolden['named']), $hiPerson);
    same(ShareCode::decodeHidden($hiGolden['named+pos']), $hiPerson + $hiPos);
    same(ShareCode::decodeHidden($hiGolden['anonymous'])['name'], '');
    same(ShareCode::decodeHidden(ShareCode::encodeHidden(['name' => str_repeat("\u{1D49C}", 40)] + $hiPerson))['name'], str_repeat("\u{1D49C}", 40));
    // Existing goldens for versions 1 and 2 are unchanged.
    same(ShareCode::decode('FlpvWFOT_MlDcElu1BXRFJvbWUA')['city'], 'Rome');
    same(ShareCode::decode('JlpvAaC3N1hTk_zJQ3BJbtQV0RSb21lA')['name'], 'Ann');
});
check('hidden code: versions 1, 2 and 3 are not interchangeable', function () use ($hiGolden) {
    same(ShareCode::decodeHidden('FlpvWFOT_MlDcElu1BXRFJvbWUA'), null);
    same(ShareCode::decodeHidden('JlpvAaC3N1hTk_zJQ3BJbtQV0RSb21lA'), null);
    foreach ($hiGolden as $code) {
        same(ShareCode::decode($code), null);
    }
    same(Request::hiddenCode(['h' => 'FlpvWFOT_MlDcElu1BXRFJvbWUA'])['invalid'], true);
});
check('hidden code: malformed input is rejected and never throws', function () use ($hiGolden, $hiFlip) {
    $code = $hiGolden['named+pos'];
    foreach (['', str_repeat('A', 401), 'MApa ZXJi', 'MApaZXJiaW5ldHRh=', 'MApa+XJi', "MApa\n", str_repeat('A', 5)] as $bad) {
        same(ShareCode::decodeHidden($bad), null);
    }
    for ($i = 0; $i < strlen($code); $i++) {
        same(ShareCode::decodeHidden(substr($code, 0, $i)), null);
    }
    same(ShareCode::decodeHidden($code . 'AAAA'), null);
    same(ShareCode::decodeHidden($code . 'A'), null);
    // Flag bits in the header, a wrong version, and changed bits: nothing decodes to something else.
    foreach ([0x01, 0x02, 0x04, 0x08] as $bit) {
        same(ShareCode::decodeHidden($hiFlip($code, fn (string $b): string => chr(ord($b[0]) | $bit) . substr($b, 1))), null);
    }
    foreach ([0x10, 0x20, 0x40, 0x00] as $v) {
        same(ShareCode::decodeHidden($hiFlip($code, fn (string $b): string => chr($v | (ord($b[0]) & 0x0F)) . substr($b, 1))), null);
    }
    for ($i = 0; $i < strlen(base64_decode(strtr($code, '-_', '+/') . '==')); $i++) {
        for ($bit = 0; $bit < 8; $bit++) {
            $mut = $hiFlip($code, function (string $b) use ($i, $bit): string {
                $b[$i] = chr(ord($b[$i]) ^ (1 << $bit));
                return $b;
            });
            $dec = ShareCode::decodeHidden($mut);
            if ($dec !== null && ShareCode::encodeHidden($dec) !== $mut) {
                throw new RuntimeException("non-canonical code accepted at byte $i bit $bit");
            }
        }
    }
});
check('hidden code: invalid people cannot be encoded', function () use ($hiPerson) {
    foreach ([
        ['name' => "An\x07n"], ['name' => '1234'], ['name' => str_repeat('é', 81)], ['date' => '1987-13-23'], ['date' => '0999-01-01'],
        ['time' => '25:00'], ['time' => '4:17'], ['lat' => 91], ['lon' => 181], ['tz' => 'Mars/Base'], ['tz' => '../x'], ['city' => ''],
    ] as $bad) {
        $threw = false;
        try {
            ShareCode::encodeHidden($bad + $hiPerson);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        same($threw, true);
    }
});
check('hidden code: extractHidden finds a code in a bare code or a pasted link', function () use ($hiCode) {
    same(ShareCode::extractHidden($hiCode), $hiCode);
    same(ShareCode::extractHidden("  \n$hiCode\t "), $hiCode);
    same(ShareCode::extractHidden("https://example.org/path/?h=$hiCode"), $hiCode);
    same(ShareCode::extractHidden("https://example.org/?mode=love&h=$hiCode&a_name=Ann"), $hiCode);
    same(ShareCode::extractHidden("https://example.org/?h=$hiCode#frag"), $hiCode);
    same(ShareCode::extractHidden("/?h=$hiCode"), $hiCode);
    foreach (['', '   ', 'hello world', 'https://example.org/?x=1', 'https://example.org/?xh=' . $hiCode, str_repeat('A', 401), 'https://e.org/?h=' . str_repeat('A', 401), str_repeat('a', 2100), "<script>"] as $bad) {
        same(ShareCode::extractHidden($bad), null);
    }
});
check('request: hidden code resolution, h wins over import, empty import ignored, h means Love', function () use ($hiCode, $hiPerson) {
    $r = Request::hiddenCode(['h' => $hiCode]);
    same([$r['code'], $r['person'], $r['invalid']], [$hiCode, $hiPerson, false]);
    same(Request::hiddenCode(['import' => "https://e.org/?h=$hiCode"]), $r);
    same(Request::hiddenCode(['import' => $hiCode, 'h' => 'garbage'])['invalid'], true);
    same(Request::hiddenCode(['h' => $hiCode, 'import' => 'garbage']), $r);
    same(Request::hiddenCode(['import' => '']), ['code' => null, 'person' => null, 'invalid' => false]);
    same(Request::hiddenCode([]), ['code' => null, 'person' => null, 'invalid' => false]);
    same(Request::hiddenCode(['import' => 'not a link'])['invalid'], true);
    same(Request::hiddenCode(['h' => ['x']])['invalid'], false);
    same(Request::mode(['h' => $hiCode]), 'love');
    same(Request::mode(['import' => 'x']), 'love');
    same(Request::mode(['import' => '']), null);
    same(Request::mode(['mode' => 'self', 'h' => $hiCode]), 'self');
});

// ---- the page ----
check('hidden page: nothing about the shared person is rendered; match results, label and hidden input are', function () use ($sharePage, $hiLove, $hiCode, $hiSecrets) {
    $srv = ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/'];
    $get = array_merge($hiLove, ['on' => '2001-02-03']) + ['h' => $hiCode, 'noaudit' => '', 'b_name' => 'Mallory', 'b_date' => '2000-01-01', 'b_city' => 'Oslo'];
    [$out] = $sharePage($get, $srv);
    foreach ($hiSecrets as $secret) {
        same([$secret, str_contains($out, $secret)], [$secret, false]);
    }
    same(str_contains($out, 'Mallory') || str_contains($out, 'Oslo') || str_contains($out, '2000-01-01'), false);
    same(preg_match('/name="b_/', $out), 0);
    same(str_contains($out, '<input type="hidden" name="h" value="' . $hiCode . '">'), true);
    same(str_contains($out, 'Your match'), true);
    same(str_contains($out, 'Details shared with you are loaded and hidden. You will only see the match results.'), true);
    same(str_contains($out, 'has no share link') || str_contains($out, '>Sharing<') || str_contains($out, 'id="share-h"'), false);
    same(preg_match('/Share this reading|share-link|data-qr-copy="share|class="qr"|\?c=/', $out), 0);
    same(str_contains($out, 'id="name-h"') && str_contains($out, 'id="common-h"') && str_contains($out, 'class="tarot__card '), true);
    same(str_contains($out, 'Biorhythm synchrony'), true);
    same(str_contains($out, 'This reading is fixed to 2001-02-03.') && !str_contains($out, 'Open the live version'), true);
    same(str_contains($out, 'name="import"'), false);
    $text = html_entity_decode(strip_tags($out));
    same(str_contains($text, 'Ann & Your match'), true);
    // The pasted-link path gives the same page.
    [$viaImport] = $sharePage(array_merge($hiLove, ['on' => '2001-02-03']) + ['import' => "https://example.org/?h=$hiCode", 'noaudit' => '', 'b_name' => 'Mallory', 'b_date' => '2000-01-01', 'b_city' => 'Oslo'], $srv);
    same($viaImport, $out);
    // Only h: the form with the hidden block, no result.
    [$form] = $sharePage(['h' => $hiCode], $srv);
    same(str_contains($form, 'id="love-form"') && str_contains($form, 'data-hidden-person'), true);
    same(str_contains($form, 'class="tarot__card '), false);
    same(preg_match('/name="b_|Zerbinetta|Reykjavik/', $form), 0);
    same(str_contains($form, 'data-saved-select'), false);
});
check('hidden page: anonymous match shows "nothing to compare"; errors never mention the shared person; invalid code is ignored with a note', function () use ($sharePage, $hiLove, $hiGolden) {
    [$out] = $sharePage($hiLove + ['h' => $hiGolden['anonymous'], 'noaudit' => '']);
    same(str_contains($out, 'There is nothing to compare in these names'), true);
    same(str_contains($out, 'Your match'), true);
    [$out] = $sharePage(['mode' => 'love', 'a_name' => 'Ann', 'a_date' => 'nope', 'h' => $hiGolden['named']]);
    same(str_contains($out, 'note--error'), true);
    same(preg_match('/Zerbinetta|Reykjavik/', $out), 0);
    foreach ([['h' => 'garbage'], ['import' => 'garbage'], ['h' => 'FlpvWFOT_MlDcElu1BXRFJvbWUA']] as $bad) {
        [$out] = $sharePage($hiLove + $bad + ['b_name' => 'Silvia', 'noaudit' => '']);
        same(str_contains($out, 'The hidden details in this link are not valid, so they were ignored.'), true);
        same(str_contains($out, 'name="b_name"') && str_contains($out, 'value="Silvia"'), true);
        same(str_contains($out, 'name="import"'), true);
        same(str_contains($out, 'Share this reading'), true);
    }
    [$out] = $sharePage($hiLove + ['b_name' => 'Silvia', 'noaudit' => '']);
    same(str_contains($out, 'name="import"') && str_contains($out, 'Import from a user'), true);
    same(str_contains($out, 'The hidden details in this link'), false);
    [$out] = $sharePage($hiLove + ['import' => '', 'b_name' => 'Silvia', 'noaudit' => '']);
    same(str_contains($out, 'not valid'), false);
});
check('hidden page: the memory list is not offered and the gate keeps h and import', function () use ($sharePage, $hiLove, $hiCode) {
    [$out] = $sharePage($hiLove + ['h' => $hiCode, 'noaudit' => '']);
    same(str_contains($out, 'data-saved-select'), false);
    same(str_contains($out, 'data-person="loved"'), false);
    $qs = 'h=' . $hiCode;
    [$out] = $sharePage(['h' => $hiCode], ['QUERY_STRING' => $qs, 'REQUEST_URI' => '/?' . $qs], false);
    same(str_contains($out, 'name="next" value="' . $qs . '"'), true);
    same(preg_match('/Zerbinetta|Reykjavik/', $out), 0);
    $imp = 'import=' . rawurlencode("https://example.org/?h=$hiCode");
    [$out] = $sharePage([], ['QUERY_STRING' => $imp, 'REQUEST_URI' => '/?' . $imp], false);
    same(str_contains($out, 'name="next" value="' . htmlspecialchars($imp, ENT_QUOTES) . '"'), true);
});

// ---- audit: opening a hidden link is recorded with both people ----
check('audit: a hidden-link request is attempted; noaudit skips it; the record holds both people and the marker', function () use ($sharePage, $hiLove, $hiCode, $hiPerson) {
    [, , $log] = $sharePage($hiLove + ['h' => $hiCode]);
    same(str_contains($log, 'audit: write failed'), true);
    [, , $log] = $sharePage($hiLove + ['h' => $hiCode, 'noaudit' => '']);
    same($log, '');
    $geo = new Magic\Geo\Geocoder(sys_get_temp_dir());
    $q = $hiLove + array_combine(array_map(fn ($k) => 'b_' . $k, array_keys($hiPerson)), array_values($hiPerson));
    $parsed = Request::parseLove($q, $geo);
    $a = $parsed['a'];
    $b = ['label' => 'Your match', 'anonymous' => false] + $parsed['b'];
    $view = LoveReading::build($a, $b, '2026-10-09');
    same($view['hidden'], true);
    same($view['names']['b'], 'Your match');
    $rec = AuditRecord::fromLove($a, $b, $view, '2026-10-09');
    same($rec['format_version'], AuditRecord::FORMAT_VERSION_HIDDEN);
    same(array_column($rec['persons'], 'role'), ['user', 'loved']);
    $loved = $rec['persons'][1];
    same([$loved['name'], $loved['birth_date'], $loved['birth_time'], $loved['place_label'], $loved['lat'], $loved['lon'], $loved['tz']],
        ['Zerbinetta', '1987-11-23', '04:17:00', 'Reykjavik', 64.14, -21.94, 'Atlantic/Reykjavik']);
    same($rec['persons'][0]['name'], 'Ann');
    same(str_contains($rec['response_yaml'], "loved_person_source: hidden_link\n"), true);
    // A normal Love record is unchanged: version 3 and no marker.
    $bn = $parsed['b'];
    $plain = AuditRecord::fromLove($a, $bn, LoveReading::build($a, $bn, '2026-10-09'), '2026-10-09');
    same($plain['format_version'], AuditRecord::FORMAT_VERSION);
    same(str_contains($plain['response_yaml'], 'hidden_link'), false);
    same($plain['persons'][1]['name'], 'Zerbinetta');
});
check('love view: a shared person is labelled in every shown text; name affinity uses the name only when it was shared', function () use ($hiLove, $hiPerson) {
    $geo = new Magic\Geo\Geocoder(sys_get_temp_dir());
    $q = $hiLove + array_combine(array_map(fn ($k) => 'b_' . $k, array_keys($hiPerson)), array_values($hiPerson)) + ['a_pos_city' => 'Paris', 'a_pos_lat' => '48.85', 'a_pos_lon' => '2.35', 'a_pos_tz' => 'Europe/Paris', 'b_pos_city' => 'Oslo', 'b_pos_lat' => '59.9', 'b_pos_lon' => '10.7', 'b_pos_tz' => 'Europe/Oslo'];
    $p = Request::parseLove($q, $geo);
    $shown = LoveReading::build($p['a'], ['label' => 'Your match'] + $p['b'], '2026-10-09');
    $plain = LoveReading::build($p['a'], $p['b'], '2026-10-09');
    same($shown['affinity']['percent'], $plain['affinity']['percent']);
    same($plain['hidden'], false);
    $anon = LoveReading::build($p['a'], ['label' => 'Your match', 'anonymous' => true, 'name' => ''] + $p['b'], '2026-10-09');
    same($anon['affinity']['noLetters'], true);
    foreach ($shown['geo']['pairs'] as $pair) {
        same(preg_match('/Zerbinetta|Oslo|Reykjavik/', $pair['label']), 0);
    }
});

// ---- hidden.php and consent.php over HTTP ----
$hiPost = function (array $f, bool $cookie = true, ?string $raw = null) use ($shareHttp): array {
    $hdr = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookie) {
        $hdr[] = 'Cookie: magic_terms=5';
    }
    [$code, $h, $body] = $shareHttp('POST', '/hidden.php', $raw ?? http_build_query($f), $hdr);
    return [$code, $h, $body, json_decode($body, true)];
};
$hiMemory = ['name' => 'Zerbinetta', 'date' => '1987-11-23', 'time' => '04:17', 'city' => 'Reykjavik', 'lat' => '64.14', 'lon' => '-21.94', 'tz' => 'Atlantic/Reykjavik',
    'pos_city' => '', 'pos_lat' => '', 'pos_lon' => '', 'pos_tz' => ''];
check('hidden.php: terms cookie and POST required, bad input rejected, nothing echoed', function () use ($hiPost, $hiMemory, $shareHttp) {
    [$code, , , $j] = $hiPost($hiMemory, false);
    same([$code, $j], [403, ['ok' => false, 'error' => 'terms']]);
    [$code, $h, , $j] = $shareHttp('GET', '/hidden.php', '', ['Cookie: magic_terms=5']) + [3 => null];
    same($code, 405);
    same(str_contains(implode("\n", $h), 'Allow: POST'), true);
    [$code, , , $j] = $hiPost(['lat' => '', 'lon' => ''] + $hiMemory);
    same([$code, $j['error']], [422, 'data']);
    foreach ([['tz' => ''], ['tz' => '../etc'], ['lat' => '99'], ['lon' => 'x'], ['date' => '1987-13-23'], ['time' => '25:00'], ['name' => "An\x07n"], ['name' => str_repeat('a', 41)], ['city' => '']] as $bad) {
        [$code, $h, $body, $j] = $hiPost($bad + $hiMemory);
        same([$code, $j['ok']], [422, false]);
        same(preg_match('/Zerbinetta|Reykjavik/', $body), 0);
    }
    [$code, , , $j] = $hiPost([], true, 'x=' . str_repeat('a', 5000));
    same([$code, $j['ok']], [413, false]);
});
check('hidden.php: a valid post returns a link whose code decodes to the details, a QR path, no-store headers and nothing in the logs', function () use ($hiPost, $hiMemory, $shareTmp) {
    @unlink($shareTmp . '/srv.log');
    [$code, $h, $body, $j] = $hiPost($hiMemory);
    $hs = implode("\n", $h);
    same($code, 200);
    same($j['ok'], true);
    same(preg_match('~^http://127\.0\.0\.1:\d+/\?h=([A-Za-z0-9_-]+)$~', $j['link'], $m), 1);
    same(ShareCode::decodeHidden($m[1]), ['name' => 'Zerbinetta', 'date' => '1987-11-23', 'time' => '04:17', 'city' => 'Reykjavik', 'lat' => '64.14000', 'lon' => '-21.94000', 'tz' => 'Atlantic/Reykjavik']);
    same(preg_match('/^[MhvHz0-9 ]+$/', $j['qr']['path']), 1);
    same($j['tooLong'], false);
    same(in_array($j['qr']['size'] - 8, [21, 25, 29, 33, 37, 41], true), true);
    same(str_contains($hs, 'Cache-Control: private, no-store') && preg_match('/^Vary:.*Cookie/mi', $hs) === 1 && str_contains($hs, 'X-Content-Type-Options: nosniff'), true);
    same(preg_match('/Zerbinetta|Reykjavik|1987/', $body), 0);
    [, , , $j2] = $hiPost(['name' => '', 'pos_city' => 'Paris', 'pos_lat' => '48.85', 'pos_lon' => '2.35', 'pos_tz' => 'Europe/Paris'] + $hiMemory);
    preg_match('/h=([A-Za-z0-9_-]+)$/', $j2['link'], $m2);
    $dec = ShareCode::decodeHidden($m2[1]);
    same([$dec['name'], $dec['pos_city'], $dec['pos_tz']], ['', 'Paris', 'Europe/Paris']);
    // An unusable current position is dropped instead of failing.
    [, , , $j3] = $hiPost(['pos_city' => 'Paris', 'pos_lat' => '999', 'pos_lon' => '2', 'pos_tz' => 'Europe/Paris'] + $hiMemory);
    same($j3['ok'], true);
    same(isset(ShareCode::decodeHidden(substr($j3['link'], strrpos($j3['link'], '=') + 1))['pos_city']), false);
    $log = is_file($shareTmp . '/srv.log') ? (string) file_get_contents($shareTmp . '/srv.log') : '';
    same(preg_match('/audit|Zerbinetta|Reykjavik/i', $log), 0);
});
check('hidden.php: source has no storage, database, audit or logging calls', function () use ($hiRoot) {
    $src = (string) file_get_contents($hiRoot . '/public/hidden.php');
    foreach (['AuditLog', 'AuditRecord', 'error_log', 'file_put_contents', 'fopen', 'PDO', 'Connection', 'setcookie', 'session_start', '$_SESSION', 'Geocoder::search', '->search('] as $bad) {
        same([$bad, str_contains($src, $bad)], [$bad, false]);
    }
});
check('consent.php withdraw: clears the cookie, redirects to ./?withdrawn=1; the gate shows the message and forgets the memory', function () use ($shareHttp, $sharePage) {
    [$code, $h] = $shareHttp('POST', '/consent.php', 'action=withdraw&next=x%3D1', ['Content-Type: application/x-www-form-urlencoded', 'Cookie: magic_terms=5']);
    $hs = implode("\n", $h);
    same([$code, str_contains($hs, 'Location: ./?withdrawn=1')], [303, true]);
    same(preg_match('/Set-Cookie: magic_terms=deleted; expires=Thu, 01 Jan 1970/', $hs), 1);
    same(str_contains($hs, 'HttpOnly'), true);
    same(Consent::VALUE, '5');
    same(Consent::given([Consent::COOKIE => '2']), false);
    [$out] = $sharePage(['withdrawn' => '1'], ['QUERY_STRING' => 'withdrawn=1'], false);
    same(str_contains($out, 'You withdrew your acceptance.'), true);
    same(str_contains($out, '<div id="page" inert aria-hidden="true" data-forget-memory>'), true);
    same(str_contains($out, 'name="next" value=""'), true);
    same(str_contains($out, 'Your link could not be kept'), false);
});

// ---- menu, import and Terms text ----
check('self actions and import: no menu, panels hidden until the script runs, import beside the other soul, no inline script or style', function () use ($sharePage) {
    foreach ([[], ['mode' => 'self'], ['mode' => 'love'], ['mode' => 'friends']] as $get) {
        [$out] = $sharePage($get);
        same(str_contains($out, 'class="menu') || str_contains($out, 'data-menu') || str_contains($out, '<summary>Menu'), false);
        same(preg_match('/\sstyle=|<script(?![^>]*\ssrc=)|\son[a-z]+=/i', $out), 0);
    }
    [$out] = $sharePage(['mode' => 'self']);
    same(preg_match('/data-hidden-panel role="group"[^>]* hidden>/', $out), 1);
    same(str_contains($out, 'Anyone who gets this link or QR code can read your name'), false);
    same(str_contains($out, 'This link is not a secret.') && str_contains($out, 'it is not encrypted, and anyone who has the link or the QR code can unpack it'), true);
    same(str_contains($out, 'Opening the link is recorded in the audit like any other request.'), true);
    same(preg_match('/<a class="btn" data-hidden-whatsapp href="#" target="_blank" rel="noopener noreferrer" hidden>Share on WhatsApp<\/a>/', $out), 1);
    same(str_contains($out, 'WhatsApp will open with this link as your message. The link then also passes through WhatsApp, so send it only to someone you trust.'), true);
    same(str_contains($out, 'name="import"'), false);
    [$out] = $sharePage(['mode' => 'love']);
    $imp = strpos($out, 'class="import"');
    $loved = strpos($out, 'data-person="loved"');
    $mem = strpos($out, 'data-memory-bar');
    same($loved !== false && $imp > $loved && $imp < $mem, true);
    same(str_contains($out, 'data-import-scan hidden') && str_contains($out, 'Pasting always works.') && str_contains($out, 'name="import"') && str_contains($out, 'name="nick"'), true);
    same(str_contains($out, 'src="assets/import.js"'), true);
});
check('terms: hidden links hold the sender details, are not secret, and opening one is recorded', function () use ($hiRoot) {
    $t = html_entity_decode((string) file_get_contents($hiRoot . '/templates/partials/terms.php'));
    foreach ([
        'Opening a link that someone shared with you is not recorded again.',
        'your browser sends those details once to this site',
        'only means not shown on that person’s screen',
        'the link is an encoding, not encryption, so anyone who has the link or QR code can decode and read them',
        'If you use the WhatsApp button, the link is sent as a message through that service, which then holds it.',
        'the full details of both people',
        'are recorded in the audit log like any other request',
    ] as $needle) {
        same([$needle, str_contains($t, $needle)], [$needle, true]);
    }
});

// ---- scripts ----
check('scripts: memory.js, share.js, import.js, help.js static rules and syntax', function () use ($hiRoot) {
    $read = fn (string $f): string => (string) file_get_contents($hiRoot . "/public/assets/$f.js");
    $all = ['memory', 'share', 'import', 'help', 'autocomplete', 'print'];
    foreach ($all as $f) {
        $js = $read($f);
        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'XMLHttpRequest', 'sendBeacon', 'WebSocket'] as $bad) {
            same([$f, $bad, str_contains($js, $bad)], [$f, $bad, false]);
        }
        if ($f !== 'memory') {
            same([$f, str_contains($js, 'localStorage') || str_contains($js, 'sessionStorage')], [$f, false]);
        }
        if ($f !== 'share' && $f !== 'autocomplete') {
            same([$f, str_contains($js, 'fetch(')], [$f, false]);
        }
        same([$f, preg_match('/document\.cookie/', $js)], [$f, 0]);
    }
    $share = $read('share');
    preg_match_all('/fetch\(([^,)]*)/', $share, $m);
    same($m[1], ['"hidden.php"']);
    same(str_contains($share, 'method: "POST"') && str_contains($share, 'createElementNS') && str_contains($share, 'magic:share-hidden'), true);
    same(str_contains($share, 'credentials: "same-origin"'), true);
    $mem = $read('memory');
    same(str_contains($mem, 'magic:share-hidden') && str_contains($mem, 'data-self-clear') && str_contains($mem, 'forgetAll();') && str_contains($mem, 'form.submit()'), true);
    same(str_contains($mem, 'data-menu'), false);
    same(str_contains($mem, 'data-hidden-person'), true);
    same(preg_match('/fetch\(|https?:\/\//', $mem), 0);
    $imp = $read('import');
    same(str_contains($imp, 'BarcodeDetector') && str_contains($imp, 'getUserMedia') && str_contains($imp, '.stop()') && str_contains($imp, 'Escape') && str_contains($imp, 'pagehide'), true);
    $node = trim((string) shell_exec('command -v node 2>/dev/null'));
    if ($node !== '') {
        foreach ($all as $f) {
            exec(escapeshellarg($node) . ' --check ' . escapeshellarg("$hiRoot/public/assets/$f.js") . ' 2>&1', $o, $rc);
            same($rc, 0);
        }
    }
});
check('help: phase B keys exist and read as plain advice', function () {
    same(isset(Help::TEXT['field.nick']), true);
    foreach (['field.import', 'self.hidden_code', 'self.clear', 'love.import', 'love.match_hidden'] as $k) {
        same(isset(Help::TEXT[$k]), false);
    }
    same(isset(Help::TEXT['menu.clean']) || isset(Help::TEXT['menu.share_hidden']), false);
});

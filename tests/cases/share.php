<?php
declare(strict_types=1);

use Magic\Geo\Geocoder;
use Magic\Http;
use Magic\LoveReading;
use Magic\Request;
use Magic\SelfReading;
use Magic\Share\Qr;
use Magic\Share\ShareLink;
use Magic\Tarot\Reading;

$shareRoot = dirname(__DIR__, 2);
$shareGeo = new Geocoder(sys_get_temp_dir());

// ---- noaudit / request ----
check('request: noaudit switch (any value, plain string only)', function () {
    same(Request::noAudit(['noaudit' => '']), true);
    same(Request::noAudit(['noaudit' => '1']), true);
    same(Request::noAudit([]), false);
    parse_str('noaudit[]=1&mode=self', $arr);
    same(Request::noAudit(array_filter($arr, 'is_string')), false);
});
check('request: coordinates are rounded to 5 decimals', function () use ($shareGeo) {
    $r = Request::parse(['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9028123456', 'lon' => '12.4963987654', 'tz' => 'Europe/Rome'], $shareGeo);
    same([$r['input']['lat'], $r['input']['lon']], [41.90281, 12.4964]);
});
check('request: tarot parameter is validated as a whole', function () {
    same(Request::parseTarot([]), ['slots' => null, 'invalid' => false]);
    same(Request::parseTarot(['t' => '16u,5r,9u'])['invalid'], false);
    same(Request::parseTarot(['t' => '16u,16r,9u']), ['slots' => null, 'invalid' => true]);
    same(Request::parseTarot(['t' => ['x']]), ['slots' => null, 'invalid' => false]);
});

// ---- ShareLink ----
$einstein = ['year' => 1879, 'month' => 3, 'day' => 14, 'hour' => 11, 'minute' => 30, 'lat' => 48.4, 'lon' => 10.0, 'tz' => 'Europe/Berlin', 'city' => 'Ulm', 'now' => null];
check('share link: frozen Self and live strings (canonical order)', function () use ($einstein) {
    same(ShareLink::self($einstein, '2026-10-09'), 'mode=self&date=1879-03-14&time=11:30&city=Ulm&lat=48.40000&lon=10.00000&tz=Europe/Berlin&on=2026-10-09&noaudit');
    same(ShareLink::liveSelf($einstein), 'mode=self&date=1879-03-14&time=11:30&city=Ulm&lat=48.40000&lon=10.00000&tz=Europe/Berlin&noaudit');
});
$loveA = ['name' => 'A&B=C #1 %', 'date' => ['year' => 1990, 'month' => 7, 'day' => 15], 'time' => ['hour' => 8, 'minute' => 30],
    'place' => ['lat' => 41.9, 'lon' => 12.5, 'tz' => 'Europe/Rome', 'city' => 'São Paulo, Brazil'], 'now' => null];
$loveB = ['name' => 'Zoë "Z" 🙂', 'date' => ['year' => 1991, 'month' => 3, 'day' => 2], 'time' => null, 'place' => null, 'now' => null];
check('share link: frozen Love string, encoding and fields that exist', function () use ($loveA, $loveB) {
    $spread = Reading::fromSlots([['number' => 16, 'reversed' => false], ['number' => 5, 'reversed' => true], ['number' => 9, 'reversed' => false]]);
    same(ShareLink::love($loveA, $loveB, $spread, '2026-10-09'),
        'mode=love&a_name=A%26B%3DC%20%231%20%25&a_date=1990-07-15&a_time=08:30&a_city=S%C3%A3o%20Paulo,%20Brazil&a_lat=41.90000&a_lon=12.50000&a_tz=Europe/Rome'
        . '&b_name=Zo%C3%AB%20%22Z%22%20%F0%9F%99%82&b_date=1991-03-02&on=2026-10-09&t=16u,5r,9u&noaudit');
    $live = ShareLink::liveLove($loveA, $loveB);
    same(str_contains($live, '&on=') || str_contains($live, '&t='), false);
    same(str_ends_with($live, '&noaudit'), true);
});
check('share link: city label trimmed so its encoded form fits', function () {
    $c = ShareLink::trimCity('Ulm, Baden-Württemberg, Deutschland, Europa, Planet');
    same(strlen(rawurlencode($c)) <= ShareLink::MAX_CITY_BYTES, true);
    same(str_starts_with($c, 'Ulm, Baden'), true);
    same(ShareLink::trimCity(str_repeat('é', 100)) === str_repeat('é', 8), true);
});
check('share link: Self and Love round trip through the request parser', function () use ($shareGeo, $loveA, $loveB, $einstein) {
    $today = '2026-10-09';
    $q = ShareLink::self($einstein, $today);
    parse_str($q, $parsed);
    $r = Request::parse($parsed, $shareGeo);
    same($r['input'], $einstein);
    same(SelfReading::build($r['input'], $today), SelfReading::build($einstein, $today));
    same(Request::parseToday($parsed, '2000-01-01'), $today);

    $a = $loveA;
    $b = $loveB;
    $view = LoveReading::build($a, $b, $today);
    $q = ShareLink::love($a, $b, $view['tarot'], $today);
    parse_str($q, $parsed);
    same(array_keys($parsed) === array_unique(array_keys($parsed)), true);
    $r = Request::parseLove($parsed, $shareGeo);
    same($r['errors'], []);
    same($r['a'], $a);
    same($r['b'], $b);
    $t = Request::parseTarot($parsed);
    $view2 = LoveReading::build($r['a'], $r['b'], Request::parseToday($parsed, '2000-01-01'), $t['slots']);
    $view['tarotShared'] = true;
    same($view2, $view);
});
check('share link: shared tarot is used instead of the day draw', function () use ($loveA, $loveB) {
    $v = LoveReading::build($loveA, $loveB, '2026-10-09', [['number' => 21, 'reversed' => true], ['number' => 0, 'reversed' => false], ['number' => 7, 'reversed' => true]]);
    same(array_map(fn (array $t): string => $t['card']['id'] . ($t['reversed'] ? '*' : ''), $v['tarot']), ['world*', 'fool', 'chariot*']);
    same($v['tarotShared'], true);
    same(LoveReading::build($loveA, $loveB, '2026-10-09')['tarotShared'], false);
});

// ---- Http ----
check('http: secure detection, base path and origin from a server array', function () {
    same(Http::isSecure(['HTTPS' => 'on']), true);
    same(Http::isSecure(['HTTPS' => 'off']), false);
    same(Http::isSecure(['HTTP_X_FORWARDED_PROTO' => 'https']), true);
    same(Http::isSecure(['HTTP_X_FORWARDED_PROTO' => 'http']), false);
    same(Http::isSecure([]), false);
    same(Http::basePath(['REQUEST_URI' => '/?mode=self']), '');
    same(Http::basePath(['REQUEST_URI' => '/index.php/x?a=1']), '');
    same(Http::basePath(['REQUEST_URI' => '/magic/?a=1']), '/magic');
    same(Http::basePath(['REQUEST_URI' => '/a b/']), null);
    same(Http::origin(['HTTP_HOST' => 'localhost:8081']), 'http://localhost:8081');
    same(Http::origin(['HTTP_HOST' => 'example.test', 'HTTPS' => 'on']), 'https://example.test');
    same(Http::origin(['HTTP_HOST' => 'evil.test/<x>']), null);
    same(Http::origin([]), null);
});

// ---- QR ----
check('qr: capacity table and version selection by length', function () {
    $caps = [1 => 17, 2 => 32, 3 => 53, 4 => 78, 5 => 106, 6 => 134, 7 => 154, 8 => 192, 9 => 230, 10 => 271, 11 => 321, 12 => 367, 13 => 425, 14 => 458, 15 => 520];
    foreach ($caps as $v => $c) {
        same(Qr::capacity($v), $c);
        same(count(Qr::encode(str_repeat('a', $c))), 17 + 4 * $v);
        if ($v > 1) {
            same(count(Qr::encode(str_repeat('a', $caps[$v - 1] + 1))), 17 + 4 * $v);
        }
    }
    same(count(Qr::encode('')), 21);
    same(count(Qr::encode(str_repeat('a', 520))), 77);
    same(Qr::encode(str_repeat('a', 521)), null);
});
check('qr: reference values (format/version information, Reed-Solomon worked examples)', function () {
    same(sprintf('%015b', Qr::formatBits(0)), '111011111000100');
    same(sprintf('%018b', Qr::versionBits(7)), '000111110010010100');
    // Published worked examples: "HELLO WORLD" 1-M data codewords and the ISO 18004 annex example "01234567".
    same(Qr::rsRemainder([32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17], 10), [196, 35, 39, 119, 235, 215, 231, 226, 93, 23]);
    same(Qr::rsRemainder([16, 32, 12, 86, 97, 128, 236, 17, 236, 17, 236, 17, 236, 17, 236, 17], 10), [165, 36, 212, 193, 237, 54, 199, 135, 44, 85]);
});
$qrRead = function (array $m): array {
    $r = Qr::readCodewords($m);
    if ($r === null) {
        throw new RuntimeException('format information unreadable');
    }
    return $r;
};
check('qr: structure of every version (finders, timing, dark module, format copies, version blocks)', function () {
    foreach (range(1, 15) as $v) {
        $m = Qr::encode("https://example.org/?v=$v");
        $n = 17 + 4 * $v;
        // The data above may select a smaller version; force the size by length.
        $m = Qr::encode(str_repeat('x', Qr::capacity($v)));
        same(count($m), $n);
        foreach ($m as $row) {
            same(count($row), $n);
        }
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$oy, $ox]) {
            for ($y = 0; $y < 7; $y++) {
                for ($x = 0; $x < 7; $x++) {
                    $ring = max(abs($x - 3), abs($y - 3));
                    same($m[$oy + $y][$ox + $x], $ring !== 2);
                }
            }
        }
        for ($i = 8; $i < $n - 8; $i++) {
            same($m[6][$i], $i % 2 === 0);
            same($m[$i][6], $i % 2 === 0);
        }
        same($m[4 * $v + 9][8], true);
        $mask = Qr::readCodewords($m)['mask'];
        $bits = Qr::formatBits($mask);
        for ($i = 0; $i < 8; $i++) {
            same($m[8][$n - 1 - $i], (($bits >> $i) & 1) === 1);
        }
        if ($v >= 7) {
            $vb = Qr::versionBits($v);
            for ($i = 0; $i < 18; $i++) {
                $bit = (($vb >> $i) & 1) === 1;
                same($m[intdiv($i, 3)][$n - 11 + $i % 3], $bit);
                same($m[$n - 11 + $i % 3][intdiv($i, 3)], $bit);
            }
        }
        if ($v >= 2) { // the bottom-right alignment pattern
            $c = [2 => 18, 3 => 22, 4 => 26, 5 => 30, 6 => 34, 7 => 38, 8 => 42, 9 => 46, 10 => 50, 11 => 54, 12 => 58, 13 => 62, 14 => 66, 15 => 70][$v];
            same([$m[$c][$c], $m[$c - 1][$c - 1], $m[$c - 2][$c - 2]], [true, false, true]);
        }
    }
});
check('qr: error correction self check (codewords re-read from the matrix, every block has zero syndromes)', function () use ($qrRead) {
    $exp = [];
    $log = [];
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x > 255) {
            $x ^= 0x11D;
        }
    }
    $mul = fn (int $a, int $b): int => ($a === 0 || $b === 0) ? 0 : $exp[($log[$a] + $log[$b]) % 255];
    foreach (range(1, 15) as $v) {
        $payload = '';
        for ($i = 0; $i < Qr::capacity($v); $i++) {
            $payload .= chr(($i * 37 + $v * 11) % 256);
        }
        $r = $qrRead(Qr::encode($payload));
        same($r['version'], $v);
        $cw = $r['codewords'];
        $layout = Qr::blockLayout($v);
        $dataTotal = array_sum(array_column($layout, 0));
        $ecLen = $layout[0][1];
        same(count($cw) >= $dataTotal + $ecLen * count($layout), true);
        // De-interleave.
        $blocks = array_fill(0, count($layout), []);
        $pos = 0;
        $maxData = max(array_column($layout, 0));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($layout as $b => [$dl]) {
                if ($i < $dl) {
                    $blocks[$b][] = $cw[$pos++];
                }
            }
        }
        $ecs = array_fill(0, count($layout), []);
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($layout as $b => $_) {
                $ecs[$b][] = $cw[$pos++];
            }
        }
        $data = [];
        foreach ($blocks as $b => $d) {
            $word = array_merge($d, $ecs[$b]);
            for ($k = 0; $k < $ecLen; $k++) {
                $s = 0;
                foreach ($word as $c) {
                    $s = $mul($s, $exp[$k]) ^ $c;
                }
                same($s, 0);
            }
            $data = array_merge($data, $d);
        }
        // Decode the payload: mode 0100, character count, bytes.
        $bits = '';
        foreach ($data as $c) {
            $bits .= sprintf('%08b', $c);
        }
        same(substr($bits, 0, 4), '0100');
        $cl = $v <= 9 ? 8 : 16;
        same(bindec(substr($bits, 4, $cl)), strlen($payload));
        $out = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $out .= chr((int) bindec(substr($bits, 4 + $cl + 8 * $i, 8)));
        }
        same($out, $payload);
    }
});
check('qr: deterministic, path data is plain, golden frozen', function () {
    $url = 'https://example.org/?mode=self&noaudit';
    $a = Qr::encode($url);
    same($a, Qr::encode($url));
    $p = Qr::path($a);
    same($p, Qr::path(Qr::encode($url)));
    same(preg_match('/^[MhvHz0-9 ]+$/', $p), 1);
    // Frozen after the matrix was compared module by module with an independent encoder
    // (python-qrcode, level L, byte mode, same mask) for versions 1-15.
    same(md5($p), '8e4e2ca91014727e180baad7bbd3c8a4');
});
check('qr: partial renders one path, viewBox with the quiet zone, no style or script', function () use ($shareRoot) {
    $code = 'require ' . var_export($shareRoot . '/src/bootstrap.php', true) . '; require ' . var_export($shareRoot . '/templates/partials/qr.php', true)
        . '; qr_svg(Magic\\Share\\Qr::encode("https://example.org/?q=\"<x>"), "QR <label> \"x\"");';
    $out = (string) shell_exec('php -r ' . escapeshellarg($code) . ' 2>&1');
    same(substr_count($out, '<path'), 1);
    same(preg_match('/viewBox="0 0 (\d+) (\d+)"/', $out, $m), 1);
    same([(int) $m[1], (int) $m[1] === (int) $m[2]], [(int) $m[1], true]);
    same(in_array((int) $m[1] - 8, [21, 25, 29, 33, 37, 41], true), true);
    same(preg_match('/style=|<script|onload/i', $out), 0);
    same(str_contains($out, 'QR &lt;label&gt; &quot;x&quot;'), true);
});

// ---- Pages in a subprocess (no database; audit target is a closed port) ----
$shareTmp = sys_get_temp_dir() . '/magic-share-' . bin2hex(random_bytes(4));
mkdir($shareTmp);
register_shutdown_function(function () use ($shareTmp): void {
    foreach (glob("$shareTmp/*") ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($shareTmp);
});
$shareClosed = function () use ($shareTmp): string {
    $s = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    file_put_contents("$shareTmp/closed.php", "<?php\nreturn ['host' => '127.0.0.1', 'port' => $port, 'name' => 'x', 'user' => 'x', 'password' => 'x'];\n");
    return "$shareTmp/closed.php";
};
$sharePage = function (array $get, array $server = [], bool $consent = true, ?string $log = null) use ($shareRoot, $shareClosed, $shareTmp): array {
    $log ??= "$shareTmp/sp-" . bin2hex(random_bytes(3)) . '.log';
    $code = '$_GET = json_decode($argv[1], true); $_COOKIE = ' . ($consent ? '["magic_terms" => "3"]' : '[]')
        . '; $_SERVER = array_merge($_SERVER, ["REQUEST_METHOD" => "GET"], json_decode($argv[3], true)); ob_start(); require $argv[2];'
        . ' $o = ob_get_clean(); echo json_encode(["out" => $o, "headers" => headers_list()]);';
    $cmd = 'MAGIC_CONFIG=' . escapeshellarg($shareClosed()) . ' php -d display_errors=0 -d log_errors=1 -d error_log=' . escapeshellarg($log)
        . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg((string) json_encode($get)) . ' ' . escapeshellarg($shareRoot . '/public/index.php')
        . ' ' . escapeshellarg((string) json_encode((object) $server)) . ' 2>&1';
    $raw = (string) shell_exec($cmd);
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        throw new RuntimeException('page failed: ' . substr($raw, 0, 300));
    }
    return [$j['out'], $j['headers'], is_file($log) ? (string) file_get_contents($log) : ''];
};
$selfGet = ['mode' => 'self', 'date' => '1879-03-14', 'time' => '11:30', 'city' => 'Ulm', 'lat' => '48.4', 'lon' => '10', 'tz' => 'Europe/Berlin', 'on' => '2026-10-09'];
$loveGet = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
    'b_name' => 'Silvia', 'b_date' => '1991-03-02', 'on' => '2026-10-09'];
check('noaudit: result is rendered, no audit attempt and nothing logged; control without it logs the failed write', function () use ($sharePage, $selfGet, $loveGet) {
    foreach ([$selfGet, $loveGet] as $get) {
        [$out, , $log] = $sharePage($get + ['noaudit' => '']);
        same(str_contains($out, 'id="results"') && str_contains($out, 'Share this reading'), true);
        same($log, '');
        [, , $log] = $sharePage($get);
        same(str_contains($log, 'audit: write failed'), true);
        [, , $log] = $sharePage($get + ['noaudit' => '1']);
        same($log, '');
    }
    [$out] = $sharePage($loveGet);
    same(str_contains($out, 'name="noaudit"'), false);
    [$out] = $sharePage($loveGet + ['noaudit' => '']);
    same(str_contains($out, '<input type="hidden" name="noaudit" value="">'), true);
});

// Real HTTP against PHP's built-in server for what only shows in response headers.
$shareHttp = (function () use ($shareRoot, $shareClosed, $shareTmp): callable {
    $proc = null;
    $port = 0;
    return function (string $method, string $path, string $body = '', array $headers = []) use (&$proc, &$port, $shareRoot, $shareClosed, $shareTmp): array {
        if ($proc === null) {
            $s = stream_socket_server('tcp://127.0.0.1:0');
            $port = (int) substr(strrchr((string) stream_socket_get_name($s, false), ':'), 1);
            fclose($s);
            $env = ['MAGIC_CONFIG' => $shareClosed(), 'PATH' => (string) getenv('PATH')];
            $proc = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'error_log=' . $shareTmp . '/srv.log', '-S', "127.0.0.1:$port", '-t', $shareRoot . '/public'],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $shareRoot, $env);
            register_shutdown_function(function () use (&$proc): void {
                if (is_resource($proc)) {
                    proc_terminate($proc);
                }
            });
            for ($i = 0; $i < 50; $i++) {
                $c = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
                if ($c) {
                    fclose($c);
                    break;
                }
                usleep(100000);
            }
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
        $out = @file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
        if ($out === false || !isset($http_response_header)) {
            throw new RuntimeException('no response from the built-in server');
        }
        preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0], $m);
        return [(int) $m[1], $http_response_header, $out];
    };
})();
check('pages send no-store and Vary: Cookie on the chooser, the gate and results', function () use ($shareHttp, $selfGet) {
    $qs = http_build_query($selfGet);
    foreach ([['/', []], ['/', ['Cookie: magic_terms=3']], ["/?$qs", []], ["/?$qs", ['Cookie: magic_terms=3']]] as [$path, $hdr]) {
        [$code, $h, $body] = $shareHttp('GET', $path, '', $hdr);
        $hs = strtolower(implode("\n", $h));
        same($code, 200);
        same(str_contains($hs, 'cache-control: private, no-store'), true);
        same(preg_match('/^vary:.*cookie/m', $hs), 1);
    }
});
check('gate: inert container with footer and terms, autofocus, original query kept (t, on, noaudit), absolute action', function () use ($sharePage, $loveGet) {
    $qs = http_build_query($loveGet + ['t' => '16u,5r,9u', 'noaudit' => ''], '', '&', PHP_QUERY_RFC3986);
    [$out] = $sharePage($loveGet + ['t' => '16u,5r,9u', 'noaudit' => ''], ['QUERY_STRING' => $qs, 'REQUEST_URI' => '/?' . $qs], false);
    same(str_contains($out, 'class="gate"'), true);
    same(str_contains($out, 'autofocus'), true);
    same(str_contains($out, 'aria-describedby="gate-terms"'), true);
    same(str_contains($out, 'action="/consent.php"'), true);
    same(str_contains($out, 'name="next" value="' . htmlspecialchars($qs, ENT_QUOTES) . '"'), true);
    same(str_contains($out, 'share-link'), false);
    same(str_contains($out, 'Share this reading'), false);
    same(preg_match('/<div id="page" inert aria-hidden="true">.*<footer.*id="terms".*<\/div>\s*<script/s', $out), 1);
    [$out] = $sharePage($loveGet, ['REQUEST_URI' => '/?x'], true);
    same(str_contains($out, 'inert'), false);
    same(str_contains($out, 'class="gate"'), false);
    same(str_contains($out, 'Withdraw my acceptance and clear the cookie'), true);
    [$out] = $sharePage([], ['QUERY_STRING' => 'x=' . str_repeat('a', 2500)], false);
    same(str_contains($out, 'Your link could not be kept, please open it again after accepting.'), true);
    [$out] = $sharePage(['withdrawn' => '1'], ['QUERY_STRING' => 'withdrawn=1'], false);
    same(str_contains($out, 'You withdrew your acceptance.') && str_contains($out, 'name="next" value=""'), true);
});

check('consent.php over HTTP: accept keeps the query, 303, cookie flags, Secure behind a proxy, long query dropped, withdraw, GET', function () use ($shareHttp, $loveGet) {
    $qs = http_build_query($loveGet + ['t' => '16u,5r,9u', 'noaudit' => ''], '', '&', PHP_QUERY_RFC3986);
    $post = fn (array $f, array $extra = []) => $shareHttp('POST', '/consent.php', http_build_query($f), array_merge(['Content-Type: application/x-www-form-urlencoded'], $extra));
    [$code, $h] = $post(['action' => 'accept', 'next' => $qs]);
    $hs = implode("\n", $h);
    same($code, 303);
    same(str_contains($hs, 'Location: ./?' . $qs . '#results'), true);
    same(preg_match('/Set-Cookie: magic_terms=3;.*HttpOnly.*SameSite=Lax/i', $hs), 1);
    same(stripos($hs, '; secure') === false, true);
    same(str_contains($hs, 'Cache-Control: private, no-store') && preg_match('/^Vary:.*Cookie/mi', $hs) === 1, true);
    [, $h] = $post(['action' => 'accept', 'next' => $qs], ['X-Forwarded-Proto: https']);
    same(stripos(implode("\n", $h), '; secure') !== false, true);
    [, $h] = $post(['action' => 'accept', 'next' => str_repeat('a=1&', 700)]);
    same(str_contains(implode("\n", $h), 'Location: ./#results'), true);
    [$code, $h] = $post(['action' => 'withdraw', 'next' => $qs]);
    $hs = implode("\n", $h);
    same([$code, str_contains($hs, 'Location: ./?withdrawn=1'), preg_match('/Set-Cookie: magic_terms=deleted; expires=Thu, 01 Jan 1970/', $hs)], [303, true, 1]);
    [$code, $h] = $shareHttp('GET', '/consent.php');
    $hs = implode("\n", $h);
    same([$code, str_contains($hs, 'Location: ./'), str_contains($hs, 'Set-Cookie')], [303, true, false]);
    // Following the redirect with the cookie shows the result and no gate.
    [, , $body] = $shareHttp('GET', '/?' . $qs, '', ['Cookie: magic_terms=3']);
    same(str_contains($body, 'class="gate"'), false);
    same(str_contains($body, 'Share this reading'), true);
    [, , $body] = $shareHttp('GET', '/?' . $qs);
    same(str_contains($body, 'class="gate"'), true);
    same(str_contains($body, 'Share this reading'), false);
});
check('csp: frame-ancestors, form-action and base-uri are set', function () use ($shareRoot) {
    $h = (string) file_get_contents($shareRoot . '/public/.htaccess');
    foreach (["frame-ancestors 'none'", "form-action 'self'", "base-uri 'none'"] as $d) {
        same(str_contains($h, $d), true);
    }
});

// ---- Result pages ----
check('self page: share section with link, QR and live link; fixed-day note', function () use ($sharePage, $selfGet) {
    [$out] = $sharePage($selfGet, ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/?x']);
    same(preg_match('/id="share-link" class="share__input" readonly value="http:\/\/localhost:8081\/\?c=[A-Za-z0-9_-]+"/', $out), 1);
    preg_match('/id="share-link"[^>]*value="[^"]*\?c=([^"]*)"/', $out, $cm);
    $dec = \Magic\Share\ShareCode::decode($cm[1]);
    same($dec['date'] . '|' . $dec['on'] . '|' . isset($dec['noaudit']), '1879-03-14|2026-10-09|1');
    same(substr_count($out, '<svg class="qr"'), 1);
    [$fixed] = $sharePage(array_merge($selfGet, ['on' => '2001-02-03']), ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    same(str_contains($fixed, 'This reading is fixed to 2001-02-03.') && str_contains($fixed, 'Open the live version'), true);
    same(preg_match('/Link to a live reading[^<]*<a href="http:\/\/localhost:8081\/\?c=[A-Za-z0-9_-]+"/', $out) === 1, true);
    same(str_contains($out, 'src="assets/share.js"') && str_contains($out, 'data-copy="share-link" hidden'), true);
    [$out] = $sharePage(array_diff_key($selfGet, ['on' => 1]), ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    same(str_contains($out, 'This reading is fixed to'), false);
    [$out] = $sharePage($selfGet, []); // no usable origin: relative link, no QR
    same(str_contains($out, '<svg class="qr"'), false);
    same(str_contains($out, 'value="./?c='), true);
});
check('love page: three sync cards, curves only in details, spread order, no tarot dates', function () use ($sharePage, $loveGet) {
    [$out] = $sharePage($loveGet, ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/']);
    same(substr_count($out, '<article class="sync-card">'), 3);
    same(substr_count($out, '<div class="sync-grid">'), 2); // summary row and the one inside the details
    same(substr_count($out, 'Next shared high'), 3);
    same(substr_count($out, 'Next shared low'), 3);
    $pos = strpos($out, '<details class="sync-more">');
    $end = strpos($out, '</details>', $pos);
    same($pos !== false && $end !== false, true);
    same(substr_count($out, 'class="bio-chart"'), 3);
    same(substr_count(substr($out, $pos, $end - $pos), 'class="bio-chart"'), 3);
    $p = strpos($out, '>Past<');
    $n = strpos($out, '>Present<');
    $f = strpos($out, '>Future<');
    same($p !== false && $p < $n && $n < $f, true);
    same(substr_count($out, 'class="tarot__card '), 3);
    same(str_contains($out, 'three-day'), false);
    // The share link carries the cards; opening it gives the same ones.
    preg_match('/id="share-link"[^>]*value="([^"]*)"/', $out, $m);
    $link = html_entity_decode($m[1]);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $cq);
    $q = \Magic\Share\ShareCode::decode($cq['c']);
    same(isset($q['t'], $q['on'], $q['noaudit']), true);
    preg_match_all('/tarot__name">([^<]+)</', $out, $names);
    preg_match_all('/(\d+)[ur]/', $q['t'], $codes);
    same($names[1], array_map(fn (string $n): string => \Magic\Content\TarotDeck::card((int) $n)['name'], $codes[1]));
});
check('love page: a valid t shows exactly those cards; an invalid t is ignored with a note', function () use ($sharePage, $loveGet) {
    [$out] = $sharePage($loveGet + ['t' => '16u,5r,9u']);
    preg_match_all('/tarot__num">(\d+)</', $out, $nums);
    same($nums[1], ['16', '5', '9']);
    same(substr_count($out, 'tarot__art tarot__art--reversed'), 1);
    same(str_contains($out, 'These cards come from the link you opened.'), true);
    same(str_contains($out, 'were not valid'), false);
    foreach (['16u,16r,9u', '99u,1u,2u', 'junk'] as $bad) {
        [$out, $h] = $sharePage($loveGet + ['t' => $bad]);
        same(str_contains($out, 'The cards in this link were not valid, so a fresh reading is shown.'), true);
        same(substr_count($out, 'class="tarot__card '), 3);
    }
    [$out] = $sharePage($selfGet_ = ['mode' => 'self', 'date' => '1879-03-14', 'time' => '11:30', 'city' => 'Ulm', 'lat' => '48.4', 'lon' => '10', 'tz' => 'Europe/Berlin', 't' => 'junk']);
    same(str_contains($out, 'were not valid'), false);
});
check('love page: bad parameters never crash (arrays, bad on, out-of-range coordinates)', function () use ($sharePage, $loveGet) {
    foreach ([['on' => 'garbage'], ['on' => ['x']], ['t' => ['1u']], ['a_lat' => '999', 'a_lon' => '999'], ['b_name' => str_repeat('x', 300)], ['noaudit' => ['1']]] as $extra) {
        [$out, , ] = $sharePage(array_merge($loveGet, $extra));
        same(str_contains($out, 'id="results"'), true);
    }
});
check('love page: in common shows chips, pills, meaning sentences and placeholders', function () use ($sharePage, $loveGet) {
    [$out] = $sharePage($loveGet);
    same(substr_count($out, 'class="pill pill--level"'), 2);
    same(str_contains($out, 'Ascendant: add both birth times and cities to compare it.'), true);
    same(substr_count($out, '<p class="chip '), 4);
    same(str_contains($out, 'Compared: Sun, Moon.'), true);
    same(str_contains($out, 'How your two names harmonise.'), true);
    [$out] = $sharePage(array_diff_key($loveGet, ['b_date' => 1]));
    same(str_contains($out, "Add Silvia's birth date to see what you share.") || str_contains($out, 'Add Silvia&#039;s birth date to see what you share.'), true);
    same(str_contains($out, 'id="common-h"'), true);
    same(str_contains($out, 'Biorhythm synchrony'), false);
});
check('in common copy: every body and level has a meaning, a label and a band', function () {
    foreach (['sun', 'moon', 'ascendant'] as $b) {
        foreach (array_keys(Magic\Content\Traits::LEVELS) as $lv) {
            same(trim(Magic\Content\Traits::COMMON_MEANING[$b][$lv] ?? '') !== '', true);
        }
    }
    foreach (array_keys(Magic\Content\Traits::LEVELS) as $lv) {
        same(trim(Magic\Content\Traits::LEVEL_LABELS[$lv] ?? '') !== '', true);
    }
    foreach ([[100, 'strong'], [80, 'strong'], [79, 'good'], [60, 'good'], [59, 'mixed'], [40, 'mixed'], [39, 'contrasting'], [0, 'contrasting']] as [$score, $band]) {
        same(Magic\Content\Traits::commonBand($score), $band);
        same(isset(Magic\Content\Traits::COMMON_VERDICTS[$band]), true);
    }
});
check('audit record: tarot is a list of position/card/reversed, format version 3', function () use ($loveA, $loveB) {
    $v = LoveReading::build($loveA, $loveB, '2026-10-09');
    $r = Magic\Audit\AuditRecord::fromLove($loveA, $loveB, $v, '2026-10-09');
    same($r['format_version'], 3);
    same(str_contains($r['response_yaml'], "tarot:\n  - position: past\n    card: "), true);
    same(preg_match('/^\s+(- )?date:/m', $r['response_yaml']), 0);
});
check('terms say shared links are not recorded again; no server names or algorithm talk in the new UI texts', function () use ($shareRoot) {
    $t = (string) file_get_contents($shareRoot . '/templates/partials/terms.php');
    same(str_contains($t, 'Opening a link that someone shared with you is not recorded again.'), true);
    same(str_contains($t, 'every result you request is recorded'), true);
    foreach (['templates/partials/share.php', 'templates/love-result.php', 'public/assets/share.js'] as $f) {
        same(preg_match('/algorithm|sha256|hash/i', (string) file_get_contents($shareRoot . '/' . $f)), 0);
    }
});

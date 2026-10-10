<?php
declare(strict_types=1);

// ADR 0006 phase A: Self name, help popovers, required marks, QR blocks, Terms and wheel CSS.
use Magic\Audit\AuditRecord;
use Magic\Content\Help;
use Magic\Geo\Geocoder;
use Magic\Request;
use Magic\Share\ShareCode;
use Magic\Share\ShareLink;

$uiRoot = dirname(__DIR__, 2);
$uiGeo = new Geocoder(sys_get_temp_dir());
$uiCss = (string) file_get_contents($uiRoot . '/public/assets/styles.css');
$uiSelf = ['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'];
$uiIn = ['year' => 1990, 'month' => 7, 'day' => 15, 'hour' => 8, 'minute' => 30, 'lat' => 41.9, 'lon' => 12.5, 'tz' => 'Europe/Rome', 'city' => 'Rome', 'now' => null];

// ---- Self name ----
check('self name: absent, empty, valid, 40 and 41 characters, control characters, no letter', function () use ($uiGeo, $uiSelf) {
    same(array_key_exists('name', Request::parse($uiSelf, $uiGeo)['input']), false);
    same(array_key_exists('name', Request::parse($uiSelf + ['name' => '  '], $uiGeo)['input']), false);
    same(Request::parse($uiSelf + ['name' => ' Ann '], $uiGeo)['input']['name'], 'Ann');
    same(Request::parse($uiSelf + ['name' => str_repeat('é', 40)], $uiGeo)['errors'], []);
    foreach ([str_repeat('a', 41), "An\x07n", '1234'] as $bad) {
        $r = Request::parse($uiSelf + ['name' => $bad], $uiGeo);
        same($r['input'], null);
        same(count($r['errors']), 1);
    }
});
check('self name: the audit person carries it, an unnamed Self has an empty name', function () use ($uiIn) {
    $view = Magic\SelfReading::build($uiIn + ['name' => 'Ann'], '2026-10-09');
    same($view['name'], 'Ann');
    same(AuditRecord::fromSelf($uiIn + ['name' => 'Ann'], $view, '2026-10-09')['persons'][0]['name'], 'Ann');
    same(AuditRecord::fromSelf($uiIn, Magic\SelfReading::build($uiIn, '2026-10-09'), '2026-10-09')['persons'][0]['name'], '');
});
check('self name: links carry it only when given; code version 2 round trip, goldens, version 1 unchanged', function () use ($uiIn) {
    same(str_contains(ShareLink::self($uiIn, '2026-10-09'), 'name='), false);
    same(str_starts_with(ShareLink::self($uiIn + ['name' => 'Ann'], '2026-10-09'), 'mode=self&name=Ann&date='), true);
    same(str_starts_with(ShareLink::liveSelf($uiIn + ['name' => 'Ann']), 'mode=self&name=Ann&date='), true);
    same(ShareCode::encodeSelf($uiIn, '2026-10-09', true), 'FlpvWFOT_MlDcElu1BXRFJvbWUA');
    $v2 = 'JlpvAaC3N1hTk_zJQ3BJbtQV0RSb21lA';
    same(ShareCode::encodeSelf($uiIn + ['name' => 'Ann'], '2026-10-09', true), $v2);
    same(ShareCode::decode($v2), ['mode' => 'self', 'name' => 'Ann', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.90000', 'lon' => '12.50000', 'tz' => 'Europe/Rome', 'on' => '2026-10-09', 'noaudit' => '']);
    same(ShareCode::encodeSelf($uiIn + ['name' => 'Zoë Ålvarez'], '2026-10-09', false), 'JA1ab8OrIMOFbHZhcmV6sKcn-ZKG4JLdqCuiKTe2soA');
    foreach (['Ann', 'Zoë Ålvarez', str_repeat("\u{1D49C}", 40)] as $name) {
        $code = ShareCode::encodeSelf($uiIn + ['name' => $name], '2026-10-09', true);
        $dec = ShareCode::decode($code);
        same($dec['name'], $name);
        same(ShareCode::encodeSelf($uiIn + ['name' => $dec['name']], '2026-10-09', true), $code);
    }
    // Truncations of a version 2 code never decode and never throw.
    for ($i = 1; $i < strlen($v2); $i++) {
        same(ShareCode::decode(substr($v2, 0, $i)), null);
    }
    same(ShareCode::decode($v2 . 'A'), null);
});
check('self name: the page shows it in the heading and keeps it in the form; Love codes cannot be version 2', function () use ($sharePage, $uiSelf) {
    [$out] = $sharePage($uiSelf + ['mode' => 'self', 'name' => 'Ann', 'noaudit' => '', 'on' => '2026-10-09']);
    same(str_contains($out, 'Self discovery for Ann'), true);
    same(str_contains($out, 'name="name" type="text" maxlength="40" value="Ann"'), true);
    same(str_contains($out, 'name="name" type="text" required'), false);
    $love = ShareCode::encodeLove(
        ['name' => 'Ann', 'date' => ['year' => 1990, 'month' => 7, 'day' => 15], 'time' => ['hour' => 8, 'minute' => 30], 'place' => ['lat' => 41.9, 'lon' => 12.5, 'tz' => 'Europe/Rome', 'city' => 'Rome'], 'now' => null],
        ['name' => 'Bo', 'date' => null, 'time' => null, 'place' => null, 'now' => null], null, '2026-10-09', false
    );
    $bytes = base64_decode(strtr($love, '-_', '+/') . '==');
    $bytes[0] = chr((ord($bytes[0]) & 0x0F) | 0x20);
    same(ShareCode::decode(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=')), null);
});

// ---- Required marks, hint, import of nothing else ----
check('forms: required marks, one legend per form, optional Self name, Love hint removed', function () use ($sharePage) {
    [$out] = $sharePage(['mode' => 'self']);
    same(substr_count($out, '* required'), 1);
    same(substr_count($out, '<span class="req" aria-hidden="true">*</span>'), 3); // date, time, city
    same(str_contains($out, 'Name <small>(optional)</small>'), true);
    [$out] = $sharePage(['mode' => 'love']);
    same(substr_count($out, '* required'), 1);
    same(substr_count($out, '<span class="req" aria-hidden="true">*</span>'), 1); // the other soul's name; "you" is carried
    same(str_contains($out, 'Only the name is required'), false);
    same(preg_match('/<label for="b_name">Name <span class="req"/', $out), 1);
    same(preg_match('/<label for="b_date">Birth date<\/label>/', $out), 1);
});

// ---- Help ----
$uiPages = function () use ($sharePage): array {
    $self = ['mode' => 'self', 'name' => 'Ann', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome',
        'pos_city' => 'Paris', 'pos_lat' => '48.85', 'pos_lon' => '2.35', 'pos_tz' => 'Europe/Paris', 'on' => '2026-10-09', 'noaudit' => ''];
    $love = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
        'a_pos_city' => 'Paris', 'a_pos_lat' => '48.85', 'a_pos_lon' => '2.35', 'a_pos_tz' => 'Europe/Paris',
        'b_name' => 'Silvia', 'b_date' => '1991-03-02', 'b_time' => '10:00', 'b_city' => 'Milan', 'b_lat' => '45.46', 'b_lon' => '9.19', 'b_tz' => 'Europe/Rome',
        'on' => '2026-10-09', 'noaudit' => '', 't' => '22u,40r,77u'];
    $srv = ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/'];
    $hid = ['mode' => 'love', 'h' => 'MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS1gA'] + array_diff_key($love, array_flip(['b_name', 'b_date', 'b_time', 'b_city', 'b_lat', 'b_lon', 'b_tz']));
    return [$sharePage($self, $srv)[0], $sharePage($love, $srv)[0], $sharePage($hid, $srv)[0], $sharePage(['mode' => 'friends'], $srv)[0]];
};
$uiHtml = $uiPages();
check('help: every key used in a template exists; every key exists on a rendered page', function () use ($uiRoot, $uiHtml) {
    $keys = Help::keys();
    same(count(array_unique($keys)), count($keys));
    foreach (Help::DYNAMIC as $prefix => $ids) {
        foreach ($ids as $id) {
            same(isset(Help::TEXT[$prefix . '.' . $id]), true);
        }
    }
    same(count(Help::dynamicKeys()), 8);
    $files = array_merge(glob($uiRoot . '/templates/*.php'), glob($uiRoot . '/templates/partials/*.php'));
    foreach ($files as $f) {
        if (str_ends_with($f, 'partials/help.php')) {
            continue;
        }
        preg_match_all("/help_term\('([a-z_.]+)'(\s*\.)?/", (string) file_get_contents($f), $m, PREG_SET_ORDER);
        foreach ($m as $x) {
            if (isset($x[2])) {
                same(isset(Help::DYNAMIC[rtrim($x[1], '.')]) || count(array_filter(Help::keys(), fn ($k) => str_starts_with($k, $x[1]))) > 0, true);
            } else {
                same(isset(Help::TEXT[$x[1]]), true);
            }
        }
    }
    $seen = [];
    foreach ($uiHtml as $html) {
        preg_match_all('/data-help="([^"]+)"/', $html, $m);
        foreach ($m[1] as $k) {
            same(isset(Help::TEXT[$k]), true);
            $seen[$k] = true;
        }
        preg_match_all('/<(?:span|button|div|p|h2|input)[^>]* id="([^"]+)"/', $html, $ids);
        same(count($ids[1]), count(array_unique($ids[1])));
        preg_match_all('/data-help-for="(help-\d+)"/', $html, $c);
        foreach ($c[1] as $id) {
            same(substr_count($html, 'id="' . $id . '"'), 1);
        }
        same(substr_count($html, 'class="help__pop" hidden data-help="'), count($c[1]));
        same(str_contains($html, 'help__btn'), false);
        same(preg_match('/<label[^>]*>(?:(?!<\/label>).)*class="help"/s', $html), 0);
    }
    foreach (Help::keys() as $k) {
        if (!isset($seen[$k])) {
            throw new RuntimeException('help key never rendered: ' . $k);
        }
    }
});
check('help: texts are 20-240 characters, unique, plain words only; unknown key throws in tests', function () {
    $texts = [];
    foreach (Help::TEXT as $k => $h) {
        $len = mb_strlen($h['text'], 'UTF-8');
        same($len >= 20 && $len <= 240, true);
        same($h['title'] !== '', true);
        $texts[] = $h['text'];
        foreach (['algorithm', 'formula', 'calculat', 'ephemeris', 'sidereal time', 'vsop', 'meeus'] as $bad) {
            same(str_contains(strtolower($h['text'] . $h['title']), $bad), false);
        }
    }
    same(count(array_unique($texts)), count($texts));
    same(count(Help::TEXT), 33);
    if (!function_exists('e')) {
        require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
    }
    require_once dirname(__DIR__, 2) . '/templates/partials/help.php';
    $thrown = false;
    try {
        ob_start();
        help_term('no.such.key', 'x');
    } catch (InvalidArgumentException) {
        $thrown = true;
    } finally {
        ob_end_clean();
    }
    same($thrown, true);
});
check('help.js: Escape, outside click, no storage, network or HTML injection; node syntax check', function () use ($uiRoot) {
    $js = (string) file_get_contents($uiRoot . '/public/assets/help.js');
    same(str_contains($js, 'Escape') && str_contains($js, 'closest(".help")') && str_contains($js, 'aria-expanded'), true);
    // Scroll never closes the popup: the sheet stays on mobile, desktop repositions it.
    preg_match('/addEventListener\(\s*"scroll"(.*?)true\s*\)/s', $js, $sc);
    same(isset($sc[1]) && !str_contains($sc[1], 'closeAll') && str_contains($sc[1], '!WIDE.matches') && str_contains($sc[1], 'place('), true);
    same(str_contains($js, 'addEventListener("resize", () => closeAll(null))'), true);
    foreach (['innerHTML', 'fetch(', 'XMLHttpRequest', 'localStorage', 'sessionStorage', 'eval(', 'cookie'] as $bad) {
        same(str_contains($js, $bad), false);
    }
    $node = trim((string) shell_exec('command -v node 2>/dev/null'));
    foreach (['help', 'share', 'memory', 'autocomplete', 'print', 'import'] as $f) {
        if ($node !== '') {
            exec(escapeshellarg($node) . ' --check ' . escapeshellarg("$uiRoot/public/assets/$f.js") . ' 2>&1', $o, $rc);
            same($rc, 0);
        }
    }
});

// ---- QR blocks and share.js ----
check('qr: collapsible open block, copy target exists, status line, hint hidden until the script runs', function () use ($sharePage) {
    [$out] = $sharePage(['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Silvia', 'on' => '2026-10-09', 'noaudit' => ''],
        ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    same(preg_match('/<details class="share__qrbox" open>\s*<summary>QR code<\/summary>.*?data-qr-copy="share-link".*?<svg class="qr".*?<\/details>/s', $out), 1);
    same(str_contains($out, 'id="share-link"'), true);
    same(str_contains($out, 'data-copy-status'), true);
    same(str_contains($out, 'data-qr-hint hidden'), true);
});
check('share.js: clipboard chain, feedback texts, keyboard, no storage and no network', function () use ($uiRoot) {
    $js = (string) file_get_contents($uiRoot . '/public/assets/share.js');
    foreach (['navigator.clipboard', 'ClipboardItem', 'execCommand("copy")', 'isSecureContext', 'Link copied', 'Press Ctrl+C', 'data-qr-copy', '"role"', 'keydown'] as $needle) {
        same(str_contains($js, $needle), true);
    }
    foreach (['innerHTML', 'eval(', 'document.write', 'localStorage', 'sessionStorage'] as $bad) {
        same(str_contains($js, $bad), false);
    }
    preg_match_all('/fetch\(([^,)]*)/', $js, $m);
    same($m[1], ['"hidden.php"']);
});

// ---- CSS regressions ----
check('css: Terms section cannot be covered, gate text scrolls and the Accept bar is not sticky', function () use ($uiCss) {
    preg_match('/\.notice \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'margin: 0 auto 40px'), true);
    same(preg_match('/margin:\s*-\d/', $m[1]), 0);
    same(str_contains($m[1], 'position: relative') && preg_match('/z-index:\s*[1-9]/', $m[1]) === 1, true);
    preg_match('/\.gate__actions \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'sticky'), false);
    preg_match('/\.gate__terms \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'overflow-y: auto'), true);
    preg_match('/\.gate__box \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'flex-direction: column') && str_contains($m[1], 'max-height'), true);
    $home = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/home.php');
    same(str_contains($home, 'class="gate__terms" tabindex="0" role="region" aria-label="Terms and Conditions text"'), true);
});
$uiLum = function (string $hex): float {
    $c = array_map(fn ($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
$uiRatio = fn (string $a, string $b): float => (max($uiLum($a), $uiLum($b)) + 0.05) / (min($uiLum($a), $uiLum($b)) + 0.05);
check('css: wheel is small, lighter than the panel, with bright strokes, readable glyphs and sizes in drawing units', function () use ($uiCss, $uiRatio) {
    preg_match('/\n\.wheel \{([^}]*)\}/', $uiCss, $m);
    preg_match('/width: min\(100%, (\d+)px\)/', $m[1], $w);
    same((int) $w[1] <= 260, true);
    preg_match('/background: (#[0-9a-f]{6})/', $m[1], $bg);
    same($bg[1] !== '#0f0d2c' && $bg[1] !== '#17153a', true);
    preg_match('/--wheel-line: (#[0-9a-f]{6})/', $uiCss, $line);
    same($line[1] !== '#2e2a63', true);
    same($uiRatio($line[1], $bg[1]) >= 3, true);
    foreach (['#d9d5fa', '#ece9ff', '#ff7a59', '#7fc08a', '#7cc4ff', '#c3acff', '#f2c96b'] as $fg) {
        same($uiRatio($fg, $bg[1]) >= 4.5, true);
    }
    foreach (['wheel__sign' => 24, 'wheel__body' => 22] as $cls => $min) {
        preg_match('/\.' . $cls . ' \{[^}]*font-size: (\d+)px/', $uiCss, $f);
        same((int) $f[1] >= $min, true);
    }
    preg_match('/\.wheel__house \{[^}]*font: 700 (\d+)px/', $uiCss, $f);
    same((int) $f[1] >= 18, true);
    preg_match('/\.wheel__axis-label \{[^}]*font: 700 (\d+)px/', $uiCss, $f);
    same((int) $f[1] >= 18, true);
    same(str_contains($uiCss, '@media (max-width: 400px)'), false);
});
check('wheel: drawn glyphs keep the larger minimum separation', function () {
    same(Magic\ChartWheel::MIN_SEPARATION >= 10.0, true);
});

// ---- ADR 0008: quiet buttons, help terms, Friends, WhatsApp ----
$uiRemovedHelp = ['field.name', 'field.date', 'field.time', 'field.import', 'self.hidden_code', 'self.clear', 'self.save', 'self.reveal', 'friends.list', 'friends.search',
    'share.link', 'share.qr', 'share.live', 'self.midheaven', 'self.houses', 'self.retrograde', 'self.geo', 'love.sync_overall', 'love.import', 'love.match_hidden'];
check('help: the 20 removed keys are gone, 33 remain, none left in templates', function () use ($uiRoot, $uiRemovedHelp) {
    foreach ($uiRemovedHelp as $k) {
        same([$k, isset(Help::TEXT[$k])], [$k, false]);
    }
    same(count(Help::TEXT), 33);
    $files = array_merge(glob($uiRoot . '/templates/*.php'), glob($uiRoot . '/templates/partials/*.php'));
    foreach ($files as $f) {
        $src = (string) file_get_contents($f);
        same([$f, str_contains($src, 'help_button')], [$f, false]);
        foreach ($uiRemovedHelp as $k) {
            same([$f, $k, str_contains($src, "'" . $k . "'")], [$f, $k, false]);
        }
    }
});
check('help: an unknown key prints the plain label outside tests; term markup is escaped', function () {
    require_once dirname(__DIR__, 2) . '/templates/partials/help.php';
    ob_start();
    help_term('self.sun', 'A<b>');
    $html = ob_get_clean();
    same(str_contains($html, '<span class="help__term" data-help-for="help-'), true);
    same(str_contains($html, 'A&lt;b&gt;') && !str_contains($html, 'A<b>'), true);
    same(preg_match('/<span id="help-\d+" class="help__pop" hidden data-help="self.sun">/', $html), 1);
});
check('buttons: only the listed buttons are primary; quiet default has no gradient; contrast of quiet and disabled text', function () use ($uiCss, $uiRatio, $sharePage, $uiRoot) {
    preg_match('/\nbutton, \.btn \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'gradient'), false);
    same(str_contains($m[1], 'background: transparent'), true);
    preg_match('/\n\.btn--primary \{([^}]*)\}/', $uiCss, $p);
    same(str_contains($p[1], 'linear-gradient'), true);
    same(preg_match('/--gold: (#[0-9a-f]{6}).*--panel: |--panel: (#[0-9a-f]{6})/s', $uiCss) === 1, true);
    preg_match('/--panel: (#[0-9a-f]{6})/', $uiCss, $panel);
    preg_match('/--gold: (#[0-9a-f]{6})/', $uiCss, $gold);
    preg_match('/--muted: (#[0-9a-f]{6})/', $uiCss, $muted);
    same($uiRatio($gold[1], $panel[1]) >= 4.5, true);
    same($uiRatio($muted[1], $panel[1]) >= 3, true);
    $primary = [];
    foreach ([['mode' => 'self'], ['mode' => 'love']] as $get) {
        [$out] = $sharePage($get);
        preg_match_all('/<button[^>]*class="[^"]*btn--primary[^"]*"[^>]*>([^<]*)</', $out, $b);
        $primary = array_merge($primary, $b[1]);
    }
    sort($primary);
    same($primary, ['Explore our connection', 'Reveal my sky', 'Yes, continue', 'Yes, continue']);
    [$gate] = $sharePage([], [], false);
    same(preg_match('/<button type="submit" class="btn--primary" name="action" value="accept" autofocus>I accept the Terms and Conditions</', $gate), 1);
    foreach (['Generate hidden data code', 'Clear data', 'Save the data', 'Copy link', 'Scan a QR code', 'Explore with these details'] as $quiet) {
        [$out] = $sharePage(['mode' => 'self']);
        $quietHtml = $out;
        same(preg_match('/<button[^>]*btn--primary[^>]*>' . preg_quote($quiet, '/') . '</', $quietHtml), 0);
    }
    $src = (string) file_get_contents($uiRoot . '/templates/partials/confirm.php');
    same(str_contains($src, 'btn--primary'), true);
});
check('css: help terms, popup, bottom sheet, print, icon buttons', function () use ($uiCss) {
    same(preg_match('/\n\.help__term \{[^}]*border-bottom/', $uiCss), 0);
    same(preg_match('/\.help--on \.help__term \{[^}]*border-bottom: 1px dotted/', $uiCss), 1);
    preg_match('/\n\.help__pop \{([^}]*)\}/', $uiCss, $m);
    same(str_contains($m[1], 'position: absolute') && str_contains($m[1], 'max-width'), true);
    same(preg_match('/@media \(max-width: 720px\) \{\s*\.help__pop \{[^}]*position: fixed[^}]*bottom/', $uiCss), 1);
    $print = substr($uiCss, (int) strpos($uiCss, '@media print'));
    same(str_contains($print, '.help__pop') && str_contains($print, '.help--on .help__term { border-bottom: 0'), true);
    preg_match('/\n\.iconbtn \{([^}]*)\}/', $uiCss, $i);
    same(str_contains($i[1], 'min-width: 32px') && str_contains($i[1], 'min-height: 32px'), true);
    same(str_contains($uiCss, '.help__btn'), false);
});
check('help.js: keyboard, ARIA, placement through the CSSOM; share.js: single messaging address, no window.open', function () use ($uiRoot) {
    $js = (string) file_get_contents($uiRoot . '/public/assets/help.js');
    foreach (['role', 'tabindex', 'aria-expanded', 'aria-controls', 'keydown', 'getBoundingClientRect', 'style.left', 'Escape', 'closest(".help")'] as $n) {
        same([$n, str_contains($js, $n)], [$n, true]);
    }
    $share = (string) file_get_contents($uiRoot . '/public/assets/share.js');
    same(substr_count($share, 'https://wa.me/'), 1);
    foreach (['encodeURIComponent', 'data-hidden-whatsapp'] as $n) {
        same([$n, str_contains($share, $n)], [$n, true]);
    }
    foreach (['window.open', 'innerHTML', 'localStorage', 'sessionStorage'] as $bad) {
        same([$bad, str_contains($share, $bad)], [$bad, false]);
    }
});
check('csp: unchanged, no third-party host, no external script in any template', function () use ($uiRoot) {
    $ht = (string) file_get_contents($uiRoot . '/public/.htaccess');
    same(str_contains($ht, "script-src 'self'") && str_contains($ht, "form-action 'self'"), true);
    same(preg_match('/wa\.me|whatsapp/i', $ht), 0);
    foreach (array_merge(glob($uiRoot . '/templates/*.php'), glob($uiRoot . '/templates/partials/*.php')) as $f) {
        same([$f, str_contains((string) file_get_contents($f), '<script src="http')], [$f, false]);
    }
});
check('friends: compact panel with icon buttons, accessible names, selected count hook, no visible button labels', function () use ($sharePage, $uiRoot) {
    [$out] = $sharePage(['mode' => 'friends']);
    same(preg_match('/<button type="button" class="iconbtn" data-friends-selectall aria-label="Select all shown" title="Select all shown"><svg[^>]*aria-hidden="true"/', $out), 1);
    same(preg_match('/<button type="button" class="iconbtn" data-friends-remove-selected disabled aria-label="Remove selected \(0\)" title="Remove selected \(0\)"><svg/', $out), 1);
    same(str_contains($out, 'data-friends-selected'), true);
    same(preg_match('/<a class="iconbtn" data-friend-compare href="[^"]*" aria-label="Compare with this friend" title="Compare"><svg/', $out), 1);
    same(preg_match('/<button type="button" class="iconbtn" data-friend-remove aria-label="Remove this friend" title="Remove"><svg/', $out), 1);
    same(preg_match('/<label for="friends-search" class="sr-only">Search by nickname<\/label>/', $out), 1);
    same(str_contains($out, 'Select all shown</button>') || str_contains($out, 'Remove</button>'), false);
    $mem = (string) file_get_contents($uiRoot . '/public/assets/memory.js');
    same(str_contains($mem, 'setText(selected'), true);
    $icons = (string) file_get_contents($uiRoot . '/templates/partials/icons.php');
    foreach (['check-all', 'trash', 'compare', 'x'] as $name) {
        same([$name, str_contains($icons, "'" . $name . "' =>")], [$name, true]);
    }
});
check('self page: no share section; hidden-derived Love: no Sharing section, via h and via import', function () use ($sharePage) {
    $self = ['mode' => 'self', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome', 'on' => '2026-10-09', 'noaudit' => ''];
    [$out] = $sharePage($self, ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    same(preg_match('/Share this reading|id="share-h"|id="share-link"|data-copy="share-link"|data-qr-copy="share-link"/', $out), 0);
    same(str_contains($out, 'id="results"') && str_contains($out, 'data-print'), true);
    $hid = 'MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS1gA';
    $love = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'on' => '2026-10-09', 'noaudit' => ''];
    foreach ([['h' => $hid], ['import' => 'https://example.org/?h=' . $hid]] as $extra) {
        [$out] = $sharePage($love + $extra, ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
        same(str_contains($out, 'class="tarot__card '), true);
        same(preg_match('/Share this reading|>Sharing<|has no share link|id="share-h"/', $out), 0);
    }
});
check('love page with typed people: share links never carry h or nick', function () use ($sharePage) {
    $love = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome',
        'b_name' => 'Silvia', 'b_date' => '1991-03-02', 'on' => '2026-10-09', 'noaudit' => '', 'nick' => 'Bea'];
    [$out] = $sharePage($love, ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    same(str_contains($out, 'id="share-h"'), true);
    preg_match('/id="share-link"[^>]*value="([^"]*)"/', $out, $m);
    same(str_contains($m[1], 'nick') || str_contains($m[1], 'h='), false);
    same(str_contains($out, 'is not encrypted. Share it only with people you trust.'), true);
});

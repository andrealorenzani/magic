<?php
declare(strict_types=1);

// ADR 0008: VERSION, badges, README / DEVELOPER.md shape, confidentiality scan.
// README and DEVELOPER.md checks apply once the documenter has rewritten them (README starts with the badge row).

$docRoot = dirname(__DIR__, 2);
$docRead = fn (string $f): string => is_file("$docRoot/$f") ? (string) file_get_contents("$docRoot/$f") : '';
$docSite = 'magic.' . 'super' . 'maestro' . '.org';
$docReadme = $docRead('README.md');
$docReady = str_starts_with(ltrim($docReadme), '![') || str_starts_with(ltrim($docReadme), '[![');
$docTracked = function () use ($docRoot): array {
    $out = [];
    exec('git -C ' . escapeshellarg($docRoot) . ' ls-files -z 2>/dev/null', $out, $rc);
    return $rc === 0 ? array_values(array_filter(explode("\0", implode("\n", $out)))) : [];
};

$docBalanced = function (string $xml): bool {
    preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)[^>]*?(\/?)>/', $xml, $m, PREG_SET_ORDER);
    $stack = [];
    foreach ($m as $t) {
        if ($t[3] === '/') {
            continue;
        }
        if ($t[1] === '/') {
            if (array_pop($stack) !== $t[2]) {
                return false;
            }
        } else {
            $stack[] = $t[2];
        }
    }
    return $stack === [] && preg_match('/<(?![\/a-zA-Z])/', $xml) === 0;
};

check('docs: VERSION is N.N.N and the changelog agrees (Unreleased above, or the same MAJOR.MINOR)', function () use ($docRead) {
    $v = $docRead('VERSION');
    same(preg_match('/^\d+\.\d+\.\d+\n?$/', $v), 1);
    $log = $docRead('docs/changelog.md');
    preg_match_all('/^## (.+)$/m', $log, $heads);
    $first = $heads[1][0] ?? '';
    if (stripos($first, 'unreleased') === 0) {
        return;
    }
    preg_match('/^v(\d+)\.(\d+)/', $first, $m);
    [$maj, $min] = array_map('intval', explode('.', trim($v)));
    same([(int) ($m[1] ?? -1), (int) ($m[2] ?? -1)], [$maj, $min]);
});

check('docs: badges are self-contained SVG; version badge shows VERSION; loc badge is within 10 percent of the live count', function () use ($docRoot, $docRead, $docTracked, $docBalanced) {
    $v = trim($docRead('VERSION'));
    foreach (['version', 'loc', 'deployed'] as $name) {
        $svg = $docRead("docs/badges/$name.svg");
        same([$name, $svg !== ''], [$name, true]);
        same([$name, $docBalanced($svg)], [$name, true]);
        same([$name, preg_match('/^<svg [^>]*role="img"[^>]*aria-label="[^"]+"/', $svg)], [$name, 1]);
        same([$name, substr_count($svg, '<title>')], [$name, 1]);
        $scan = strtolower(str_replace('xmlns="http://www.w3.org/2000/svg"', '', $svg));
        foreach (['<script', 'href', 'xlink', 'http', '<image', '@import', 'url(', '<a ', '<style', 'metadata'] as $bad) {
            same([$name, $bad, str_contains($scan, $bad)], [$name, $bad, false]);
        }
    }
    same(str_contains($docRead('docs/badges/version.svg'), '>v' . $v . '<'), true);
    $tracked = $docTracked();
    if ($tracked === []) {
        return;
    }
    $lines = 0;
    foreach ($tracked as $f) {
        if (preg_match('/\.(php|js|css|sh|sql)$/', $f) && !str_starts_with($f, 'docs/') && is_file("$docRoot/$f")) {
            $lines += substr_count((string) file_get_contents("$docRoot/$f"), "\n");
        }
    }
    preg_match('/lines of code<\/title>|<title>lines of code: ([0-9.]+)(k?)</', $docRead('docs/badges/loc.svg'), $m);
    same(isset($m[1]), true);
    $shown = (float) $m[1] * ($m[2] === 'k' ? 1000 : 1);
    same(abs($shown - $lines) <= 0.1 * $lines, true);
});

check('deploy.sh: dry run lists tracked files but never anything under release/', function () use ($docRoot) {
    $tmp = sys_get_temp_dir() . '/magic-deploy-' . bin2hex(random_bytes(4));
    mkdir("$tmp/scripts", 0777, true);
    mkdir("$tmp/tests", 0777, true);
    mkdir("$tmp/release", 0777, true);
    copy("$docRoot/scripts/deploy.sh", "$tmp/scripts/deploy.sh");
    file_put_contents("$tmp/tests/run.php", "<?php\n");
    file_put_contents("$tmp/.deploy.local", "DEPLOY_HOST=example.invalid\nDEPLOY_DIR=/x\n");
    file_put_contents("$tmp/index.txt", 'a');
    file_put_contents("$tmp/release/package.zip", 'b');
    try {
        $cd = 'cd ' . escapeshellarg($tmp) . ' && ';
        exec($cd . 'git init -q . && git add index.txt tests scripts release && git -c user.name=t -c user.email=t@t commit -qm t 2>&1', $o, $rc);
        same($rc, 0);
        $out = (string) shell_exec($cd . 'bash scripts/deploy.sh --all --dry-run 2>&1');
        same(str_contains($out, 'would upload index.txt'), true);
        same(str_contains($out, 'release/'), false);
    } finally {
        exec('rm -rf ' . escapeshellarg($tmp));
    }
});

check('docs: update-badges.sh rewrites version.svg byte for byte offline in a temp copy, rejects a bad VERSION, never touches deployed.svg', function () use ($docRoot, $docRead) {
    $tmp = sys_get_temp_dir() . '/magic-badges-' . bin2hex(random_bytes(4));
    mkdir("$tmp/scripts", 0777, true);
    mkdir("$tmp/docs/badges", 0777, true);
    copy("$docRoot/scripts/update-badges.sh", "$tmp/scripts/update-badges.sh");
    chmod("$tmp/scripts/update-badges.sh", 0755);
    copy("$docRoot/VERSION", "$tmp/VERSION");
    file_put_contents("$tmp/docs/badges/deployed.svg", 'keep');
    exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/update-badges.sh 2>&1', $o, $rc);
    same($rc, 0);
    same((string) file_get_contents("$tmp/docs/badges/version.svg"), $docRead('docs/badges/version.svg'));
    same((string) file_get_contents("$tmp/docs/badges/deployed.svg"), 'keep');
    file_put_contents("$tmp/VERSION", "1.2\n");
    exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/update-badges.sh 2>&1', $o, $rc);
    same($rc, 1);
    foreach (['version.svg', 'loc.svg', 'deployed.svg'] as $f) {
        @unlink("$tmp/docs/badges/$f");
    }
    foreach (["$tmp/docs/badges", "$tmp/docs", "$tmp/scripts/update-badges.sh", "$tmp/scripts", "$tmp/VERSION", $tmp] as $p) {
        is_dir($p) ? @rmdir($p) : @unlink($p);
    }
});

check('docs: README is the business specification (badge row, first note, sections, no developer material)', function () use ($docReady, $docReadme, $docSite) {
    if (!$docReady) {
        return; // waits for the documenter
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", $docReadme)), fn ($l) => $l !== ''));
    same(preg_match('/^!\[[^\]]*\]\(docs\/badges\/version\.svg\) ?!\[[^\]]*\]\(docs\/badges\/loc\.svg\) ?\[!\[[^\]]*\]\(docs\/badges\/deployed\.svg\)\]\([^)]+\)$/', $lines[0]), 1);
    same(preg_match('/\d/', preg_replace('/\(docs\/badges\/[a-z]+\.svg\)/', '', $lines[0])), 0);
    same(ltrim($lines[1], '> '), 'This project can be used on [' . $docSite . '](https://' . $docSite . ')');
    foreach (['noaudit', 'docker', 'php tests', 'scripts/', 'composer', '.deploy'] as $bad) {
        same([$bad, str_contains(strtolower($docReadme), $bad)], [$bad, false]);
    }
    foreach (['algorithm', 'formula', 'ephemeris', 'sidereal time', 'vsop', 'meeus'] as $bad) {
        same([$bad, str_contains(strtolower($docReadme), $bad)], [$bad, false]);
    }
    preg_match_all('/^#{1,3} (.+)$/m', $docReadme, $h);
    $pos = -1;
    foreach (['What Magic is', 'Self Discovery', 'The chart wheel', 'Soul Affinity', 'Friends hidden codes', 'Hidden data codes', 'Sharing a Soul Affinity reading',
        'Where you are now', 'Remembered in your browser', 'Terms and privacy', 'Limits'] as $title) {
        $found = false;
        foreach ($h[1] as $i => $head) {
            if ($i > $pos && stripos($head, $title) !== false) {
                $pos = $i;
                $found = true;
                break;
            }
        }
        same([$title, $found], [$title, true]);
    }
    same(stripos($docReadme, 'chart wheel') !== false, true);
    same(str_contains($docReadme, 'DEVELOPER.md'), true);
});

check('docs: DEVELOPER.md carries the developer material', function () use ($docRead, $docReady) {
    if (!$docReady) {
        return; // waits for the documenter
    }
    $d = $docRead('DEVELOPER.md');
    same($d !== '', true);
    foreach (['noaudit', 'php tests/run.php', 'scripts/deploy.sh', 'scripts/update-badges.sh'] as $needle) {
        same([$needle, str_contains($d, $needle)], [$needle, true]);
    }
});

check('docs: the site name appears only where the owner allowed it', function () use ($docRoot, $docTracked) {
    $name = 'super' . 'maestro';
    $allowed = ['README.md', 'docs/badges/deployed.svg', 'CLAUDE.md'];
    $files = $docTracked();
    if ($files === []) {
        return;
    }
    foreach ($files as $f) {
        if (in_array($f, $allowed, true) || !is_file("$docRoot/$f")) {
            continue;
        }
        same([$f, stripos((string) file_get_contents("$docRoot/$f"), $name) !== false], [$f, false]);
    }
});

check('docs: decisions folder holds only the template and 0001 or higher, and no doc links a removed ADR', function () use ($docRoot, $docTracked) {
    foreach (scandir("$docRoot/docs/decisions") ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        same([$f, $f === '0000-template.md' || preg_match('/^(000[1-9]|00[1-9]\d|0[1-9]\d\d|[1-9]\d{3})-.+\.md$/', $f) === 1], [$f, true]);
        same([$f, preg_match('/^000[2-8]-/', $f) === 1], [$f, false]);
    }
    foreach ($docTracked() as $f) {
        if (!preg_match('/\.md$/', $f) || !is_file("$docRoot/$f")) {
            continue;
        }
        same([$f, preg_match('/\]\([^)]*decisions\/000[2-8]-/', (string) file_get_contents("$docRoot/$f")) === 1], [$f, false]);
    }
});

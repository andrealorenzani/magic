<?php
declare(strict_types=1);

use Magic\Audit\AuditRecord;
use Magic\Audit\Yaml;
use Magic\Db\AuditLog;
use Magic\Db\Config;
use Magic\LoveReading;
use Magic\Request;
use Magic\SelfReading;

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/magic-audit-' . bin2hex(random_bytes(4));
mkdir($tmp);

// ---- Yaml (golden) ----
check('yaml: scalars', function () {
    same(Yaml::dump(['a' => 1, 'b' => true, 'c' => null, 'd' => 1.5, 'e' => 2.0, 'f' => -0.0]), "a: 1\nb: true\nc: null\nd: 1.5\ne: 2\nf: 0\n");
    same(Yaml::dump(['n' => NAN, 'i' => INF, 'r' => 0.123456]), "n: null\ni: null\nr: 0.1235\n");
});
check('yaml: plain vs quoted strings', function () {
    $d = fn (string $s) => Yaml::dump(['k' => $s]);
    same($d('Pisces'), "k: Pisces\n");
    same($d('Mary Ann'), "k: Mary Ann\n");
    same($d('Rome, Italy'), "k: \"Rome, Italy\"\n");
    same($d('a: b'), "k: \"a: b\"\n");
    same($d('# c'), "k: \"# c\"\n");
    same($d('- x'), "k: \"- x\"\n");
    foreach (['yes', 'No', 'null', '~', '123', '1e3'] as $w) {
        same($d($w), "k: \"$w\"\n");
    }
    same($d(''), "k: \"\"\n");
    same($d('it\'s "x"'), "k: \"it's \\\"x\\\"\"\n");
    same($d('a\\b'), "k: \"a\\\\b\"\n");
    same($d('x '), "k: \"x \"\n");
});
check('yaml: newlines, controls, unicode, invalid utf-8', function () {
    $d = fn (string $s) => Yaml::dump(['k' => $s]);
    same($d("l1\nl2"), "k: \"l1\\nl2\"\n");
    same($d("\r\n\t"), "k: \"\\r\\n\\t\"\n");
    same($d("\x00"), "k: \"\\0\"\n");
    same($d("\x1b"), "k: \"\\x1B\"\n");
    same($d("\x7f"), "k: \"\\x7F\"\n");
    same($d("\u{2028}"), "k: \"\\u2028\"\n");
    foreach (['Zoë', 'José Ñandú', '日本語', '🙂'] as $u) {
        same($d($u), "k: \"$u\"\n");
    }
    same($d("\xff"), "k: \"<invalid utf-8>\"\n");
});
check('yaml: injection stays on one quoted line and round-trips', function () {
    $evil = "x\nresponse: {admin: true}\n---\n!!php/object";
    $out = Yaml::dump(['name' => $evil]);
    $lines = explode("\n", rtrim($out, "\n"));
    same(count($lines), 1);
    same(str_starts_with($lines[0], 'name: "'), true);
    $q = substr($lines[0], 7, -1);
    $back = preg_replace_callback('/\\\\(.)/', fn ($m) => match ($m[1]) {'n' => "\n", 't' => "\t", 'r' => "\r", default => $m[1]}, $q);
    same($back, $evil);
});
check('yaml: bad keys and values throw', function () {
    foreach ([['Bad Key' => 1], ['a:b' => 1], ['' => 1], [5 => 1, 'a' => 2], ['o' => new stdClass()], ['r' => fopen('php://memory', 'r')]] as $bad) {
        try {
            Yaml::dump($bad);
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('expected exception');
    }
});
check('yaml: structure is nested, indented, deterministic', function () {
    $data = ['a' => ['b' => 1, 'c' => [1, 2, 3]], 'l' => [['x' => 1, 'y' => 'z'], ['x' => 2, 'y' => 'w']], 'e' => [], 'long' => array_fill(0, 30, 'abcdef')];
    $want = "a:\n  b: 1\n  c: [1, 2, 3]\nl:\n  - x: 1\n    y: z\n  - x: 2\n    y: w\ne: []\nlong:\n" . str_repeat("  - abcdef\n", 30);
    same(Yaml::dump($data), $want);
    same(Yaml::dump($data), Yaml::dump($data));
});

// ---- AuditRecord ----
$einstein = ['name' => 'Albert Einstein', 'year' => 1879, 'month' => 3, 'day' => 14, 'hour' => 11, 'minute' => 30,
    'lat' => 48.4, 'lon' => 10.0, 'tz' => 'Europe/Berlin', 'city' => 'Ulm, Baden-Württemberg, Germany'];
$selfRec = fn () => AuditRecord::fromSelf($einstein, SelfReading::build($einstein, '2026-10-09'), '2026-10-09');
$geoOffline = new Magic\Geo\Geocoder(sys_get_temp_dir());
$loveRec = function (array $q, ?array $self = null) use ($geoOffline) {
    $r = Request::parseLove($q, $geoOffline);
    if ($r['a'] === null || $r['b'] === null) {
        throw new RuntimeException('parse failed: ' . implode('|', $r['errors']));
    }
    return AuditRecord::fromLove($r['a'], $r['b'], LoveReading::build($r['a'], $r['b'], '2026-10-09'), '2026-10-09', $self);
};
$andrea = ['a_name' => 'Andrea Lorenzani', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome'];

check('audit record: self (Einstein reference chart)', function () use ($selfRec) {
    $r = $selfRec();
    same($r['functionality'], 'self');
    same(count($r['persons']), 1);
    $p = $r['persons'][0];
    same([$p['role'], $p['birth_date'], $p['birth_time'], $p['lat'], $p['tz']], ['self', '1879-03-14', '11:30:00', 48.4, 'Europe/Berlin']);
    foreach (['sun: {', 'ascendant:', 'moon:'] as $needle) {
        // block style: each body is a map with a sign line
        same(str_contains($r['response_yaml'], str_replace(': {', ':', $needle)), true);
    }
    same(str_contains($r['response_yaml'], "  sun:\n    sign: Pisces\n"), true);
    same(str_contains($r['response_yaml'], "  ascendant:\n    sign: Cancer\n"), true);
    same(str_contains($r['response_yaml'], "  moon:\n    sign: Sagittarius\n"), true);
    same(str_contains($r['response_yaml'], 'on_date: "2026-10-09"'), true);
    same(str_ends_with($r['response_yaml'], "\n"), true);
    same(strlen($r['response_yaml']) <= 65536, true);
    same($selfRec(), $r);
});
check('audit record: self has the Midheaven, the moon phase of the day and at birth', function () use ($selfRec) {
    $y = $selfRec()['response_yaml'];
    same(str_contains($y, "  midheaven:\n    sign: "), true);
    same(str_contains($y, "sky:\n  moon_phase: waning-crescent\n  moon_illumination: 2\n  moon_sign: Libra\n  born_moon_phase: "), true);
    same(preg_match('/born_moon_phase: [a-z-]+\n/', $y), 1);
});
check('audit record: love has the moon of the day and the synastry counts and tightest aspects', function () use ($loveRec, $andrea) {
    $y = $loveRec($andrea + ['b_name' => 'Silvia', 'b_date' => '1991-03-02'])['response_yaml'];
    same(str_contains($y, "sky:\n  moon_phase: waning-crescent\n"), true);
    same(str_contains($y, "synastry:\n  available: true\n  approx: true\n  total: "), true);
    same(preg_match('/tightest:\n    - a [a-z]+ [a-z]+ b [a-z]+ \d\.\d\n/', $y), 1);
    $y = $loveRec($andrea + ['b_name' => 'Silvia'])['response_yaml'];
    same(str_contains($y, "synastry:\n  available: false\n"), true);
    same(str_contains($y, "tightest: []"), true);
});
check('audit record: love (ADR 0002 reference, no self row)', function () use ($loveRec, $andrea) {
    $r = $loveRec($andrea + ['b_name' => 'Silvia Pellico']);
    same(array_column($r['persons'], 'role'), ['user', 'loved']);
    $b = $r['persons'][1];
    same([$b['birth_date'], $b['birth_time'], $b['place_label'], $b['lat'], $b['lon'], $b['tz']], [null, null, null, null, null, null]);
    same($r['persons'][0]['birth_time'], '08:30:00');
    same(str_contains($r['response_yaml'], 'percent: 48'), true);
    same(str_contains($r['response_yaml'], 'chain: [40223, 4245, 669, 135, 48]'), true);
    $self = ['name' => 'X', 'year' => 2000, 'month' => 1, 'day' => 1, 'hour' => 0, 'minute' => 0, 'lat' => 0.0, 'lon' => 0.0, 'tz' => 'UTC', 'city' => 'Null Island'];
    same(array_column($loveRec($andrea + ['b_name' => 'Silvia Pellico'], $self)['persons'], 'role'), ['self', 'user', 'loved']);
});
check('audit record: loved person time handling', function () use ($loveRec, $andrea) {
    $r = $loveRec($andrea + ['b_name' => 'B', 'b_date' => '1992-02-02', 'b_time' => '10:00', 'b_city' => 'Rome', 'b_lat' => '41.9', 'b_lon' => '12.5', 'b_tz' => 'Europe/Rome']);
    same($r['persons'][1]['birth_time'], '10:00:00');
    $r = $loveRec($andrea + ['b_name' => 'B', 'b_date' => '1992-02-02', 'b_city' => 'Rome', 'b_lat' => '41.9', 'b_lon' => '12.5', 'b_tz' => 'Europe/Rome']);
    same([$r['persons'][1]['birth_date'], $r['persons'][1]['birth_time'], $r['persons'][1]['place_label']], ['1992-02-02', null, null]);
    $r = $loveRec($andrea + ['b_name' => 'B', 'b_time' => '10:00']);
    same($r['persons'][1]['birth_time'], null);
});
check('audit record: oversize response falls back to truncated document', function () use ($einstein) {
    $view = SelfReading::build($einstein, '2026-10-09');
    $view['notes'] = [str_repeat('é', 70000)];
    $r = AuditRecord::fromSelf($einstein, $view, '2026-10-09');
    same($r['response_yaml'], "functionality: self\non_date: \"2026-10-09\"\ntruncated: true\n");
    same(mb_check_encoding($r['response_yaml'], 'UTF-8'), true);
});
check('audit record: hostile names pass through unchanged, quoted in YAML', function () use ($loveRec, $andrea) {
    $name = "O'Brien \"x\" 100%; '-- 🙂";
    $r = $loveRec(['a_name' => $name] + $andrea + ['b_name' => 'B']);
    same($r['persons'][0]['name'], $name);
    $r2 = $loveRec($andrea + ['b_name' => 'B']);
    same(str_contains($r2['response_yaml'], 'Lorenzani'), false); // names are not part of the summary
});
check('audit record: no secret-like keys', function () use ($selfRec, $loveRec, $andrea) {
    foreach ([$selfRec(), $loveRec($andrea + ['b_name' => 'B'])] as $r) {
        $keys = [];
        array_walk_recursive($r, function ($v, $k) use (&$keys) {
            $keys[] = (string) $k;
        });
        foreach ($keys as $k) {
            if (in_array(strtolower($k), ['password', 'host', 'ip', 'user_agent'], true)) {
                throw new RuntimeException("key $k");
            }
        }
        same(preg_match('/^(\s*-?\s*)(password|host|ip|user_agent):/m', $r['response_yaml']), 0);
    }
});

// ---- failure isolation ----
check('db config: invalid files give null, silently', function () use ($tmp) {
    set_error_handler(function ($no, $str) {
        throw new RuntimeException("warning: $str");
    });
    try {
        file_put_contents("$tmp/notarray.php", "<?php\nreturn 5;\n");
        file_put_contents("$tmp/nopass.php", "<?php\nreturn ['host' => 'h', 'name' => 'n', 'user' => 'u'];\n");
        file_put_contents("$tmp/good.php", "<?php\nreturn ['host' => 'h', 'name' => 'n', 'user' => 'u', 'password' => 'p'];\n");
        same(Config::load("$tmp/missing.php"), null);
        same(Config::load($tmp), null);
        same(Config::load("$tmp/notarray.php"), null);
        same(Config::load("$tmp/nopass.php"), null);
        same(Config::load("$tmp/good.php")['port'], 3306);
    } finally {
        restore_error_handler();
    }
});
$closedPortConfig = function () use ($tmp): string {
    $s = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    file_put_contents("$tmp/closed.php", "<?php\nreturn ['host' => '127.0.0.1', 'port' => $port, 'name' => 'secretdb', 'user' => 'secretuser', 'password' => 'secretpw'];\n");
    return "$tmp/closed.php";
};
check('audit log: missing config returns false, logs nothing', function () use ($tmp, $selfRec) {
    $log = "$tmp/err1.log";
    $old = ini_set('error_log', $log);
    try {
        same(AuditLog::tryWrite('/nonexistent/config.php', $selfRec()), false);
    } finally {
        ini_set('error_log', (string) $old);
    }
    same(is_file($log) ? (string) file_get_contents($log) : '', '');
});
check('audit log: connection failure is fast, logged without request data or credentials', function () use ($tmp, $selfRec, $closedPortConfig) {
    $log = "$tmp/err2.log";
    $old = ini_set('error_log', $log);
    $t = microtime(true);
    try {
        $rec = $selfRec();
        $rec['persons'][0]['name'] = 'Zaphod Beeblebrox';
        same(AuditLog::tryWrite($closedPortConfig(), $rec), false);
    } finally {
        ini_set('error_log', (string) $old);
    }
    if (microtime(true) - $t > 4) {
        throw new RuntimeException('too slow');
    }
    $l = (string) file_get_contents($log);
    same(str_contains($l, 'audit: write failed'), true);
    foreach (['Zaphod', '127.0.0.1', 'secretdb', 'secretuser', 'secretpw'] as $secret) {
        same(str_contains($l, $secret), false);
    }
});

// Run public/index.php in a subprocess and return [exit code, output].
$runPage = function (array $get, string $config, string $errorLog, bool $consent = true) use ($root): array {
    $code = '$_GET = json_decode($argv[1], true); $_COOKIE = ' . ($consent ? '["magic_terms" => "5"]' : '[]') . '; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start(); require $argv[2]; echo ob_get_clean();';
    $cmd = 'MAGIC_CONFIG=' . escapeshellarg($config) . ' php -d display_errors=0 -d log_errors=1 -d error_log=' . escapeshellarg($errorLog)
        . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg((string) json_encode($get)) . ' ' . escapeshellarg($root . '/public/index.php') . ' 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};
check('page renders without a database (self and love), with the Terms and Conditions section', function () use ($runPage, $tmp) {
    [$rc, $out] = $runPage(['mode' => 'self', 'date' => '1879-03-14', 'time' => '11:30', 'city' => 'Ulm', 'lat' => '48.4', 'lon' => '10', 'tz' => 'Europe/Berlin'], '/nonexistent', "$tmp/p1.log");
    same($rc, 0);
    same(str_contains($out, 'Pisces'), true);
    same(str_contains($out, 'It does not hold your IP address'), true);
    same(str_contains($out, 'id="terms"'), true);
    same(str_contains($out, 'class="gate"'), false);
    [$rc, $out] = $runPage(['mode' => 'love', 'a_name' => 'Andrea Lorenzani', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome', 'b_name' => 'Silvia Pellico'], '/nonexistent', "$tmp/p1.log");
    same($rc, 0);
    same(str_contains($out, 'Silvia Pellico'), true);
});
check('without accepted terms: popup only, no result, no audit attempt', function () use ($runPage, $tmp, $closedPortConfig) {
    $log = "$tmp/p-gate.log";
    [$rc, $out] = $runPage(['mode' => 'self', 'date' => '1879-03-14', 'time' => '11:30', 'city' => 'Ulm', 'lat' => '48.4', 'lon' => '10', 'tz' => 'Europe/Berlin'], $closedPortConfig(), $log, false);
    same($rc, 0);
    same(str_contains($out, 'class="gate"'), true);
    same(str_contains($out, 'I accept the Terms and Conditions'), true);
    same(str_contains($out, 'Pisces'), false);
    same(is_file($log) && str_contains((string) file_get_contents($log), 'audit:'), false);
});
check('consent: cookie check and safe redirect query', function () {
    same(\Magic\Consent::given(['magic_terms' => '5']), true);
    same(\Magic\Consent::given(['magic_terms' => '4']), false);
    same(\Magic\Consent::given(['magic_terms' => '2']), false);
    same(\Magic\Consent::given(['magic_terms' => '3']), false);
    same(\Magic\Consent::given(['magic_terms' => '0']), false);
    same(\Magic\Consent::given(['magic_terms' => '1']), false); // text changed: everyone accepts again
    same(\Magic\Consent::given([]), false);
    same(\Magic\Consent::safeQuery('?mode=self&date=1990-01-01'), 'mode=self&date=1990-01-01');
    same(\Magic\Consent::safeQuery("a=1\r\nSet-Cookie: x"), '');
    same(\Magic\Consent::safeQuery('//evil.example'), '//evil.example'); // stays a query after './?', never a host
});
check('audit is attempted for results only (closed-port config)', function () use ($runPage, $tmp, $closedPortConfig) {
    $cfg = $closedPortConfig();
    $attempts = function (array $get) use ($runPage, $tmp, $cfg): bool {
        $log = "$tmp/p-" . bin2hex(random_bytes(3)) . '.log';
        [$rc] = $runPage($get, $cfg, $log);
        same($rc, 0);
        return is_file($log) && str_contains((string) file_get_contents($log), 'audit: write failed');
    };
    same($attempts(['mode' => 'self', 'date' => '1879-03-14', 'time' => '11:30', 'city' => 'Ulm', 'lat' => '48.4', 'lon' => '10', 'tz' => 'Europe/Berlin']), true);
    same($attempts([]), false);
    same($attempts(['mode' => 'self', 'date' => '2024-13-01', 'time' => '25:00', 'city' => 'Rome']), false);
    same($attempts(['mode' => 'love', 'a_name' => '', 'b_name' => 'X']), false);
});

// ---- layering / hygiene ----
check('layering: src/Audit is pure; src/Db has no output, no variable SQL, no message logging', function () use ($root) {
    foreach (glob("$root/src/Audit/*.php") as $f) {
        $src = (string) file_get_contents($f);
        if (preg_match('/\b(echo|print|file_get_contents|file_put_contents|fopen|curl_\w+|time|microtime|date|gmdate|error_log)\s*\(|\$_(GET|POST|SERVER|COOKIE)|\becho\b/', $src)) {
            throw new RuntimeException('impure: ' . basename($f));
        }
    }
    foreach (glob("$root/src/Db/*.php") as $f) {
        $src = (string) file_get_contents($f);
        $n = basename($f);
        if (preg_match('/\$_(GET|POST|SERVER|COOKIE)|\becho\b|\bprint\b/', $src)) {
            throw new RuntimeException("output/globals in $n");
        }
        if (preg_match('/(->|::)(query|exec)\s*\(/', $src)) {
            throw new RuntimeException("query/exec in $n");
        }
        if (preg_match_all('/->prepare\(\s*([^;]*?)\)\s*(;|->)/', $src, $m)) {
            foreach ($m[1] as $arg) {
                if (!preg_match("/^'[^'\$]*'\$/", trim($arg))) {
                    throw new RuntimeException("non-literal prepare in $n");
                }
            }
        }
        if (preg_match('/error_log\([^;]*getMessage/', $src)) {
            throw new RuntimeException("message logged in $n");
        }
    }
});
check('scripts: no set -x, no echo/printf of credential variables to stdout, no cat of ~/.password', function () use ($root) {
    foreach (array_merge(glob("$root/scripts/*.sh"), glob("$root/scripts/lib/*.sh")) as $f) {
        foreach (file($f) as $i => $line) {
            $n = basename($f) . ':' . ($i + 1);
            if (preg_match('/^\s*#/', $line)) {
                continue;
            }
            if (preg_match('/\bset\s+-\w*x/', $line) || preg_match('/cat\s+[^|;]*\.password/', $line)) {
                throw new RuntimeException("$n");
            }
            if (preg_match('/\b(echo|printf)\b.*\$\{?\w*(PASS|PWD|CRED|HOST|SECRET)/', $line) && !preg_match('/>>?\s*"/', $line)) {
                throw new RuntimeException("$n prints a credential variable");
            }
        }
    }
});
check('terms make no retention promise', function () use ($root) {
    $t = (string) file_get_contents($root . '/templates/partials/terms.php');
    same(preg_match('/\\b90\\s*days\\b/i', $t), 0);
    same(str_contains((string) file_get_contents($root . '/templates/partials/terms.php'), 'can be removed on request'), true);
});
check('scripts: sanitized errors, safe python, purge validation, gitignore', function () use ($root) {
    $lib = (string) file_get_contents($root . '/scripts/lib/dbcred.sh');
    same(str_contains($lib, 'except BaseException'), true);
    same(str_contains($lib, ')" 2>/dev/null'), true);
    same(str_contains($lib, 'run_mysql()'), true);
    foreach (['db-migrate.sh', 'db-purge.sh'] as $f) {
        $s = (string) file_get_contents("$root/scripts/$f");
        same(preg_match('/^\\s*mysql\\s/m', $s), 0);
        same(str_contains($s, 'run_mysql'), true);
    }
    same(str_contains((string) file_get_contents($root . '/.gitignore'), ".config.*"), true);
    foreach (['0', '123456', 'abc', '-5', '1.5'] as $d) {
        exec('bash ' . escapeshellarg($root . '/scripts/db-purge.sh') . ' --days ' . escapeshellarg($d) . ' 2>&1', $o, $rc);
        same($rc, 2);
    }
    $dir = sys_get_temp_dir() . '/arc-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents("$dir/mysql", "#!/bin/sh\necho \"ERROR 1045 (28000): Access denied for user 'secretuser'@'10.9.8.7'\" >&2\nexit 1\n");
    chmod("$dir/mysql", 0755);
    file_put_contents("$dir/d", "[client]\n");
    $out = [];
    exec('PATH=' . escapeshellarg($dir) . ':$PATH bash -c ' . escapeshellarg('source ' . $root . '/scripts/lib/dbcred.sh; run_mysql ' . $dir . '/d db') . ' 2>&1', $out, $rc);
    $txt = implode("\n", $out);
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
    same($rc !== 0, true);
    same($txt, 'mysql error 1045 (28000): access denied');
});
check('hygiene: the private database section name is in no tracked file', function () use ($root) {
    $local = $root . '/.deploy.local';
    if (!is_file($local) || !preg_match('/^DB_PASSWORD_SECTION=(\S+)/m', (string) file_get_contents($local), $m)) {
        return; // nothing to check on this machine
    }
    $files = array_filter(explode("\n", (string) shell_exec('cd ' . escapeshellarg($root) . ' && git ls-files')));
    foreach ($files as $f) {
        if (is_file("$root/$f") && str_contains((string) file_get_contents("$root/$f"), $m[1])) {
            throw new RuntimeException("section name found in a tracked file: $f");
        }
    }
});

// ---- optional integration (real database; needs MAGIC_DB_TEST=1 and config.php) ----
if (getenv('MAGIC_DB_TEST') === '1' && is_file($root . '/config.php')) {
    check('integration: audit rows round-trip, cascade, strict column limit', function () use ($root, $einstein, $andrea, $loveRec) {
        $cfg = Config::load($root . '/config.php') ?? throw new RuntimeException('config unusable');
        $pdo = Magic\Db\Connection::open($cfg);
        $marker = 'ZZTEST-' . bin2hex(random_bytes(4));
        $ids = [];
        try {
            $selfIn = ['name' => $marker . ' "Q" 🙂'] + $einstein;
            $rec = AuditRecord::fromSelf($selfIn, SelfReading::build($selfIn, '2026-10-09'), '2026-10-09');
            same(AuditLog::tryWrite($root . '/config.php', $rec), true);
            $love = $loveRec(['a_name' => $marker . ' A'] + $andrea + ['b_name' => $marker . ' B']);
            same(AuditLog::tryWrite($root . '/config.php', $love), true);
            $q = $pdo->prepare('SELECT a.id, a.response_yaml, a.created_at, UTC_TIMESTAMP() AS now_utc, a.functionality FROM magic_audit a JOIN magic_audit_person p ON p.audit_id = a.id WHERE p.name LIKE ? ORDER BY a.id');
            $q->execute([$marker . '%']);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            same(count($rows), 2);
            foreach ($rows as $r) {
                $ids[] = (int) $r['id'];
                if (abs(strtotime($r['now_utc'] . ' UTC') - strtotime($r['created_at'] . ' UTC')) > 5) {
                    throw new RuntimeException('created_at off');
                }
            }
            same($rows[0]['response_yaml'], $rec['response_yaml']);
            same($rows[1]['response_yaml'], $love['response_yaml']);
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM magic_audit_person WHERE audit_id = ?');
            $cnt->execute([$ids[0]]);
            same((int) $cnt->fetchColumn(), 1);
            $cnt->execute([$ids[1]]);
            same((int) $cnt->fetchColumn(), 2);
            $n = $pdo->prepare('SELECT name FROM magic_audit_person WHERE audit_id = ?');
            $n->execute([$ids[0]]);
            same($n->fetchColumn(), $selfIn['name']);
            $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
            if (str_contains($mode, 'STRICT')) {
                $long = $rec;
                $long['persons'][0]['name'] = $marker . str_repeat('x', 41);
                $log = sys_get_temp_dir() . '/magic-int-' . bin2hex(random_bytes(3)) . '.log';
                $old = ini_set('error_log', $log);
                $ok = AuditLog::tryWrite($root . '/config.php', $long);
                ini_set('error_log', (string) $old);
                @unlink($log);
                same($ok, false);
            }
            $pdo->prepare('DELETE FROM magic_audit WHERE id = ?')->execute([$ids[0]]);
            $cnt->execute([$ids[0]]);
            same((int) $cnt->fetchColumn(), 0);
        } finally {
            foreach ($ids as $id) {
                $pdo->prepare('DELETE FROM magic_audit WHERE id = ?')->execute([$id]);
            }
        }
    });
}

array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);

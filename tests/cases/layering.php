<?php
declare(strict_types=1);

/** Pure core files must not do I/O or read the clock (token-based check). */
check('layering: pure core has no I/O, echo, globals, clock reads', function () {
    $root = dirname(__DIR__, 2) . '/src';
    $files = [$root . '/Chart.php', $root . '/ChartWheel.php', $root . '/SelfReading.php', $root . '/LoveReading.php', $root . '/Http.php'];
    foreach (['Astro', 'Earth', 'Sky', 'Time', 'Love', 'Bio', 'Tarot', 'Audit', 'Share', 'Content'] as $dir) {
        foreach (glob("$root/$dir/*.php") as $f) {
            $files[] = $f;
        }
    }
    $bad = [];
    foreach ($files as $file) {
        $tokens = array_values(array_filter(token_get_all((string) file_get_contents($file)), fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        foreach ($tokens as $i => $t) {
            if (!is_array($t)) {
                continue;
            }
            $name = basename($file);
            [$id, $text] = $t;
            $next = $tokens[$i + 1] ?? null;
            $prev = $tokens[$i - 1] ?? null;
            $isCall = $next === '(' && !(is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true));
            if (in_array($id, [T_ECHO, T_PRINT, T_EXIT, T_INLINE_HTML], true)) {
                $bad[] = "$name: $text";
            } elseif ($id === T_VARIABLE && in_array($text, ['$_GET', '$_POST', '$_SERVER', '$_COOKIE', '$_SESSION', '$_FILES'], true)) {
                $bad[] = "$name: $text";
            } elseif ($id === T_STRING && $isCall) {
                $l = strtolower($text);
                if (str_starts_with($l, 'file_') || str_starts_with($l, 'curl') || in_array($l, ['fopen', 'fwrite', 'fread', 'file', 'time', 'microtime', 'mt_srand', 'rand', 'mt_rand', 'random_int', 'header', 'session_start'], true)) {
                    $bad[] = "$name: $text()";
                } elseif (in_array($l, ['date', 'gmdate', 'mktime', 'gmmktime', 'strtotime'], true)) {
                    // Allowed only with an explicit timestamp argument (a top-level comma).
                    $depth = 0;
                    $comma = false;
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        $x = $tokens[$j];
                        if ($x === '(') {
                            $depth++;
                        } elseif ($x === ')') {
                            if (--$depth === 0) {
                                break;
                            }
                        } elseif ($x === ',' && $depth === 1) {
                            $comma = true;
                        }
                    }
                    if (!$comma) {
                        $bad[] = "$name: $text() without explicit timestamp";
                    }
                }
            } elseif ($id === T_NEW && is_array($next) && in_array(strtolower($next[1]), ['datetime', 'datetimeimmutable'], true)
                && ($tokens[$i + 2] ?? null) === '(' && ($tokens[$i + 3] ?? null) === ')') {
                $bad[] = basename($file) . ': new ' . $next[1] . '()';
            }
        }
    }
    if ($bad) {
        throw new RuntimeException(implode('; ', $bad));
    }
});
check('layering: templates escape output and carry no inline script/style', function () {
    $root = dirname(__DIR__, 2);
    $files = array_merge(glob("$root/templates/*.php"), glob("$root/templates/partials/*.php"));
    foreach ($files as $f) {
        $src = (string) file_get_contents($f);
        if (preg_match('/<script(?![^>]*\bsrc=)[^>]*>|<style|\sstyle=|\sonclick=/i', $src)) {
            throw new RuntimeException('inline script/style in ' . basename($f));
        }
        // A bare variable echo must go through e(); ternaries on constants are fine.
        if (preg_match('/<\?=\s*\$[\w\[\]\'"]+\s*\?>/', $src, $m)) {
            throw new RuntimeException('unescaped echo in ' . basename($f) . ': ' . $m[0]);
        }
    }
});

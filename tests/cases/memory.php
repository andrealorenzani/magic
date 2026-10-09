<?php
declare(strict_types=1);

$memRoot = dirname(__DIR__, 2);
$memJs = (string) file_get_contents($memRoot . '/public/assets/memory.js');

check('memory.js: no HTML injection, code evaluation or network access', function () use ($memJs) {
    foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'fetch(', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'new Function', 'EventSource', 'import(', 'cookie'] as $bad) {
        same(str_contains($memJs, $bad), false);
    }
    same(preg_match('/https?:\/\//', $memJs), 0);
});
check('memory.js: keys, limits and guarded storage access', function () use ($memJs) {
    foreach (['magic.me.v1', 'magic.loved.v1', 'MAX_PEOPLE = 20', 'MAX_LOVED_BYTES = 16384', 'MAX_NAME = 40', 'MAX_CITY = 80', 'MAX_TZ = 64', 'MAX_COORD = 12', 'MAX_ME_BYTES = 2048', 'MAX_RAW = 32768', 'o.v === 1', 'textContent', 'createElement("option")'] as $needle) {
        same(str_contains($memJs, $needle), true);
    }
    // Every storage call sits in a try block.
    preg_match_all('/\b(?:s|store)\.(?:getItem|setItem|removeItem|key)\(|\bwindow\.localStorage\b/', $memJs, $m, PREG_OFFSET_CAPTURE);
    same(count($m[0]) > 0, true);
    foreach ($m[0] as [, $off]) {
        $before = substr($memJs, 0, $off);
        same(strrpos($before, 'try {') > strrpos($before, '} catch'), true);
    }
    same(str_contains($memJs, 'storage"'), false); // storage events from other tabs are not used
});
check('memory.js: syntax check when node is available', function () use ($memRoot) {
    $node = trim((string) shell_exec('command -v node 2>/dev/null'));
    if ($node === '') {
        return;
    }
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($memRoot . '/public/assets/memory.js') . ' 2>&1', $out, $rc);
    same($rc, 0);
});
check('memory markup: forms, bars and select start hidden; no inline script or handlers', function () use ($sharePage, $selfGet) {
    foreach ([['mode' => 'self'], ['mode' => 'love']] as $get) {
        [$out] = $sharePage($get);
        same(substr_count($out, 'data-memory-bar hidden'), 1);
        same(preg_match('/<form id="(?:birth|love)-form"[^>]* data-memory="(?:self|love)"/', $out), 1);
        same(str_contains($out, 'data-memory-save hidden') && str_contains($out, 'data-memory-forget'), true);
        same(str_contains($out, 'src="assets/memory.js" defer'), true);
        same(preg_match('/<script(?![^>]*\bsrc=)|\son[a-z]+=|\sstyle=/i', $out), 0);
        same(str_contains($out, 'Your details are remembered in this browser only.'), true);
    }
    [$out] = $sharePage(['mode' => 'love']);
    same(str_contains($out, 'data-saved hidden') && str_contains($out, 'data-saved-select') && str_contains($out, 'data-saved-remove'), true);
    same(substr_count($out, 'data-person="me"'), 1);
    same(substr_count($out, 'data-person="loved"'), 1);
    [$out] = $sharePage(['mode' => 'self']);
    same(str_contains($out, 'data-saved-select'), false);
    same(substr_count($out, 'data-person="me"'), 1);
    same(str_contains($out, 'data-memory-forget-on-submit'), true);
});
check('memory markup: the page after withdrawing clears the stored details; normal pages do not', function () use ($sharePage) {
    [$out] = $sharePage(['withdrawn' => '1'], [], false);
    same(str_contains($out, 'data-forget-memory') && str_contains($out, 'src="assets/memory.js"'), true);
    [$out] = $sharePage(['withdrawn' => '1']);
    same(str_contains($out, 'data-forget-memory'), false);
    [$out] = $sharePage([], [], false);
    same(str_contains($out, 'data-forget-memory'), false);
});
check('memory: the Terms say remembered details stay in the browser and can be erased', function () use ($memRoot) {
    $terms = (string) file_get_contents($memRoot . '/templates/partials/terms.php');
    same(str_contains($terms, 'in your browser only'), true);
    same(str_contains($terms, 'never leaves it'), true);
    same(str_contains($terms, 'withdrawing your acceptance erases it too'), true);
});

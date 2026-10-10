<?php
declare(strict_types=1);

// ADR 0007: Self Discovery first, Soul Affinity, Friends hidden codes, nickname, locks, dialog, Terms version.
use Magic\Audit\AuditRecord;
use Magic\Consent;
use Magic\Content\Help;
use Magic\LoveReading;
use Magic\Request;

$flRoot = dirname(__DIR__, 2);
$flCss = (string) file_get_contents($flRoot . '/public/assets/styles.css');
$flSelf = ['date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome', 'lat' => '41.9', 'lon' => '12.5', 'tz' => 'Europe/Rome'];
$flA = ['a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome', 'a_lat' => '41.9', 'a_lon' => '12.5', 'a_tz' => 'Europe/Rome'];
$flCode = 'MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS1gA';
$flSecrets = ['Zerbinetta', 'Reykjavik', '1987-11-23', '04:17', 'Atlantic', '64.14', '21.94'];
$flSrv = ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/'];
$flOrder = function (string $html, array $needles): bool {
    $last = -1;
    foreach ($needles as $n) {
        $p = strpos($html, $n);
        if ($p === false || $p <= $last) {
            return false;
        }
        $last = $p;
    }
    return true;
};

// ---- request ----
check('flow: friends is a mode, Consent version is 5, the old value asks again', function () {
    same(in_array('friends', Request::MODES, true), true);
    same(Request::mode(['mode' => 'friends']), 'friends');
    same(Request::mode(['mode' => 'nope']), null);
    same(Consent::VALUE, '5');
    same(Consent::given(['magic_terms' => '3']), false);
    same(Consent::given(['magic_terms' => '5']), true);
    same(Consent::given(['magic_terms' => '4']), false);
});
check('flow: nickname rules', function () {
    same(Request::nickname([]), ['nick' => null, 'invalid' => false]);
    same(Request::nickname(['nick' => '']), ['nick' => null, 'invalid' => false]);
    same(Request::nickname(['nick' => '   ']), ['nick' => null, 'invalid' => false]);
    same(Request::nickname(['nick' => ' Bea ']), ['nick' => 'Bea', 'invalid' => false]);
    same(Request::nickname(['nick' => 'Zoë Ålvarez']), ['nick' => 'Zoë Ålvarez', 'invalid' => false]);
    same(Request::nickname(['nick' => str_repeat('é', 40)])['invalid'], false);
    same(Request::nickname(['nick' => str_repeat('a', 41)]), ['nick' => null, 'invalid' => true]);
    same(Request::nickname(['nick' => "Be\x07a"]), ['nick' => null, 'invalid' => true]);
    same(Request::nickname(['nick' => '1234']), ['nick' => null, 'invalid' => true]);
    same(Request::nickname(['nick' => ['x']]), ['nick' => null, 'invalid' => true]);
    same(Request::nickname(['nick' => "\xff\xfe"]), ['nick' => null, 'invalid' => true]);
    same(Request::nickname(['nick' => '<script>x</script>'])['nick'], '<script>x</script>');
});

// ---- chooser, locks, Self ----
check('flow: no Menu; chooser has three cards in order, Soul Affinity and Friends locked with both texts', function () use ($sharePage, $flOrder) {
    foreach ([[], ['mode' => 'self'], ['mode' => 'love'], ['mode' => 'friends']] as $get) {
        [$out] = $sharePage($get);
        same(str_contains($out, 'class="menu') || str_contains($out, 'data-menu') || str_contains($out, 'Share my Self Discovery hidden data') || str_contains($out, 'Clean browser data'), false);
        same($flOrder($out, ['<strong>Self Discovery</strong>', '<strong>Soul Affinity</strong>', '<strong>Friends hidden codes</strong>']), true);
        same(substr_count($out, 'data-needs-self'), 2);
        same(substr_count($out, 'class="chooser__card is-locked"') + substr_count($out, 'class="chooser__card is-active is-locked"'), 2);
        same(substr_count($out, 'aria-disabled="true" data-needs-self'), 2);
        same(substr_count($out, 'Self Discovery fields are required to unlock this section.') >= 2, true);
        same(str_contains($out, 'Name affinity, biorhythm synchrony, common signs and a tarot spread.') && str_contains($out, 'Hidden codes your friends shared with you.'), true);
        same(preg_match('/<span data-unlock-text hidden>/', $out), 1);
        same(preg_match('/href="\?mode=self"[^>]* aria-disabled="true" data-needs-self/', $out), 1);
    }
    [$out] = $sharePage([]);
    same(str_contains($out, 'Start here'), true);
    [$out] = $sharePage(['mode' => 'self']);
    same(str_contains($out, 'Start here'), false);
    same(str_contains($out, '<strong>Love</strong>'), false);
});
check('flow: Self form has half-width fields and the four buttons in order with their hooks', function () use ($sharePage, $flOrder) {
    [$out] = $sharePage(['mode' => 'self']);
    same($flOrder($out, ['class="split__main"', 'data-self-actions', 'data-self-hidden-code', 'data-self-clear', 'data-self-reveal', 'data-self-save']), true);
    same($flOrder($out, ['>Generate hidden data code<', '>Clear data<', '>Reveal my sky<', '>Save the data<']), true);
    same(preg_match('/<button type="button" class="is-empty" aria-disabled="true" data-self-hidden-code title="[^"]+">/', $out), 1);
    same(preg_match('/<button type="button" class="is-empty" aria-disabled="true" data-self-clear title="[^"]+">/', $out), 1);
    same(preg_match('/<button type="submit" class="btn--primary" data-self-reveal title="[^"]+">/', $out), 1);
    same(preg_match('/<button type="button" data-self-save hidden title="[^"]+">/', $out), 1);
    same(str_contains($out, 'Your details are remembered in this browser only.'), true);
    same(substr_count($out, '<dialog class="confirm no-print" data-confirm'), 1);
    same(str_contains($out, 'class="btn--primary" data-confirm-yes>Yes, continue') && str_contains($out, 'data-confirm-no>Cancel'), true);
    same(preg_match('/\sstyle=|<script(?![^>]*\ssrc=)|\son[a-z]+=/i', $out), 0);
});
check('flow: a Self result unlocks the other sections and links Soul Affinity with the validated details', function () use ($sharePage, $flSelf) {
    [$out] = $sharePage($flSelf + ['mode' => 'self', 'name' => 'Ann', 'on' => '2026-10-09', 'noaudit' => '']);
    same(substr_count($out, 'chooser__card is-locked'), 0);
    same(substr_count($out, 'aria-disabled="true" data-needs-self'), 0);
    preg_match('/href="(\?mode=love[^"]*)"/', $out, $m);
    parse_str(ltrim(html_entity_decode($m[1]), '?'), $q);
    same([$q['a_name'], $q['a_date'], $q['a_time'], $q['a_city'], $q['a_tz'], isset($q['noaudit'])], ['Ann', '1990-07-15', '08:30', 'Rome', 'Europe/Rome', true]);
    [$out2] = $sharePage($q + ['b_name' => 'Bo']);
    same(str_contains($out2, 'id="name-h"'), true);
    // Without a Self name the link uses a neutral one so the other section accepts it.
    [$out] = $sharePage($flSelf + ['mode' => 'self', 'on' => '2026-10-09', 'noaudit' => '']);
    same(str_contains($out, 'a_name=Me'), true);
});

// ---- Soul Affinity ----
check('flow: Soul Affinity page: no "You" part, hidden carried inputs, two legends, locked without details', function () use ($sharePage, $flA) {
    [$out] = $sharePage(['mode' => 'love']);
    same(str_contains($out, '<legend>You</legend>'), false);
    same(str_contains($out, 'name="a_name" type="text"') || str_contains($out, 'name="a_date" type="date"'), false);
    same(substr_count($out, 'type="hidden" name="a_'), 11);
    same(str_contains($out, 'data-carried hidden'), true);
    same(str_contains($out, '<legend>Other soul\'s info</legend>') || str_contains($out, "<legend>Other soul&#039;s info</legend>"), true);
    same(str_contains($out, '<legend>Import from a user</legend>'), true);
    same(str_contains($out, 'The person you love'), false);
    same(preg_match('/data-love-locked>/', $out), 1);
    same(preg_match('/data-love-body hidden>/', $out), 1);
    same(str_contains($out, 'Go to Self Discovery'), true);
    [$out] = $sharePage($flA + ['mode' => 'love', 'b_name' => 'Bo', 'on' => '2026-10-09', 'noaudit' => '']);
    same(preg_match('/data-love-locked hidden>/', $out), 1);
    same(preg_match('/data-love-body>/', $out), 1);
    same(str_contains($out, '<input type="hidden" name="a_name" value="Ann">'), true);
    same(str_contains($out, 'id="name-h"') && str_contains($out, 'Share this reading'), true);
    same(substr_count($out, 'aria-disabled="true" data-needs-self'), 0);
});
check('flow: Soul Affinity results are the old Love results (same view model)', function () use ($sharePage, $flA) {
    $get = $flA + ['mode' => 'love', 'b_name' => 'Silvia', 'b_date' => '1991-03-02', 'on' => '2026-10-09', 'noaudit' => ''];
    [$out] = $sharePage($get);
    $p = Request::parseLove($get, new Magic\Geo\Geocoder(sys_get_temp_dir()));
    $view = LoveReading::build($p['a'], $p['b'], '2026-10-09');
    same($view['names'], ['a' => 'Ann', 'b' => 'Silvia']);
    same(str_contains($out, 'Ann &amp; Silvia'), true);
    same(str_contains($out, (string) $view['affinity']['percent']), true);
});

// ---- nickname and hidden codes ----
check('flow: hidden request with a nickname: label is the nickname, nothing of the shared person is rendered; escaping', function () use ($sharePage, $flA, $flCode, $flSecrets, $flSrv) {
    $get = $flA + ['mode' => 'love', 'h' => $flCode, 'nick' => 'Bea', 'on' => '2026-10-09', 'noaudit' => ''];
    [$out] = $sharePage($get, $flSrv);
    foreach ($flSecrets as $s) {
        same([$s, str_contains($out, $s)], [$s, false]);
    }
    same(str_contains($out, '<strong>Bea</strong>'), true);
    same(str_contains($out, '<strong>Your match</strong>'), false);
    same(str_contains(html_entity_decode(strip_tags($out)), 'Ann & Bea'), true);
    same(str_contains($out, '<input type="hidden" name="nick" value="Bea">'), true);
    same(preg_match('/Share this reading|share-link|\?c=/', $out), 0);
    [$plain] = $sharePage(array_diff_key($get, ['nick' => 1]), $flSrv);
    same(str_contains($plain, '<strong>Your match</strong>'), true);
    same(str_contains($plain, 'name="nick" value'), false);
    [$xss] = $sharePage(['nick' => '<script>alert(1)</script>'] + $get, $flSrv);
    same(str_contains($xss, '<script>alert'), false);
    same(str_contains($xss, '&lt;script&gt;alert(1)&lt;/script&gt;'), true);
    [$bad] = $sharePage(['nick' => "x\x07"] + $get, $flSrv);
    same(str_contains($bad, 'The nickname was not valid, so it was ignored.') && str_contains($bad, '<strong>Your match</strong>'), true);
    // A nickname without a hidden person has no effect.
    [$none] = $sharePage($flA + ['mode' => 'love', 'b_name' => 'Silvia', 'nick' => 'Bea', 'on' => '2026-10-09', 'noaudit' => ''], $flSrv);
    same(str_contains($none, 'Ann &amp; Silvia'), true);
    same(str_contains($none, '>Bea<') || str_contains($none, 'value="Bea"'), false);
});
check('flow: the nickname is not in the audit record or its YAML', function () use ($flA) {
    $geo = new Magic\Geo\Geocoder(sys_get_temp_dir());
    $hp = Magic\Share\ShareCode::decodeHidden('MApaZXJiaW5ldHRhsC6EBdZl4HiXGCUEqTK8tbUwuzS1gA');
    $q = $flA + ['mode' => 'love', 'on' => '2026-10-09'] + array_combine(array_map(fn ($k) => 'b_' . $k, array_keys($hp)), array_values($hp));
    $p = Request::parseLove($q, $geo);
    $b = ['label' => 'Quillon', 'anonymous' => false] + $p['b'];
    $view = LoveReading::build($p['a'], $b, '2026-10-09');
    $rec = AuditRecord::fromLove($p['a'], $b, $view, '2026-10-09');
    same(str_contains(json_encode($rec), 'Quillon'), false);
    same(str_contains($rec['response_yaml'], 'Quillon'), false);
});
check('flow: Self with a pending hidden code: banner, hidden h, nickname field; results, share links and codes never carry them', function () use ($sharePage, $flSelf, $flCode, $flSrv) {
    [$out] = $sharePage(['mode' => 'self', 'h' => $flCode, 'nick' => 'Bea']);
    same(str_contains($out, 'data-pending-friend'), true);
    same(str_contains($out, 'A friend shared hidden data with you. Fill in Self Discovery and press Reveal my sky or Save the data: it will be added to Friends hidden codes.'), true);
    same(str_contains($out, '<input type="hidden" name="h" value="' . $flCode . '">'), true);
    same(preg_match('/name="nick" type="text" maxlength="40" autocomplete="off" value="Bea"/', $out), 1);
    [$res] = $sharePage($flSelf + ['mode' => 'self', 'h' => $flCode, 'nick' => 'Bea', 'on' => '2026-10-09', 'noaudit' => ''], $flSrv);
    same(str_contains($res, 'Share this reading'), false);
    preg_match_all('/(?:value|href)="([^"]*(?:\?|&amp;)(?:c|mode)=[^"]*)"/', $res, $links);
    same(count($links[1]) > 0, true);
    foreach ($links[1] as $l) {
        same(str_contains($l, 'h=' . $flCode) || str_contains($l, 'nick=') , false);
    }
    preg_match_all('/\?c=([A-Za-z0-9_-]+)/', $res, $codes);
    foreach ($codes[1] as $c) {
        $d = Magic\Share\ShareCode::decode($c);
        same($d !== null && !isset($d['h']) && !isset($d['nick']), true);
    }
    [$plain] = $sharePage($flSelf + ['mode' => 'self', 'on' => '2026-10-09', 'noaudit' => ''], $flSrv);
    $strip = fn (string $h): string => preg_replace('/<form id="birth-form".*?<\/form>/s', '', $h);
    [$res2] = $sharePage($flSelf + ['mode' => 'self', 'h' => $flCode, 'nick' => 'Bea', 'on' => '2026-10-09', 'noaudit' => ''], $flSrv);
    same(str_contains($strip($res2), $flCode), false);
    // The audit record of a Self result is built from the model only.
    [, , $log] = $sharePage($flSelf + ['mode' => 'self', 'h' => $flCode, 'nick' => 'Bea', 'on' => '2026-10-09']);
    same(str_contains($log, 'audit: write failed'), true);
    // An invalid code gives the note and no banner.
    [$bad] = $sharePage(['mode' => 'self', 'h' => 'garbage']);
    same(str_contains($bad, 'The hidden details in this link are not valid, so they were ignored.'), true);
    same(str_contains($bad, 'data-pending-friend'), false);
});
check('flow: landing from a hidden link: the gate keeps the query; afterwards Soul Affinity is locked and points to Self Discovery with the code', function () use ($sharePage, $flCode) {
    $qs = 'h=' . $flCode . '&nick=Bea';
    [$gate] = $sharePage(['h' => $flCode, 'nick' => 'Bea'], ['QUERY_STRING' => $qs, 'REQUEST_URI' => '/?' . $qs], false);
    same(str_contains($gate, 'class="gate"') && str_contains($gate, 'name="next" value="' . htmlspecialchars($qs, ENT_QUOTES) . '"'), true);
    same(Magic\Consent::safeQuery($qs), $qs);
    same(preg_match('/Zerbinetta|Reykjavik/', $gate), 0);
    [$out] = $sharePage(['h' => $flCode, 'nick' => 'Bea']);
    same(preg_match('/data-love-locked>/', $out), 1);
    same(str_contains($out, 'href="?mode=self&amp;h=' . $flCode . '&amp;nick=Bea"'), true);
    same(str_contains($out, 'class="tarot__card '), false);
});

// ---- Friends ----
check('flow: friends page builds no result and no audit; list structure, search, bulk bar, row template, noscript', function () use ($sharePage, $flOrder) {
    [$out, $headers, $log] = $sharePage(['mode' => 'friends']);
    same($log, '');
    same(str_contains($out, 'class="results"') && str_contains($out, 'id="results"'), true);
    same(str_contains($out, 'class="tarot__card '), false);
    same(str_contains($out, 'data-friends-search') && str_contains($out, 'data-friends-count') && str_contains($out, 'data-friends-list'), true);
    same(str_contains($out, 'data-friends-selectall') && str_contains($out, 'data-friends-remove-selected'), true);
    same(preg_match('/<template data-friends-row>.*data-friend-nick.*data-friend-compare.*data-friend-remove.*<\/template>/s', $out), 1);
    same(str_contains($out, '<noscript>'), true);
    same(str_contains($out, 'No hidden codes yet. When a friend shares a hidden link or QR code with you, add it in Soul Affinity (Import from a user) and it appears here.'), true);
    same(preg_match('/data-friends-locked>/', $out), 1);
    same(preg_match('/data-friends-body hidden>/', $out), 1);
    same(str_contains($out, 'X-Robots-Tag: noindex'), false);
    same(substr_count($out, '<dialog'), 1);
    same(preg_match('/\sstyle=|<script(?![^>]*\ssrc=)|\son[a-z]+=/i', $out), 0);
});

// ---- help, CSS, scripts ----
check('flow: help keys of this feature exist, old menu keys are gone', function () {
    same(isset(Help::TEXT['field.nick']), true);
    foreach (['self.hidden_code', 'self.clear', 'self.save', 'self.reveal', 'friends.list', 'friends.search', 'love.import'] as $k) {
        same(isset(Help::TEXT[$k]), false);
    }
    same(isset(Help::TEXT['menu.clean']) || isset(Help::TEXT['menu.share_hidden']), false);
});
check('flow: CSS has the locked card, the split layout that stacks under 720px, no menu rules, readable greyed text', function () use ($flCss, $uiRatio) {
    same(str_contains($flCss, '.menu {') || str_contains($flCss, '.menu__'), false);
    same(preg_match('/\.chooser__card\.is-locked \{[^}]*background: (#[0-9a-f]{6})/', $flCss, $bg), 1);
    same($bg[1] !== '#17153a', true);
    same(preg_match('/\.chooser__card\.is-locked span \{[^}]*color: (#[0-9a-f]{6})/', $flCss, $fg), 1);
    same($uiRatio($fg[1], $bg[1]) >= 3, true);
    same(preg_match('/@media \(min-width: 720px\) \{[^@]*\.split \{ grid-template-columns: 1fr 1fr/', $flCss), 1);
    same(preg_match('/\n\.split \{ display: grid; gap: 16px; \}/', $flCss), 1);
    same(str_contains($flCss, 'dialog.confirm') && str_contains($flCss, '.friends__row'), true);
});
check('flow: memory.js keeps the friends rules; only memory.js touches storage; share.js keeps its single fetch', function () use ($flRoot) {
    $mem = (string) file_get_contents($flRoot . '/public/assets/memory.js');
    foreach (['data-confirm', 'data-friends-row', 'data-self-clear', 'data-carried', 'data-pending-friend', 'extractCode', 'confirmBox("Remove hidden codes?"'] as $n) {
        same([$n, str_contains($mem, $n)], [$n, true]);
    }
    same(str_contains($mem, 'Changing your own data erases all previous data in this browser, including the hidden data shared by friends. Continue?'), true);
    same(str_contains($mem, 'This erases everything this browser remembers'), true);
    foreach (glob($flRoot . '/public/assets/*.js') as $f) {
        if (basename($f) !== 'memory.js') {
            $js = (string) file_get_contents($f);
            same([basename($f), str_contains($js, 'localStorage') || str_contains($js, 'sessionStorage')], [basename($f), false]);
        }
    }
    // Every Friends read/write goes through the validated helpers.
    same(preg_match_all('/rawSet\(KEY_FRIENDS/', $mem), 1);
    same(preg_match_all('/rawGet\(KEY_FRIENDS/', $mem), 1);
});

check('flow: memory.js hardening: strict hidden shape, handled-once friend, no-storage notices, focus after removal', function () use ($flRoot, $flCode, $sharePage) {
    $mem = (string) file_get_contents($flRoot . '/public/assets/memory.js');
    foreach (['hasFriend(c)', 'FRIEND_FLAG', 'Friend added to Friends hidden codes.', 'NO_STORE_TEXT', 'search.focus()', '[data-nostore]', '/^M[A-P][A-Za-z0-9_-]{6,398}$/', '[\\u0300-\\u036f]'] as $n) {
        same([$n, str_contains($mem, $n)], [$n, true]);
    }
    same(preg_match('/[\x{0300}-\x{036f}]/u', $mem), 0);
    // The strict shape accepts the server's own hidden codes.
    same(preg_match('/^M[A-P][A-Za-z0-9_-]{6,398}$/', $flCode), 1);
    same(Magic\Share\ShareCode::decodeHidden($flCode) !== null, true);
    foreach (['love', 'friends'] as $m) {
        [$out] = $sharePage(['mode' => $m]);
        same(str_contains($out, 'data-nostore hidden'), true);
    }
});

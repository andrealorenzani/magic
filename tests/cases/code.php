<?php
declare(strict_types=1);

use Magic\Consent;
use Magic\Geo\Geocoder;
use Magic\LoveReading;
use Magic\Request;
use Magic\SelfReading;
use Magic\Share\Qr;
use Magic\Share\ShareCode;
use Magic\Share\ShareLink;
use Magic\Share\TimeZoneTable;
use Magic\Time\Zone;

$codeGeo = new Geocoder(sys_get_temp_dir());
$codeToday = '2026-10-09';
$selfBase = ['mode' => 'self', 'date' => '1990-07-15', 'time' => '08:30', 'city' => 'Rome, Lazio, Italy', 'lat' => '41.9028', 'lon' => '12.4964', 'tz' => 'Europe/Rome'];
$loveBase = ['mode' => 'love', 'a_name' => 'Ann', 'a_date' => '1990-07-15', 'a_time' => '08:30', 'a_city' => 'Rome, Lazio, Italy', 'a_lat' => '41.9028', 'a_lon' => '12.4964', 'a_tz' => 'Europe/Rome',
    'b_name' => 'Silvia', 'b_date' => '1991-03-02'];
$bFull = ['b_time' => '17:45', 'b_city' => 'Milan, Lombardy, Italy', 'b_lat' => '45.46427', 'b_lon' => '9.18951', 'b_tz' => 'Europe/Rome'];
$aPos = ['a_pos_city' => 'London, England, United Kingdom', 'a_pos_lat' => '51.50853', 'a_pos_lon' => '-0.12574', 'a_pos_tz' => 'Europe/London'];
$bPos = ['b_pos_city' => 'New York, New York, USA', 'b_pos_lat' => '40.71427', 'b_pos_lon' => '-74.00597', 'b_pos_tz' => 'America/New_York'];
$sPos = ['pos_city' => 'Milan, Lombardy, Italy', 'pos_lat' => '45.46427', 'pos_lon' => '9.18951', 'pos_tz' => 'Europe/Rome'];

$codeSelf = fn (array $get) => Request::parse($get, $codeGeo)['input'];
$codeLove = function (array $get) use ($codeGeo): array {
    $r = Request::parseLove($get, $codeGeo);
    if ($r['a'] === null || $r['b'] === null) {
        throw new RuntimeException('fixture invalid: ' . implode('; ', $r['errors']));
    }
    return [$r['a'], $r['b']];
};
$codeSpread = fn (array $slots) => \Magic\Tarot\Reading::fromSlots($slots);
$bits = fn (int $v, int $n): string => str_pad(decbin($v), $n, '0', STR_PAD_LEFT);
$toCode = function (string $b): string {
    $b .= str_repeat('0', (8 - strlen($b) % 8) % 8);
    $bytes = '';
    foreach (str_split($b, 8) as $byte) {
        $bytes .= chr((int) bindec($byte));
    }
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
};
$txt = fn (string $s): string => implode('', array_map(fn ($c) => str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT), str_split($s)));
// A hand-made valid Self code body (after the header) with replaceable parts.
$selfBits = function (array $o = []) use ($bits, $txt): string {
    $o += ['header' => $bits(1, 4) . '0' . '0' . '0' . '0', 'date' => $bits(361785, 19), 'time' => $bits(510, 11), 'lat' => $bits(13190280, 25), 'lon' => $bits(19249640, 26),
        'tz' => $bits(TimeZoneTable::indexOf('Europe/Rome'), 10), 'label' => $bits(4, 6) . $txt('Rome'), 'now' => '0', 'pre' => ''];
    return $o['header'] . $o['pre'] . $o['date'] . $o['time'] . $o['lat'] . $o['lon'] . $o['tz'] . $o['label'] . $o['now'];
};

// ---- headers and goldens ----
check('code: first characters (version, mode, noaudit, on, tarot flags)', function () use ($codeSelf, $codeLove, $selfBase, $loveBase, $codeSpread, $codeToday) {
    $in = $codeSelf($selfBase);
    [$a, $b] = $codeLove($loveBase);
    $spread = $codeSpread([['number' => 16, 'reversed' => false], ['number' => 5, 'reversed' => true], ['number' => 9, 'reversed' => false]]);
    same(ShareCode::encodeSelf($in, $codeToday, true)[0], 'F');
    same(ShareCode::encodeSelf($in, $codeToday, true, false)[0], 'E');
    same(ShareCode::encodeSelf($in, $codeToday, false, false)[0], 'E');
    same(ShareCode::encodeLove($a, $b, $spread, $codeToday, true)[0], 'H');
    same(ShareCode::encodeLove($a, $b, $spread, $codeToday, true, false)[0], 'G');
});
check('code: golden codes for a typical Self and Love', function () use ($codeSelf, $codeLove, $selfBase, $loveBase, $codeSpread, $codeToday) {
    same(ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true), 'FlpvWFOT_MlEiEluehXUlJvbWUsIExhemlvLCBJdGFseQA');
    [$a, $b] = $codeLove($loveBase);
    $spread = $codeSpread([['number' => 16, 'reversed' => false], ['number' => 5, 'reversed' => true], ['number' => 9, 'reversed' => false]]);
    $c = ShareCode::encodeLove($a, $b, $spread, $codeToday, true);
    same(ShareCode::decode($c)['t'], '16u,5r,9u');
    same(ShareCode::decode($c)['on'], $codeToday);
    same(ShareCode::encodeLove($a, $b, $spread, $codeToday, true), $c);
});
check('code: dates, times and coordinates keep their exact value at the edges', function () use ($codeSelf, $selfBase, $codeToday) {
    foreach (['1000-01-01', '1000-02-28', '1600-02-29', '1999-12-31', '2000-01-01', '2000-02-29', '2100-12-31'] as $d) {
        foreach (['00:00', '23:59', '12:00'] as $t) {
            $in = $codeSelf(['date' => $d, 'time' => $t] + $selfBase);
            $dec = ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, true));
            same([$dec['date'], $dec['time']], [$d, $t]);
        }
    }
    foreach ([['-90', '-180'], ['90', '180'], ['0', '0'], ['-0.004', '0.004'], ['41.90281', '-12.49639'], ['-33.86785', '151.20732']] as [$la, $lo]) {
        $in = $codeSelf(['lat' => $la, 'lon' => $lo] + $selfBase);
        $dec = ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, false));
        same([(float) $dec['lat'], (float) $dec['lon']], [$in['lat'], $in['lon']]);
        same($codeSelf($dec)['lat'], $in['lat']);
        same($codeSelf($dec)['lon'], $in['lon']);
    }
    $in = $codeSelf(['lat' => '0', 'lon' => '-0.000001'] + $selfBase);
    same(ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, false))['lon'], '0.00000');
});
check('code: Self round trip gives the same input and the same reading', function () use ($codeSelf, $selfBase, $sPos, $codeToday) {
    foreach ([$selfBase, $selfBase + $sPos] as $get) {
        $in = $codeSelf($get);
        foreach ([true, false] as $frozen) {
            $dec = ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, $frozen));
            same(isset($dec['on']), $frozen);
            same($codeSelf($dec), $in);
            same(SelfReading::build($codeSelf($dec), $codeToday), SelfReading::build($in, $codeToday));
        }
    }
});
check('code: Love round trip for every combination of optional parts', function () use ($codeLove, $loveBase, $bFull, $aPos, $bPos, $codeSpread, $codeToday) {
    $spread = $codeSpread([['number' => 21, 'reversed' => true], ['number' => 0, 'reversed' => false], ['number' => 7, 'reversed' => true]]);
    $bOnlyName = ['b_name' => 'Silvia'];
    $variants = [
        $loveBase,
        array_diff_key($loveBase, ['b_date' => 1]),
        $loveBase + $bFull,
        $loveBase + $aPos,
        $loveBase + $bPos,
        $loveBase + $bFull + $aPos + $bPos,
        ['b_name' => 'Zoë "Z" 🙂 & =#%'] + $loveBase,
        ['a_name' => 'A&B=C #1 %', 'b_name' => str_repeat('𝒜', 40)] + $loveBase,
        ['a_name' => 'José Ñandú', 'b_name' => 'Åsa'] + $loveBase + $bFull,
    ];
    foreach ($variants as $i => $get) {
        [$a, $b] = $codeLove($get);
        foreach ([[true, $spread], [false, null], [true, null]] as [$frozen, $sp]) {
            $c = ShareCode::encodeLove($a, $b, $sp, $codeToday, $frozen);
            $dec = ShareCode::decode($c);
            if ($dec === null) {
                throw new RuntimeException("variant $i does not decode");
            }
            $r = Request::parseLove($dec, new Geocoder(sys_get_temp_dir()));
            same([$r['a'], $r['b']], [$a, $b]);
            same(isset($dec['t']), $frozen && $sp !== null);
            same(LoveReading::build($r['a'], $r['b'], $codeToday, null), LoveReading::build($a, $b, $codeToday, null));
        }
    }
});
check('code: tarot numbers 0-77 are carried; slots keep order and orientation', function () use ($codeLove, $loveBase, $codeToday) {
    [$a, $b] = $codeLove($loveBase);
    $mk = fn (array $n) => array_map(fn ($x) => ['card' => ['number' => $x[0]], 'reversed' => $x[1]], $n);
    foreach ([[[77, true], [22, false], [0, true]], [[1, false], [63, true], [64, false]]] as $slots) {
        $dec = ShareCode::decode(ShareCode::encodeLove($a, $b, $mk($slots), $codeToday, true));
        same($dec['t'], implode(',', array_map(fn ($s) => $s[0] . ($s[1] ? 'r' : 'u'), $slots)));
    }
});
check('code: long labels are cut on a character boundary', function () use ($codeSelf, $selfBase, $codeToday) {
    $label = 'Llanfairpwllgwyngyll, Isle of Anglesey, Wales';
    $in = $codeSelf(['city' => $label] + $selfBase);
    $city = ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, true))['city'];
    same(strlen($city) <= 32 && str_starts_with($label, $city), true);
    $in = $codeSelf(['city' => str_repeat('é', 40)] + $selfBase);
    $city = ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, true))['city'];
    same($city, str_repeat('é', 16));
    $in = $codeSelf(['city' => str_repeat('日', 20)] + $selfBase);
    same(ShareCode::decode(ShareCode::encodeSelf($in, $codeToday, true))['city'], str_repeat('日', 10));
});
check('code: names are length-prefixed UTF-8 bytes, 40 four-byte characters fit', function () use ($codeLove, $loveBase, $codeToday) {
    [$a, $b] = $codeLove(['a_name' => str_repeat('𝒜', 40)] + $loveBase);
    $dec = ShareCode::decode(ShareCode::encodeLove($a, $b, null, $codeToday, false));
    same($dec['a_name'], str_repeat('𝒜', 40));
});

// ---- time zone table ----
check('code: time zone table is pinned (append-only) and usable', function () {
    $z = TimeZoneTable::ZONES;
    same(count($z) < 1023, true);
    same(count($z), count(array_unique($z)));
    same([$z[0], $z[count($z) - 1]], ['Africa/Abidjan', 'UTC']);
    same(TimeZoneTable::indexOf('Europe/Rome'), array_search('Europe/Rome', $z, true));
    same(TimeZoneTable::at(count($z)), null);
    $checkpoints = [419 => 'c842ed68d0827c1e7c118b7bcb735f81c968a2ff'];
    foreach ($checkpoints as $count => $sha) {
        same(sha1(implode("\n", array_slice($z, 0, $count))), $sha);
    }
    same(array_keys($checkpoints) === [419] && count($z) >= 419, true);
});
check('code: the machine tz database knows the table zones (legacy names listed)', function () {
    $legacy = [];
    $missing = array_values(array_filter(TimeZoneTable::ZONES, fn ($t) => !Zone::isValid($t) && !in_array($t, $legacy, true)));
    same($missing, []);
});
check('code: a zone outside the table uses the literal form and an unknown one is rejected on decode', function () use ($codeSelf, $selfBase, $codeToday) {
    $in = $codeSelf($selfBase);
    $in['tz'] = 'Mars/Olympus_Mons';
    $c = ShareCode::encodeSelf($in, $codeToday, true);
    same(strlen($c) > strlen(ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true)), true);
    same(ShareCode::decode($c), null);
    $in['tz'] = 'Bad Zone!';
    try {
        ShareCode::encodeSelf($in, $codeToday, true);
        throw new RuntimeException('expected exception');
    } catch (InvalidArgumentException) {
    }
});

// ---- invalid codes ----
check('code: garbage, wrong alphabet, padding, length and version are rejected', function () use ($codeSelf, $selfBase, $codeToday, $selfBits, $toCode, $bits) {
    $good = ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true);
    foreach (['', '=', 'A', str_repeat('A', 401), $good . '=', $good . '!', 'F lp', $good . "\n", "😀", str_repeat('F', 399), '../..'] as $bad) {
        same(ShareCode::decode($bad), null);
    }
    same(ShareCode::decode($toCode($selfBits())) !== null, true);
    foreach ([0, 2, 15] as $v) {
        same(ShareCode::decode($toCode($selfBits(['header' => $bits($v, 4) . '0000']))), null);
    }
});
check('code: truncated at every byte and with extra bytes or bits', function () use ($codeSelf, $codeLove, $selfBase, $loveBase, $bFull, $codeToday) {
    [$a, $b] = $codeLove($loveBase + $bFull);
    foreach ([ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true), ShareCode::encodeLove($a, $b, null, $codeToday, false)] as $good) {
        same(ShareCode::decode($good) !== null, true);
        for ($i = 0; $i < strlen($good); $i++) {
            same(ShareCode::decode(substr($good, 0, $i)), null);
        }
        foreach (['A', 'AA', 'AAAA', '_', 'g'] as $extra) {
            same(ShareCode::decode($good . $extra), null);
        }
        // Flipping the unused bits of the last character is not canonical.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $last = strpos($alphabet, $good[-1]);
        $pad = strlen($good) * 6 - intdiv(strlen($good) * 6, 8) * 8;
        for ($bit = 0; $bit < $pad; $bit++) {
            $alt = $good;
            $alt[-1] = $alphabet[$last ^ (1 << $bit)];
            if ($alt !== $good && ShareCode::decode($alt) !== null) {
                throw new RuntimeException("non-canonical code accepted (bit $bit)");
            }
        }
    }
});
check('code: out-of-range and malformed fields are rejected', function () use ($selfBits, $toCode, $bits, $txt) {
    $ok = fn (string $b) => ShareCode::decode($toCode($b));
    same($ok($selfBits()) !== null, true);
    same($ok($selfBits(['date' => $bits(402132, 19)])), null);
    same($ok($selfBits(['time' => $bits(1440, 11)])), null);
    same($ok($selfBits(['lat' => $bits(18000001, 25)])), null);
    same($ok($selfBits(['lon' => $bits(36000001, 26)])), null);
    same($ok($selfBits(['tz' => $bits(1022, 10)])), null);
    same($ok($selfBits(['tz' => $bits(1023, 10) . $bits(3, 6) . $txt('a!b')])), null);
    same($ok($selfBits(['tz' => $bits(1023, 10) . $bits(0, 6)])), null);
    same($ok($selfBits(['label' => $bits(0, 6)])), null);
    same($ok($selfBits(['label' => $bits(33, 6) . $txt(str_repeat('a', 33))])), null);
    same($ok($selfBits(['label' => $bits(4, 6) . $txt("Ro\x07e")])), null);
    same($ok($selfBits(['label' => $bits(2, 6) . $txt("\xC3\x28")])), null);
    same($ok($selfBits(['label' => $bits(5, 6) . $txt('Rome ')])), null);
    same($ok($selfBits(['pre' => $bits(100, 17), 'header' => $bits(1, 4) . '0010'])) !== null, true);
    same($ok($selfBits(['pre' => $bits(73414, 17), 'header' => $bits(1, 4) . '0010'])), null);
    same($ok($selfBits(['header' => $bits(1, 4) . '0001'])), null); // tarot flag in Self
    same($ok($selfBits() . '1'), null); // trailing non-zero bit
    same($ok($selfBits() . '00000000'), null); // trailing whole byte
});
check('code: Love field rules (names, tarot, B flags)', function () use ($toCode, $bits, $txt) {
    $person = fn (string $name, string $rest) => $bits(strlen($name), 8) . $txt($name) . $rest;
    $a = fn (string $name = 'Ann') => $person($name, $bits(361785, 19) . $bits(510, 11) . $bits(13190280, 25) . $bits(19249640, 26) . $bits(TimeZoneTable::indexOf('Europe/Rome'), 10) . $bits(4, 6) . $txt('Rome') . '0');
    $b = fn (string $name = 'Bo', string $flags = '0') => $person($name, $flags . '0');
    $head = fn (string $flags = '000') => $bits(1, 4) . '1' . $flags;
    $dec = fn (string $s) => ShareCode::decode($toCode($s));
    same($dec($head() . $a() . $b()) !== null, true);
    same($dec($head() . $a('') . $b()), null);
    same($dec($head() . $a() . $b('')), null);
    same($dec($head() . $a(str_repeat('a', 41)) . $b()), null);
    same($dec($head() . $a('12345') . $b('99')), null); // no letter
    same($dec($head() . $a(' Ann') . $b()), null);
    same($dec($head() . $a("A\x01n") . $b()), null);
    same($dec($head() . $a("\xff\xfe") . $b()), null);
    $cards = fn (array $c) => implode('', array_map(fn ($x) => $bits($x, 7) . '0', $c));
    same($dec($head('001') . $cards([1, 2, 3]) . $a() . $b()) !== null, true);
    same($dec($head('001') . $cards([1, 1, 3]) . $a() . $b()), null);
    same($dec($head('001') . $cards([1, 78, 3]) . $a() . $b()), null);
    same($dec($head('001') . $cards([77, 0, 3]) . $a() . $b()) !== null, true);
    // B with a birth date and no time/place is valid; a birth flag without its fields is not.
    same($dec($head() . $a() . $b('Bo', '1' . $bits(361785, 19) . '0')) !== null, true);
    same($dec($head() . $a() . $b('Bo', '1' . $bits(361785, 19) . '1' . $bits(510, 11))), null);
});

// ---- sizes ----
check('code: sizes of typical links (5-decimal places) and QR versions', function () use ($codeSelf, $codeLove, $selfBase, $loveBase, $bFull, $aPos, $bPos, $sPos, $codeSpread, $codeToday) {
    $spread = $codeSpread([['number' => 16, 'reversed' => false], ['number' => 5, 'reversed' => true], ['number' => 9, 'reversed' => false]]);
    $origin = 'https://example.org/?';
    $selfIn = $codeSelf($selfBase + $sPos);
    [$a, $b] = $codeLove($loveBase);
    [$a2, $b2] = $codeLove($loveBase + $bFull + $aPos + $bPos);
    $cases = [
        'self' => [ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true), ShareLink::self($codeSelf($selfBase), $codeToday), 52],
        'self+pos' => [ShareCode::encodeSelf($selfIn, $codeToday, true), ShareLink::self($selfIn, $codeToday), 100],
        'love' => [ShareCode::encodeLove($a, $b, $spread, $codeToday, true), ShareLink::love($a, $b, $spread, $codeToday), 80],
        'love worst' => [ShareCode::encodeLove($a2, $b2, $spread, $codeToday, true), ShareLink::love($a2, $b2, $spread, $codeToday), 260],
    ];
    foreach ($cases as $name => [$code, $long, $max]) {
        $url = $origin . ShareLink::codeQuery($code);
        $qr = Qr::encode($url);
        if (getenv('MAGIC_SIZES')) {
            fwrite(STDERR, sprintf("%-11s code %3d chars, long query %3d, url %3d, QR v%s\n", $name, strlen($code), strlen($long), strlen($url), $qr === null ? 'none' : (string) ((count($qr) - 17) / 4)));
        }
        same(strlen($code) <= $max, true);
        same(strlen($code) < strlen($long) / 2, true);
        same($qr !== null, true);
    }
    [$l] = [$cases['love'][0]];
    same(strlen($l) < 200 && ((count(Qr::encode($origin . 'c=' . $l)) - 17) / 4) <= 7, true);
    same(strlen($cases['love worst'][0]) <= ShareCode::MAX_CHARS, true);
});

// ---- ShareLink ----
check('code: ShareLink::codeQuery and old long links keep their meaning', function () use ($codeGeo, $codeToday) {
    same(ShareLink::codeQuery('abc'), 'c=abc');
    parse_str('mode=self&date=1990-07-15&time=08:30&city=Rome&lat=41.90000&lon=12.50000&tz=Europe/Rome&on=2026-10-09&noaudit', $q);
    $in = Request::parse($q, $codeGeo)['input'];
    same([$in['lat'], $in['lon'], $in['city']], [41.9, 12.5, 'Rome']);
    parse_str('mode=love&a_name=Ann&a_date=1990-07-15&a_time=08:30&a_city=Rome&a_lat=41.9&a_lon=12.5&a_tz=Europe/Rome&b_name=Bo&on=2026-10-09&t=16u,5r,9u&noaudit', $q);
    same(Request::parseTarot($q)['slots'][0]['number'], 16);
    $r = Request::parseLove($q, $codeGeo);
    same([$r['errors'], $r['a']['name'], $r['b']['name']], [[], 'Ann', 'Bo']);
});
check('code: the gate carries c through safeQuery', function () use ($codeSelf, $selfBase, $codeToday) {
    $c = ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true);
    same(Consent::safeQuery('c=' . $c), 'c=' . $c);
});

// ---- pages ----
$resultsOf = function (string $out): string {
    $p = strpos($out, 'id="results"');
    return $p === false ? '' : str_replace(['The cards depend on your names, birth dates and the day: the same reading appears on every reload.', 'These cards come from the link you opened.'], 'CARDS-NOTE', substr($out, $p));
};
check('code: a compact link opens the same result, tarot and share section as the long link', function () use ($sharePage, $resultsOf, $selfGet, $loveGet) {
    foreach ([$selfGet, $loveGet] as $get) {
        [$long] = $sharePage($get + ['noaudit' => ''], ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
        preg_match('/id="share-link"[^>]*value="[^"]*\?c=([^"]*)"/', $long, $m);
        [$short] = $sharePage(['c' => $m[1]], ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
        same($resultsOf($short) === $resultsOf($long) && $resultsOf($long) !== '', true);
        same(str_contains($short, 'tarot__card') || $get === $selfGet, true);
    }
});
check('code: an old long link still opens the result', function () use ($sharePage, $selfGet, $loveGet) {
    foreach ([$selfGet, $loveGet + ['t' => '16u,5r,9u']] as $get) {
        [$out] = $sharePage($get, ['HTTP_HOST' => 'localhost']);
        same(str_contains($out, 'Share this reading'), true);
    }
});
check('code: a bad c gives the page with a note, a valid readable query next to it is used', function () use ($sharePage, $selfGet) {
    foreach (['!!!', 'AAAA', str_repeat('A', 500), 'F'] as $bad) {
        [$out] = $sharePage(['c' => $bad]);
        same(str_contains($out, 'The short code in this link is not valid, so it was ignored.') && !str_contains($out, 'Share this reading'), true);
        [$out] = $sharePage($selfGet + ['c' => $bad]);
        same(str_contains($out, 'The short code in this link is not valid') && str_contains($out, 'Share this reading'), true);
    }
    [$out] = $sharePage(['c' => ['x']]);
    same(str_contains($out, 'id="results"'), true);
});
check('code: noaudit flag inside the code skips the audit; without it the write is attempted', function () use ($sharePage, $codeSelf, $selfBase, $codeToday) {
    $in = $codeSelf($selfBase);
    [$out, , $log] = $sharePage(['c' => ShareCode::encodeSelf($in, $codeToday, true, true)]);
    same(str_contains($out, 'Share this reading') && $log === '', true);
    [, , $log] = $sharePage(['c' => ShareCode::encodeSelf($in, $codeToday, true, false)]);
    same(str_contains($log, 'audit: write failed'), true);
});
check('code: without consent a c link shows only the gate', function () use ($sharePage, $codeSelf, $selfBase, $codeToday) {
    $c = ShareCode::encodeSelf($codeSelf($selfBase), $codeToday, true);
    [$out] = $sharePage(['c' => $c], ['QUERY_STRING' => 'c=' . $c], false);
    same(str_contains($out, 'Terms and Conditions') && !str_contains($out, 'Share this reading') && str_contains($out, 'value="c=' . $c . '"'), true);
});
check('code: share section markup, small QR rule, no inline script or style', function () use ($sharePage, $selfGet) {
    [$out] = $sharePage($selfGet, ['HTTP_HOST' => 'localhost:8081', 'REQUEST_URI' => '/']);
    $d = strpos($out, '<details class="share__more">');
    $e = strpos($out, '</details>', (int) $d);
    $qr = strpos($out, '<div class="share__qr" data-qr-copy="share-link">');
    same($d !== false && $e !== false && $qr !== false && ($qr < $d || $qr > $e), true);
    $box = substr($out, (int) $d, $e - (int) $d);
    same(str_contains($box, 'id="share-link"') && str_contains($box, 'Link to a live reading') && !str_contains($box, '<svg'), true);
    same(str_contains(substr($out, (int) $d, 40), ' open'), false);
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/styles.css');
    same(str_contains($css, '.share__qr svg { width: clamp(112px, 38vw, 150px); height: auto; }'), true);
    same(preg_match('/<script(?![^>]*\bsrc=)|\sstyle=|\sonclick=/i', $out), 0);
});

<?php
declare(strict_types=1);

namespace Magic\Share;

/**
 * Pure QR Code encoder (ISO/IEC 18004): byte mode, error-correction level L, versions 1-15,
 * all eight masks evaluated. No I/O.
 */
final class Qr
{
    public const MAX_VERSION = 15;
    public const QUIET = 4;

    /** Per version (index = version): [data codewords per block group 1, blocks 1, data per block group 2, blocks 2, EC codewords per block]. */
    private const BLOCKS = [
        1 => [19, 1, 0, 0, 7], 2 => [34, 1, 0, 0, 10], 3 => [55, 1, 0, 0, 15], 4 => [80, 1, 0, 0, 20],
        5 => [108, 1, 0, 0, 26], 6 => [68, 2, 0, 0, 18], 7 => [78, 2, 0, 0, 20], 8 => [97, 2, 0, 0, 24],
        9 => [116, 2, 0, 0, 30], 10 => [68, 2, 69, 2, 18], 11 => [81, 4, 0, 0, 20], 12 => [92, 2, 93, 2, 24],
        13 => [107, 4, 0, 0, 26], 14 => [115, 3, 116, 1, 30], 15 => [87, 5, 88, 1, 22],
    ];

    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
        9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
        14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70],
    ];

    /** @var array<int,int>|null */
    private static ?array $exp = null;
    /** @var array<int,int>|null */
    private static ?array $log = null;

    /** Bytes that fit at level L in byte mode. */
    public static function capacity(int $version): int
    {
        if ($version < 1 || $version > self::MAX_VERSION) {
            throw new \InvalidArgumentException('version 1..15');
        }
        $data = self::dataCodewords($version);
        $overhead = $version <= 9 ? 12 : 20; // mode (4) + character count (8 or 16) bits
        return intdiv($data * 8 - $overhead, 8);
    }

    /**
     * Modules of the QR code (true = dark), without quiet zone; null when the data is longer than 520 bytes.
     * @param ?int $forceMask only for tests (0-7); normally the best mask is chosen
     * @return ?list<list<bool>>
     */
    public static function encode(string $data, ?int $forceMask = null): ?array
    {
        $len = strlen($data);
        $version = 0;
        for ($v = 1; $v <= self::MAX_VERSION; $v++) {
            if ($len <= self::capacity($v)) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            return null;
        }
        $codewords = self::interleave($version, self::dataCodewordList($version, $data));
        [$base, $isFunction] = self::baseMatrix($version);
        $size = 17 + 4 * $version;

        // Place the codewords in the zigzag order.
        $bits = [];
        foreach ($codewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($cw >> $b) & 1;
            }
        }
        $i = 0;
        $total = count($bits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
                    if (!$isFunction[$y][$x] && $i < $total) {
                        $base[$y][$x] = $bits[$i] === 1;
                        $i++;
                    }
                }
            }
        }

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        $masks = $forceMask === null ? range(0, 7) : [$forceMask];
        foreach ($masks as $mask) {
            $m = self::applyMask($base, $isFunction, $mask);
            self::drawFormat($m, $mask);
            $p = self::penalty($m);
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $best = $m;
            }
        }
        return $best;
    }

    /**
     * SVG path data of the dark modules, including the quiet zone offset: one "M x y h n v1 H x z" rectangle per
     * horizontal run. Digits, letters and spaces only.
     * @param list<list<bool>> $modules
     */
    public static function path(array $modules): string
    {
        $parts = [];
        foreach ($modules as $y => $row) {
            $n = count($row);
            for ($x = 0; $x < $n; $x++) {
                if (!$row[$x]) {
                    continue;
                }
                $start = $x;
                while ($x + 1 < $n && $row[$x + 1]) {
                    $x++;
                }
                $parts[] = 'M' . ($start + self::QUIET) . ' ' . ($y + self::QUIET) . 'h' . ($x - $start + 1) . 'v1H' . ($start + self::QUIET) . 'z';
            }
        }
        return implode('', $parts);
    }

    /** Error-correction codewords for `$data`. @param list<int> $data @return list<int> */
    public static function rsRemainder(array $data, int $ecLen): array
    {
        self::tables();
        $gen = self::generator($ecLen);
        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                foreach ($gen as $i => $g) {
                    $rem[$i] ^= self::mul($g, $factor);
                }
            }
        }
        return $rem;
    }

    /** Format information bits (15 bits) for level L and a mask. */
    public static function formatBits(int $mask): int
    {
        $data = (1 << 3) | $mask; // level L = 01
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        return (($data << 10) | $rem) ^ 0x5412;
    }

    /** Version information bits (18 bits), versions 7 and up. */
    public static function versionBits(int $version): int
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        return ($version << 12) | $rem;
    }

    /**
     * Reads the interleaved codewords back from a finished matrix (the mask is taken from the format bits).
     * Used by tests as a self-check; returns null when the two format copies disagree.
     * @param list<list<bool>> $modules
     * @return ?array{version:int, mask:int, codewords:list<int>}
     */
    public static function readCodewords(array $modules): ?array
    {
        $size = count($modules);
        $version = ($size - 17) / 4;
        if ($version < 1 || $version > self::MAX_VERSION || $version !== (int) $version) {
            return null;
        }
        $version = (int) $version;
        $f1 = 0;
        for ($i = 0; $i <= 5; $i++) {
            $f1 |= ((int) $modules[$i][8]) << $i;
        }
        $f1 |= ((int) $modules[7][8]) << 6;
        $f1 |= ((int) $modules[8][8]) << 7;
        $f1 |= ((int) $modules[8][7]) << 8;
        for ($i = 9; $i < 15; $i++) {
            $f1 |= ((int) $modules[8][14 - $i]) << $i;
        }
        $f2 = 0;
        for ($i = 0; $i < 8; $i++) {
            $f2 |= ((int) $modules[8][$size - 1 - $i]) << $i;
        }
        for ($i = 8; $i < 15; $i++) {
            $f2 |= ((int) $modules[$size - 15 + $i][8]) << $i;
        }
        if ($f1 !== $f2) {
            return null;
        }
        $mask = -1;
        for ($m = 0; $m < 8; $m++) {
            if (self::formatBits($m) === $f1) {
                $mask = $m;
            }
        }
        if ($mask < 0) {
            return null;
        }
        [, $isFunction] = self::baseMatrix($version);
        $bits = [];
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
                    if (!$isFunction[$y][$x]) {
                        $bits[] = (int) ($modules[$y][$x] xor self::maskBit($mask, $x, $y));
                    }
                }
            }
        }
        $cws = [];
        $n = intdiv(count($bits), 8);
        for ($i = 0; $i < $n; $i++) {
            $v = 0;
            for ($b = 0; $b < 8; $b++) {
                $v = ($v << 1) | $bits[$i * 8 + $b];
            }
            $cws[] = $v;
        }
        return ['version' => $version, 'mask' => $mask, 'codewords' => $cws];
    }

    /**
     * The blocks of a version as [dataLength, ecLength] pairs, in interleaving order.
     * @return list<array{0:int,1:int}>
     */
    public static function blockLayout(int $version): array
    {
        [$d1, $n1, $d2, $n2, $ec] = self::BLOCKS[$version];
        $out = [];
        for ($i = 0; $i < $n1; $i++) {
            $out[] = [$d1, $ec];
        }
        for ($i = 0; $i < $n2; $i++) {
            $out[] = [$d2, $ec];
        }
        return $out;
    }

    private static function dataCodewords(int $version): int
    {
        [$d1, $n1, $d2, $n2] = self::BLOCKS[$version];
        return $d1 * $n1 + $d2 * $n2;
    }

    /** @return list<int> data codewords: mode, count, bytes, terminator, padding */
    private static function dataCodewordList(int $version, string $data): array
    {
        $bits = [];
        $put = static function (int $value, int $count) use (&$bits): void {
            for ($i = $count - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $put(0b0100, 4);
        $put(strlen($data), $version <= 9 ? 8 : 16);
        foreach (str_split($data) as $ch) {
            $put(ord($ch), 8);
        }
        $capBits = self::dataCodewords($version) * 8;
        $put(0, min(4, $capBits - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $cws = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $v = 0;
            for ($b = 0; $b < 8; $b++) {
                $v = ($v << 1) | $bits[$i + $b];
            }
            $cws[] = $v;
        }
        for ($pad = 0xEC; count($cws) < self::dataCodewords($version); $pad ^= 0xEC ^ 0x11) {
            $cws[] = $pad;
        }
        return $cws;
    }

    /** @param list<int> $data @return list<int> */
    private static function interleave(int $version, array $data): array
    {
        $layout = self::blockLayout($version);
        $blocksData = [];
        $blocksEc = [];
        $pos = 0;
        foreach ($layout as [$dl, $el]) {
            $d = array_slice($data, $pos, $dl);
            $pos += $dl;
            $blocksData[] = $d;
            $blocksEc[] = self::rsRemainder($d, $el);
        }
        $out = [];
        $maxData = max(array_map('count', $blocksData));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocksData as $b) {
                if ($i < count($b)) {
                    $out[] = $b[$i];
                }
            }
        }
        $ec = self::BLOCKS[$version][4];
        for ($i = 0; $i < $ec; $i++) {
            foreach ($blocksEc as $b) {
                $out[] = $b[$i];
            }
        }
        return $out;
    }

    private static function tables(): void
    {
        if (self::$exp !== null) {
            return;
        }
        $exp = [];
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** Error-correction generator coefficients. @return list<int> */
    private static function generator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $poly = $next;
        }
        return array_slice($poly, 1);
    }

    /**
     * Matrix with the function patterns drawn (format area reserved as light) and a map of function modules.
     * @return array{0: list<list<bool>>, 1: list<list<bool>>}
     */
    private static function baseMatrix(int $version): array
    {
        $size = 17 + 4 * $version;
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $f = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $x, int $y, bool $dark) use (&$m, &$f, $size): void {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $m[$y][$x] = $dark;
                $f[$y][$x] = true;
            }
        };
        // Timing patterns.
        for ($i = 0; $i < $size; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        // Finder patterns with separators.
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $d = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $d !== 2 && $d !== 4);
                }
            }
        }
        // Alignment patterns (not over the finders).
        $al = self::ALIGN[$version];
        $n = count($al);
        for ($a = 0; $a < $n; $a++) {
            for ($b = 0; $b < $n; $b++) {
                if (($a === 0 && $b === 0) || ($a === 0 && $b === $n - 1) || ($a === $n - 1 && $b === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($al[$a] + $dx, $al[$b] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // Reserve the format areas and the dark module; version information.
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) { // row/column 6 stays timing
                $set(8, $i, false);
                $set($i, 8, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, false);
            $set(8, $size - 1 - $i, false);
        }
        $set(8, $size - 8, true);
        if ($version >= 7) {
            $bits = self::versionBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $bit);
                $set($b, $a, $bit);
            }
        }
        return [$m, $f];
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => $x * $y % 2 + $x * $y % 3 === 0,
            6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
            default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
        };
    }

    /**
     * @param list<list<bool>> $m @param list<list<bool>> $isFunction
     * @return list<list<bool>>
     */
    private static function applyMask(array $m, array $isFunction, int $mask): array
    {
        $size = count($m);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$isFunction[$y][$x] && self::maskBit($mask, $x, $y)) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
        return $m;
    }

    /** @param list<list<bool>> $m */
    private static function drawFormat(array &$m, int $mask): void
    {
        $size = count($m);
        $bits = self::formatBits($mask);
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) {
            $m[$i][8] = $bit($i);
        }
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) {
            $m[8][14 - $i] = $bit($i);
        }
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; $i++) {
            $m[$size - 15 + $i][8] = $bit($i);
        }
        $m[$size - 8][8] = true;
    }

    /** @param list<list<bool>> $m */
    private static function penalty(array $m): int
    {
        $size = count($m);
        $score = 0;
        $dark = 0;
        $lines = [];
        for ($y = 0; $y < $size; $y++) {
            $lines[] = $m[$y];
            $col = [];
            for ($x = 0; $x < $size; $x++) {
                $col[] = $m[$x][$y];
                $dark += $m[$y][$x] ? 1 : 0;
            }
            $lines[] = $col;
        }
        $p1 = [true, false, true, true, true, false, true, false, false, false, false];
        $p2 = array_reverse($p1);
        foreach ($lines as $line) {
            // Runs of five or more.
            $run = 1;
            for ($i = 1; $i <= $size; $i++) {
                if ($i < $size && $line[$i] === $line[$i - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + $run - 5;
                    }
                    $run = 1;
                }
            }
            // Finder-like patterns.
            for ($i = 0; $i + 11 <= $size; $i++) {
                $win = array_slice($line, $i, 11);
                if ($win === $p1 || $win === $p2) {
                    $score += 40;
                }
            }
        }
        for ($y = 0; $y + 1 < $size; $y++) {
            for ($x = 0; $x + 1 < $size; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $total = $size * $size;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $score + max(0, $k) * 10;
    }
}

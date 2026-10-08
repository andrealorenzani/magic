<?php
declare(strict_types=1);

namespace Arcana\Audit;

use InvalidArgumentException;

/** Pure, deliberately tiny YAML emitter (block style). User text is only ever a quoted scalar value. */
final class Yaml
{
    private const RESERVED = ['null', 'true', 'false', 'yes', 'no', 'on', 'off', 'y', 'n', '~'];
    private const FLOW_MAX = 76;

    /** @param array<mixed> $data */
    public static function dump(array $data): string
    {
        $lines = self::lines($data);
        return $lines === [] ? "{}\n" : implode("\n", $lines) . "\n";
    }

    /** @param array<mixed> $v @return list<string> */
    private static function lines(array $v): array
    {
        $out = [];
        if (array_is_list($v)) {
            foreach ($v as $item) {
                if (is_array($item) && $item !== [] && ($flow = self::flow($item)) === null) {
                    $sub = self::lines($item);
                    $out[] = '- ' . array_shift($sub);
                    foreach ($sub as $l) {
                        $out[] = '  ' . $l;
                    }
                } else {
                    $out[] = '- ' . (is_array($item) ? ($item === [] ? '[]' : self::flow($item)) : self::scalar($item));
                }
            }
            return $out;
        }
        foreach ($v as $key => $val) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                throw new InvalidArgumentException('invalid YAML key');
            }
            if (is_array($val)) {
                if ($val === []) {
                    $out[] = $key . ': []';
                } elseif (($flow = self::flow($val)) !== null) {
                    $out[] = $key . ': ' . $flow;
                } else {
                    $out[] = $key . ':';
                    foreach (self::lines($val) as $l) {
                        $out[] = '  ' . $l;
                    }
                }
            } else {
                $out[] = $key . ': ' . self::scalar($val);
            }
        }
        return $out;
    }

    /** Flow list for short lists of scalars, else null. @param array<mixed> $v */
    private static function flow(array $v): ?string
    {
        if (!array_is_list($v)) {
            return null;
        }
        $parts = [];
        foreach ($v as $item) {
            if (is_array($item)) {
                return null;
            }
            $parts[] = self::scalar($item);
        }
        $s = '[' . implode(', ', $parts) . ']';
        return strlen($s) <= self::FLOW_MAX ? $s : null;
    }

    private static function scalar(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            if (is_nan($v) || is_infinite($v)) {
                return 'null';
            }
            $s = rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
            return ($s === '-0' || $s === '') ? '0' : $s;
        }
        if (is_string($v)) {
            return self::string($v);
        }
        throw new InvalidArgumentException('unsupported YAML value');
    }

    private static function string(string $s): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            return '"<invalid utf-8>"';
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_ .\/()-]*$/', $s) === 1
            && !str_ends_with($s, ' ') && !in_array(strtolower($s), self::RESERVED, true)) {
            return $s;
        }
        $out = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $o = mb_ord($ch, 'UTF-8');
            $out .= match (true) {
                $ch === '\\' => '\\\\',
                $ch === '"' => '\\"',
                $ch === "\n" => '\\n',
                $ch === "\r" => '\\r',
                $ch === "\t" => '\\t',
                $o === 0 => '\\0',
                $o < 0x20 || $o === 0x7F => sprintf('\\x%02X', $o),
                $o === 0x85 || $o === 0x2028 || $o === 0x2029 => sprintf('\\u%04X', $o),
                default => $ch,
            };
        }
        return '"' . $out . '"';
    }
}

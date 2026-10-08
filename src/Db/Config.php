<?php
declare(strict_types=1);

namespace Arcana\Db;

/** Loads config.php (a PHP file returning an array). Never throws, never outputs. */
final class Config
{
    /** @return ?array{host:string,port:int,name:string,user:string,password:string} */
    public static function load(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        try {
            $cfg = (static function (string $p) {
                return include $p;
            })($path);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($cfg)) {
            return null;
        }
        foreach (['host', 'name', 'user'] as $k) {
            if (!isset($cfg[$k]) || !is_string($cfg[$k]) || $cfg[$k] === '') {
                return null;
            }
        }
        if (!isset($cfg['password']) || !is_string($cfg['password'])) {
            return null;
        }
        $port = $cfg['port'] ?? 3306;
        if (!is_int($port) || $port < 1 || $port > 65535) {
            return null;
        }
        return ['host' => $cfg['host'], 'port' => $port, 'name' => $cfg['name'], 'user' => $cfg['user'], 'password' => $cfg['password']];
    }
}

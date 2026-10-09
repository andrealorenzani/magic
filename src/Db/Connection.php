<?php
declare(strict_types=1);

namespace Magic\Db;

use PDO;

/** Opens the PDO connection. Throws on failure (callers catch). */
final class Connection
{
    /** @param array{host:string,port:int,name:string,user:string,password:string} $cfg */
    public static function open(array $cfg): PDO
    {
        $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_PERSISTENT => false,
        ];
        if (defined('PDO::MYSQL_ATTR_READ_TIMEOUT')) {
            $options[\PDO::MYSQL_ATTR_READ_TIMEOUT] = 3;
        }
        return new PDO($dsn, $cfg['user'], $cfg['password'], $options);
    }
}

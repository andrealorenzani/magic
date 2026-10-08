<?php
declare(strict_types=1);

// Minimal PSR-4 autoloader: Arcana\Foo\Bar -> src/Foo/Bar.php (no Composer needed on shared hosting).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Arcana\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

<?php
declare(strict_types=1);

require __DIR__ . '/autoload.php';

const MAGIC_ROOT = __DIR__ . '/..';

/** HTML-escape helper for templates. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Version tag for a file in public/assets (its modification time), so browsers fetch a changed file at once. */
function asset_version(string $file): string
{
    $t = @filemtime(dirname(__DIR__) . '/public/assets/' . basename($file));
    return $t === false ? '0' : (string) $t;
}

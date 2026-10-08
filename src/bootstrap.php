<?php
declare(strict_types=1);

require __DIR__ . '/autoload.php';

const ARCANA_ROOT = __DIR__ . '/..';

/** HTML-escape helper for templates. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

<?php
/** Help terms: a visible word that opens a small popup beside it; made interactive by assets/help.js. */

use Magic\Content\Help;

if (!function_exists('help_term')) {
    function help_term(string $key, string $label): void
    {
        static $n = 0;
        $h = Help::get($key);
        if ($h === null) {
            if (defined('MAGIC_TESTING')) {
                throw new InvalidArgumentException('Unknown help key: ' . $key);
            }
            echo e($label);
            return;
        }
        $n++;
        $id = 'help-' . $n;
        ?><span class="help"><span class="help__term" data-help-for="<?= e($id) ?>"><?= e($label) ?></span><span id="<?= e($id) ?>" class="help__pop" hidden data-help="<?= e($key) ?>"><strong><?= e($h['title']) ?></strong> <?= e($h['text']) ?></span></span><?php
    }
}

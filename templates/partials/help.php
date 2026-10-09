<?php
/** "?" help popovers: a button and an in-flow text block, un-hidden by assets/help.js. */

use Magic\Content\Help;

if (!function_exists('help_button')) {
    function help_button(string $key): void
    {
        static $n = 0;
        $h = Help::get($key);
        if ($h === null) {
            if (defined('MAGIC_TESTING')) {
                throw new InvalidArgumentException('Unknown help key: ' . $key);
            }
            return;
        }
        $n++;
        $id = 'help-' . $n;
        ?><span class="help"><button type="button" class="help__btn" hidden aria-expanded="false" aria-controls="<?= e($id) ?>" aria-label="What is this: <?= e($h['title']) ?>">?</button><span id="<?= e($id) ?>" class="help__pop" hidden data-help="<?= e($key) ?>"><?= e($h['text']) ?></span></span><?php
    }
}

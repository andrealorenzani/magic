<?php
/** Help terms: a visible word that opens a small popup beside it; made interactive by assets/help.js. */

use Magic\Content\Help;

if (!function_exists('help_term')) {
    function help_term(string $key, string $label): void
    {
        $h = Help::get($key);
        if ($h === null) {
            if (defined('MAGIC_TESTING')) {
                throw new InvalidArgumentException('Unknown help key: ' . $key);
            }
            echo e($label);
            return;
        }
        static $n = 0;
        $n++;
        $id = 'help-' . $n;
        ?><span class="help"><span class="help__term" data-help-for="<?= e($id) ?>"><?= e($label) ?></span><span id="<?= e($id) ?>" class="help__pop" hidden data-help="<?= e($key) ?>"><strong><?= e($h['title']) ?></strong> <?= e($h['text']) ?></span></span><?php
    }
}

if (!function_exists('bar')) {
    /**
     * A bar whose value shows in a small bubble on hover or focus (and always in print).
     * @param string $text the value as words, e.g. "48%"
     */
    function bar(int|float $value, string $text, string $label, int $min = 0, int $max = 100, string $class = ''): void
    {
        ?><span class="bar<?= $class !== '' ? ' ' . e($class) : '' ?>" tabindex="0"><meter min="<?= e($min) ?>" max="<?= e($max) ?>" value="<?= e($value) ?>" aria-label="<?= e($label . ': ' . $text) ?>"><?= e($text) ?></meter><span class="bar__tip" aria-hidden="true"><?= e($text) ?></span></span><?php
    }
}

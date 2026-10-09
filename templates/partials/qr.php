<?php
/** QR code as inline SVG: one white rect and one black path (presentation attributes only, CSP safe). */

use Magic\Share\Qr;

if (!function_exists('qr_svg')) {
    /** @param list<list<bool>> $modules @param string $label text alternative */
    function qr_svg(array $modules, string $label): void
    {
        $n = count($modules) + 2 * Qr::QUIET;
        ?>
        <svg class="qr" viewBox="0 0 <?= e($n) ?> <?= e($n) ?>" role="img" aria-label="<?= e($label) ?>" shape-rendering="crispEdges">
          <rect width="<?= e($n) ?>" height="<?= e($n) ?>" fill="#fff"/>
          <path d="<?= e(Qr::path($modules)) ?>" fill="#000"/>
        </svg>
        <?php
    }
}

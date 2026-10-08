<?php
/** Biorhythm helpers: SVG polylines built from integer series only. */

if (!function_exists('bio_points')) {
    /** @param list<int> $curve values -100..100 @return string SVG points attribute */
    function bio_points(array $curve): string
    {
        $n = max(1, count($curve) - 1);
        $pts = [];
        foreach ($curve as $i => $v) {
            $pts[] = round(300 * $i / $n, 1) . ',' . round(50 - 0.45 * $v, 1);
        }
        return implode(' ', $pts);
    }

    /**
     * @param list<array{class:string, curve:list<int>}> $series
     * @param string $label text alternative with the numbers
     */
    function bio_svg(array $series, string $label): void
    {
        ?>
        <svg class="bio-chart" viewBox="0 0 300 100" role="img" aria-label="<?= e($label) ?>" preserveAspectRatio="none">
          <line class="bio-axis" x1="0" y1="50" x2="300" y2="50"/>
          <?php foreach ($series as $s): ?>
            <polyline class="<?= e($s['class']) ?>" fill="none" points="<?= e(bio_points($s['curve'])) ?>"/>
          <?php endforeach; ?>
        </svg>
        <?php
    }
}

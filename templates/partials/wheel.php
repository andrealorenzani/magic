<?php
/** Chart wheel: SVG built from ChartWheel::layout() numbers only. Colours come from CSS classes. */

if (!function_exists('wheel_svg')) {
    /**
     * @param array<string,mixed> $w ChartWheel::layout() result
     * @param string $label text alternative
     */
    function wheel_svg(array $w, string $label): void
    {
        $c = \Magic\ChartWheel::CENTER;
        $n = static fn (float $v): string => (string) round($v, 2);
        ?>
        <svg class="wheel" viewBox="-8 -8 416 416" role="img" aria-label="<?= e($label) ?>">
          <title><?= e($label) ?></title>
          <circle class="wheel__ring" cx="<?= e($n($c)) ?>" cy="<?= e($n($c)) ?>" r="<?= e($n(\Magic\ChartWheel::R_OUTER)) ?>"/>
          <circle class="wheel__ring" cx="<?= e($n($c)) ?>" cy="<?= e($n($c)) ?>" r="<?= e($n(\Magic\ChartWheel::R_SIGN_INNER)) ?>"/>
          <circle class="wheel__ring" cx="<?= e($n($c)) ?>" cy="<?= e($n($c)) ?>" r="<?= e($n(\Magic\ChartWheel::R_HOUSE_INNER)) ?>"/>
          <?php foreach ($w['signs'] as $s): ?>
            <line class="wheel__divider" x1="<?= e($n($s['line']['x1'])) ?>" y1="<?= e($n($s['line']['y1'])) ?>" x2="<?= e($n($s['line']['x2'])) ?>" y2="<?= e($n($s['line']['y2'])) ?>"/>
            <text class="wheel__sign wheel__sign--<?= e($s['element']) ?>" x="<?= e($n($s['label']['x'])) ?>" y="<?= e($n($s['label']['y'])) ?>" text-anchor="middle" dominant-baseline="central"><title><?= e($s['name']) ?></title><?= e($s['symbol']) ?></text>
          <?php endforeach; ?>
          <?php foreach ($w['houses'] as $h): ?>
            <text class="wheel__house" x="<?= e($n($h['label']['x'])) ?>" y="<?= e($n($h['label']['y'])) ?>" text-anchor="middle" dominant-baseline="central"><?= e($h['number']) ?></text>
          <?php endforeach; ?>
          <?php foreach ($w['axes'] as $a): ?>
            <line class="wheel__axis wheel__axis--<?= e($a['id']) ?>" x1="<?= e($n($a['line']['x1'])) ?>" y1="<?= e($n($a['line']['y1'])) ?>" x2="<?= e($n($a['line']['x2'])) ?>" y2="<?= e($n($a['line']['y2'])) ?>"/>
            <text class="wheel__axis-label" x="<?= e($n($a['text']['x'])) ?>" y="<?= e($n($a['text']['y'])) ?>" text-anchor="middle" dominant-baseline="central"><?= e($a['label']) ?></text>
          <?php endforeach; ?>
          <?php foreach ($w['bodies'] as $b): ?>
            <line class="wheel__tick" x1="<?= e($n($b['tick']['x1'])) ?>" y1="<?= e($n($b['tick']['y1'])) ?>" x2="<?= e($n($b['tick']['x2'])) ?>" y2="<?= e($n($b['tick']['y2'])) ?>"/>
            <text class="wheel__body wheel__body--<?= e($b['id']) ?>" x="<?= e($n($b['position']['x'])) ?>" y="<?= e($n($b['position']['y'])) ?>" text-anchor="middle" dominant-baseline="central"><title><?= e($b['title']) ?></title><?= e($b['glyph']) ?></text>
          <?php endforeach; ?>
        </svg>
        <?php
    }
}

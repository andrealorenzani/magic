<?php
/**
 * "Today's sky": phase, Moon sign and the daily reading.
 * @var array $sky Sky\Today::for() result
 * @var string $skyMode 'self' (full reading) or 'love' (compact)
 * @var array<string,string> $skyNames person key => display name (Love)
 */
use Magic\Content\Daily;

require_once __DIR__ . '/help.php';

$moon = $sky['moon'];
?>
<section class="block sky" aria-labelledby="sky-h">
  <h2 id="sky-h"><?= icon('bulb') ?> <?php help_term('self.sky', "Today's sky"); ?></h2>
  <p class="sky__phase"><span class="sky__symbol" aria-hidden="true"><?= e($sky['symbol']) ?></span>
    <strong><?= e($sky['phaseName']) ?></strong>, <?= e($sky['illumination']) ?>% lit</p>
  <p><meter min="0" max="100" value="<?= e($sky['illumination']) ?>"><?= e($sky['illumination']) ?>%</meter></p>
  <p>The Moon is in <span aria-hidden="true"><?= e($moon['symbol']) ?></span> <?= e($moon['name']) ?> on <?= e($sky['day']) ?>.</p>
  <?php if ($skyMode === 'self'): ?>
    <?php foreach ($sky['people'] as $p): ?>
      <p class="sky__reading"><?= e($p['reading']) ?></p>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="sky__reading"><?= e(Daily::MOOD[$moon['id']]) ?></p>
    <ul class="sky__people">
      <?php foreach ($sky['people'] as $key => $p): ?>
        <li><strong><?= e($skyNames[$key] ?? '') ?></strong>: <?= e($p['relation']) ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="sky__reading"><?= e(Daily::INVITATION[$sky['phase']]) ?></p>
  <?php endif; ?>
  <p class="note">For entertainment: a mood for the day, not a forecast. The sky is taken at midday UTC of the reading day.</p>
</section>

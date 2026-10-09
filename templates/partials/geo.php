<?php
/**
 * "Distance and geography" card.
 * @var ?array{pairs: list<array<string,mixed>>} $geo
 */
use Magic\Earth\Geography;

require_once __DIR__ . '/help.php';

if (empty($geo['pairs'])) {
    return;
}
?>
<section class="block geo" aria-labelledby="geo-h">
  <div class="head"><h2 id="geo-h"><?= icon('bolt') ?> Distance and geography</h2><?php help_button('self.geo'); ?></div>
  <p class="hint">Approximate, in a straight line.</p>
  <ul class="geo__list">
    <?php foreach ($geo['pairs'] as $pair): ?>
      <li>
        <strong><?= e($pair['label']) ?></strong>:
        <?php if ($pair['km'] === 0): ?>
          same place
        <?php else: ?>
          <?= e(number_format($pair['km'])) ?> km (<?= e(number_format($pair['miles'])) ?> miles)
        <?php endif; ?>
        &middot; time zone difference
        <?= $pair['minutes'] === 0 ? 'none' : e(Geography::formatOffset($pair['minutes'])) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php
/**
 * Synastry: the tightest aspects between two charts.
 * @var array $synastry LoveReading 'synastry'
 * @var array{a:string,b:string} $names
 */
use Magic\Content\Aspects as AspectText;
use Magic\Content\Bodies;

require_once __DIR__ . '/help.php';

?>
<section class="block synastry" aria-labelledby="syn-h">
  <div class="head"><h2 id="syn-h"><?= icon('heart') ?> Synastry</h2><?php help_button('love.synastry'); ?></div>
  <?php if (!$synastry['available']): ?>
    <article class="card card--empty">
      <p class="card__text">Add <?= e($names['b']) ?>'s birth date to compare your two skies planet by planet.</p>
    </article>
  <?php elseif ($synastry['rows'] === []): ?>
    <p>No close aspects were found between your two charts.</p>
  <?php else: ?>
    <div class="head"><p><?= e($synastry['total']) ?> aspect<?= $synastry['total'] === 1 ? '' : 's' ?> between your charts:
      <?= e($synastry['counts']['harmonious']) ?> harmonious, <?= e($synastry['counts']['tense']) ?> tense, <?= e($synastry['counts']['neutral']) ?> blending.
      The <?= e(count($synastry['rows'])) ?> closest <?= count($synastry['rows']) === 1 ? 'is' : 'are' ?> listed.</p><?php help_button('love.aspect'); ?></div>
    <div class="table-wrap">
      <table class="synastry__table">
        <caption class="sr-only">Closest aspects between <?= e($names['a']) ?> and <?= e($names['b']) ?></caption>
        <thead>
          <tr><th scope="col">Aspect</th><th scope="col">Orb</th><th scope="col">Meaning</th></tr>
        </thead>
        <tbody>
          <?php foreach ($synastry['rows'] as $r): ?>
            <tr class="synastry__row synastry__row--<?= e($r['tone']) ?>">
              <th scope="row">
                <?= e($names['a']) ?>'s <?= e(Bodies::INFO[$r['a']]['title']) ?>
                <?= e(AspectText::NAMES[$r['type']]) ?>
                <?= e($names['b']) ?>'s <?= e(Bodies::INFO[$r['b']]['title']) ?>
              </th>
              <td><?= e(number_format($r['orb'], 1)) ?>&deg;</td>
              <td><span class="pill synastry__tone"><?= e(AspectText::TONE_LABELS[$r['tone']]) ?></span> <?= e(AspectText::MEANING[$r['type']]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($synastry['approx']): ?>
      <p class="note"><span class="badge">Approximate</span> At least one birth time or place is missing, so the Moon, Ascendant and Midheaven are left out and the planets are taken at midday UTC.</p>
    <?php endif; ?>
    <p class="note">Orb is how far each aspect is from exact. For entertainment: two charts are never a verdict on a relationship.</p>
  <?php endif; ?>
</section>

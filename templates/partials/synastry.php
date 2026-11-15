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
  <h2 id="syn-h"><?= icon('heart') ?> <?php help_term('love.synastry', 'Synastry'); ?></h2>
  <?php if (!$synastry['available']): ?>
    <article class="card card--empty">
      <p class="card__text">Add <?= e($names['b']) ?>'s birth date to compare your two skies planet by planet.</p>
    </article>
  <?php elseif ($synastry['best'] === [] && $synastry['worst'] === []): ?>
    <p>No close aspects were found between your two charts.</p>
  <?php else: ?>
    <p><?= e($synastry['total']) ?> aspect<?= $synastry['total'] === 1 ? '' : 's' ?> between your charts:
      <?= e($synastry['counts']['harmonious']) ?> harmonious, <?= e($synastry['counts']['tense']) ?> tense, <?= e($synastry['counts']['neutral']) ?> blending.
      Here are the <?php help_term('love.aspect', 'closest'); ?> ones that help you most and those that challenge you most.</p>
    <div class="synastry__groups">
    <?php foreach (['best' => 'Best aspects', 'worst' => 'Hardest aspects'] as $group => $title): if ($synastry[$group] === []) { continue; } ?>
    <div>
    <h3 class="synastry__group"><?= e($title) ?></h3>
    <div class="table-wrap">
      <table class="synastry__table synastry__table--<?= e($group) ?>">
        <caption class="sr-only"><?= e($title) ?> between <?= e($names['a']) ?> and <?= e($names['b']) ?></caption>
        <thead>
          <tr><th scope="col">Aspect</th><th scope="col">Meaning</th></tr>
        </thead>
        <tbody>
          <?php foreach ($synastry[$group] as $r): ?>
            <tr class="synastry__row synastry__row--<?= e($r['tone']) ?>">
              <th scope="row">
                <?= e($names['a']) ?>'s <?= e(Bodies::INFO[$r['a']]['title']) ?>
                <?= e(AspectText::NAMES[$r['type']]) ?>
                <?= e($names['b']) ?>'s <?= e(Bodies::INFO[$r['b']]['title']) ?>
              </th>
              <td><span class="pill synastry__tone"><?= e(AspectText::TONE_LABELS[$r['tone']]) ?></span> <?= e(AspectText::MEANING[$r['type']]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ($synastry['approx']): ?>
      <p class="note"><span class="badge">Approximate</span> At least one birth time or place is missing, so the Moon, Ascendant and Midheaven are left out and the planets are taken at midday UTC.</p>
    <?php endif; ?>
    <p class="note">For entertainment: two charts are never a verdict on a relationship.</p>
  <?php endif; ?>
</section>

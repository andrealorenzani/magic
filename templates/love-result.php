<?php
/** @var array $view @var string $today */

use Arcana\Content\Traits;

require_once __DIR__ . '/partials/icons.php';
require_once __DIR__ . '/partials/bio.php';

$names = $view['names'];
$aff = $view['affinity'];
$bio = $view['bio'];
$common = $view['common'];
$bodyName = ['sun' => 'Sun', 'moon' => 'Moon', 'ascendant' => 'Ascendant'];
$when = ['Today', 'Tomorrow', 'The day after'];
$pos = static fn (array $p): string => $p['symbol'] . ' ' . $p['name'] . ' ' . $p['degree'] . '°';
?>
<div class="print-only print-head">
  <h2>Arcana · <?= e($names['a']) ?> &amp; <?= e($names['b']) ?></h2>
  <p>Reading of <?= e($today) ?></p>
</div>
<div class="toolbar no-print">
  <button type="button" class="print-btn" data-print hidden><?= icon('printer') ?> Print</button>
</div>

<section class="block heart-card" aria-labelledby="name-h">
  <h2 id="name-h"><?= icon('heart') ?> <?= e($names['a']) ?> &amp; <?= e($names['b']) ?></h2>
  <p class="heart__percent"><span aria-hidden="true">♥</span> <?= e($aff['percent']) ?>%</p>
  <p><meter min="0" max="100" value="<?= e($aff['percent']) ?>"><?= e($aff['percent']) ?>%</meter></p>
  <?php if ($aff['noLetters']): ?>
    <p>These names share no letters of AMORE.</p>
  <?php else: ?>
    <p>Name affinity from the letters of AMORE in your two names.</p>
  <?php endif; ?>
  <details>
    <summary>How this was computed</summary>
    <p>Letter counts:
      <?php foreach ($aff['counts'] as $l => $n): ?><strong><?= e($l) ?></strong> <?= e($n) ?> <?php endforeach; ?>
      → digits <strong><?= e(implode('', $aff['slots'])) ?></strong>, start value <?= e($aff['start']) ?>.</p>
    <p>Adding neighbouring digits until the value is 100 or less: <?= e(implode(' → ', $aff['chain'])) ?>.</p>
  </details>
</section>

<?php if ($bio): ?>
<section class="block" aria-labelledby="sync-h">
  <h2 id="sync-h"><?= icon('bolt') ?> Biorhythm synchrony</h2>
  <p><strong><?= e(round($bio['overall'])) ?>%</strong> overall. <?= e(Traits::VERDICTS[$bio['band']]) ?></p>
  <?php foreach ($bio['cycles'] as $name => $c): ?>
    <div class="bio-row">
      <h3><?= e(ucfirst($name)) ?> <small>(<?= e($c['period']) ?> days)</small></h3>
      <p><meter min="0" max="100" value="<?= e(round($c['sync'])) ?>"><?= e(round($c['sync'])) ?>%</meter>
        <strong><?= e(round($c['sync'])) ?>%</strong> in step.
        <?php if ($c['peaksTogether']): ?>Your peaks fall on the same days.
        <?php else: ?>Your birthdays are <?= e(abs($c['delta'])) ?> day<?= abs($c['delta']) === 1 ? '' : 's' ?> apart in this cycle.<?php endif; ?></p>
      <?php if ($c['flat']): ?>
        <p>You balance each other: when one rises the other falls.</p>
      <?php else: ?>
        <p>Together you peak around <?= e($c['nextCombinedPeak']) ?> and dip around <?= e($c['nextCombinedTrough']) ?>.</p>
      <?php endif; ?>
      <p>
        <?php if ($c['nextBothHigh']): ?>Both high: <?= e($c['nextBothHigh']['date']) ?> (<?= e($c['nextBothHigh']['a']) ?>% / <?= e($c['nextBothHigh']['b']) ?>%).
        <?php else: ?>Both high: not in the next 4 months.<?php endif; ?>
        <?php if ($c['nextBothLow']): ?>Both low: <?= e($c['nextBothLow']['date']) ?> (<?= e($c['nextBothLow']['a']) ?>% / <?= e($c['nextBothLow']['b']) ?>%).
        <?php else: ?>Both low: not in the next 4 months.<?php endif; ?>
      </p>
      <?php bio_svg(
          [['class' => 'bio--physical', 'curve' => $c['curveA']], ['class' => 'bio--emotional bio--second', 'curve' => $c['curveB']]],
          ucfirst($name) . ' cycle for the next 30 days. Today: ' . $names['a'] . ' ' . $c['curveA'][0] . '%, ' . $names['b'] . ' ' . $c['curveB'][0] . '%'
      ); ?>
      <ul class="legend">
        <li><span class="swatch bio--physical" aria-hidden="true"></span><?= e($names['a']) ?></li>
        <li><span class="swatch bio--emotional bio--second" aria-hidden="true"></span><?= e($names['b']) ?></li>
      </ul>
    </div>
  <?php endforeach; ?>
  <p class="note">Biorhythms are a popular theory, not scientifically validated. Out of step is not a verdict on your relationship.</p>
</section>
<?php endif; ?>

<?php if ($common !== null): ?>
<section class="block" aria-labelledby="common-h">
  <h2 id="common-h"><?= icon('heart') ?> What you have in common<?php if ($common['score'] !== null): ?> <small>(<?= e($common['score']) ?>%)</small><?php endif; ?></h2>
  <?php if ($common['items']): ?>
  <div class="trio">
    <?php foreach ($common['items'] as $it): ?>
      <article class="card card--<?= e(strtolower($it['a']['element'])) ?>">
        <h3 class="card__role"><?= e($bodyName[$it['body']]) ?></h3>
        <p class="card__pos"><?= e($names['a']) ?>: <?= e($pos($it['a'])) ?><br><?= e($names['b']) ?>: <?= e($pos($it['b'])) ?></p>
        <p class="card__tag"><?= e(Traits::LEVELS[$it['level']]) ?></p>
        <p class="card__text"><?= e(implode(', ', $it['traits'])) ?></p>
        <?php if ($it['approx']): ?><p class="note">May be the adjacent sign depending on the birth time.</p><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php foreach ($common['basis'] as $line): ?><p class="note"><?= e($line) ?></p><?php endforeach; ?>
</section>
<?php endif; ?>

<section class="block" aria-labelledby="tarot-h">
  <h2 id="tarot-h"><?= icon('bulb') ?> Your three-day tarot</h2>
  <div class="tarot">
    <?php foreach ($view['tarot'] as $i => $t): $c = $t['card']; ?>
      <article class="tarot__card">
        <h3 class="card__role"><?= e($when[$i] ?? $t['date']) ?> <small><?= e($t['date']) ?></small></h3>
        <div class="tarot__face">
          <div class="tarot__art<?= $t['reversed'] ? ' tarot__art--reversed' : '' ?>" aria-hidden="true">
            <span class="tarot__num"><?= e($c['number']) ?></span>
            <span class="tarot__name"><?= e($c['name']) ?></span>
          </div>
        </div>
        <p class="card__sign"><?= e($c['name']) ?></p>
        <p class="card__pos"><?= $t['reversed'] ? 'Reversed' : 'Upright' ?></p>
        <p class="card__text"><?= e($t['meaning']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="note">The cards depend on your names, birth dates and the day: the same reading appears on every reload. For entertainment.</p>
</section>

<?php
/** @var array $view @var string $today @var ?array $share */

use Magic\Content\Signs;
use Magic\Content\TarotSpread;
use Magic\Content\Traits;

require_once __DIR__ . '/partials/icons.php';
require_once __DIR__ . '/partials/bio.php';

$names = $view['names'];
$aff = $view['affinity'];
$bio = $view['bio'];
$common = $view['common'];
$bodyName = ['sun' => 'Sun', 'moon' => 'Moon', 'ascendant' => 'Ascendant'];
$pos = static fn (array $p): string => $p['symbol'] . ' ' . $p['name'] . ' ' . $p['degree'] . '°';
?>
<div class="print-only print-head">
  <h2>Magic · <?= e($names['a']) ?> &amp; <?= e($names['b']) ?></h2>
  <p>Reading of <?= e($today) ?></p>
</div>
<?php if (!empty($view['dayZone'])): ?>
<p class="note">Today for you: <?= e($today) ?> (time zone <?= e($view['dayZone']) ?>)</p>
<?php endif; ?>
<div class="toolbar no-print">
  <button type="button" class="print-btn" data-print hidden><?= icon('printer') ?> Print</button>
</div>

<section class="block heart-card" aria-labelledby="name-h">
  <h2 id="name-h"><?= icon('heart') ?> <?= e($names['a']) ?> &amp; <?= e($names['b']) ?></h2>
  <?php if ($aff['noLetters']): ?>
    <p>There is nothing to compare in these names, so no name reading is shown.</p>
  <?php else: ?>
    <p class="heart__percent"><span aria-hidden="true">♥</span> <?= e($aff['percent']) ?>%</p>
    <p><meter min="0" max="100" value="<?= e($aff['percent']) ?>"><?= e($aff['percent']) ?>%</meter></p>
    <p>How your two names harmonise.</p>
  <?php endif; ?>
</section>

<?php if ($bio): ?>
<section class="block" aria-labelledby="sync-h">
  <h2 id="sync-h"><?= icon('bolt') ?> Biorhythm synchrony</h2>
  <p><strong><?= e(round($bio['overall'])) ?>%</strong> overall. <?= e(Traits::VERDICTS[$bio['band']]) ?></p>
  <div class="sync-grid">
    <?php foreach ($bio['cycles'] as $name => $c): ?>
      <article class="sync-card">
        <h3><?= e(ucfirst($name)) ?> <small>(<?= e($c['period']) ?> days)</small></h3>
        <p class="sync-card__pct"><meter min="0" max="100" value="<?= e(round($c['sync'])) ?>"><?= e(round($c['sync'])) ?>%</meter>
          <strong><?= e(round($c['sync'])) ?>%</strong> affinity</p>
        <dl class="sync-card__dates">
          <dt>Next shared high</dt><dd><?= $c['nextBothHigh'] ? e($c['nextBothHigh']['date']) : 'not in the next 4 months' ?></dd>
          <dt>Next shared low</dt><dd><?= $c['nextBothLow'] ? e($c['nextBothLow']['date']) : 'not in the next 4 months' ?></dd>
        </dl>
      </article>
    <?php endforeach; ?>
  </div>
  <details class="sync-more">
    <summary>Curves and details</summary>
    <div class="sync-grid">
      <?php foreach ($bio['cycles'] as $name => $c): ?>
        <div class="sync-card sync-card--detail">
          <h3><?= e(ucfirst($name)) ?></h3>
          <p>
            <?php if ($c['peaksTogether']): ?>Your peaks fall on the same days.
            <?php else: ?>Your birthdays are <?= e(abs($c['delta'])) ?> day<?= abs($c['delta']) === 1 ? '' : 's' ?> apart in this cycle.<?php endif; ?>
          </p>
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
    </div>
  </details>
  <p class="note">Biorhythms are a popular theory, not scientifically validated. Out of step is not a verdict on your relationship.</p>
</section>
<?php endif; ?>

<section class="block" aria-labelledby="common-h">
  <h2 id="common-h"><?= icon('heart') ?> What you have in common</h2>
  <?php if ($common === null): ?>
    <article class="card card--empty">
      <p class="card__text">Add <?= e($names['b']) ?>'s birth date to see what you share.</p>
    </article>
  <?php else: ?>
    <?php if ($common['score'] !== null): ?>
      <p class="common__summary"><meter min="0" max="100" value="<?= e($common['score']) ?>"><?= e($common['score']) ?>%</meter>
        <strong><?= e($common['score']) ?>%</strong> <?= e(Traits::COMMON_VERDICTS[Traits::commonBand($common['score'])]) ?></p>
    <?php endif; ?>
    <?php
    $compared = array_column($common['items'], 'body');
    ?>
    <div class="trio">
      <?php foreach ($common['items'] as $it): $role = Signs::ROLES[$it['body']]; ?>
        <article class="card card--<?= e(strtolower($it['a']['element'])) ?>">
          <h3 class="card__role"><?= e($bodyName[$it['body']]) ?></h3>
          <p class="card__tag"><?= e($role['title']) ?> - <?= e(lcfirst($role['tagline'])) ?></p>
          <div class="chips">
            <p class="chip chip--<?= e(strtolower($it['a']['element'])) ?>"><span class="chip__who"><?= e($names['a']) ?></span><span class="chip__sign"><?= e($pos($it['a'])) ?></span></p>
            <p class="chip chip--<?= e(strtolower($it['b']['element'])) ?>"><span class="chip__who"><?= e($names['b']) ?></span><span class="chip__sign"><?= e($pos($it['b'])) ?></span></p>
          </div>
          <p><span class="pill pill--level"><?= e(Traits::LEVEL_LABELS[$it['level']]) ?></span></p>
          <p class="card__text"><?= e(Traits::LEVELS[$it['level']]) ?></p>
          <p class="card__meaning"><?= e(Traits::COMMON_MEANING[$it['body']][$it['level']]) ?></p>
          <?php if (in_array($it['level'], ['sign', 'element', 'modality'], true)): ?>
          <ul class="pills">
            <?php foreach ($it['traits'] as $trait): ?><li class="pill"><?= e($trait) ?></li><?php endforeach; ?>
          </ul>
          <?php elseif ($it['level'] === 'complement'): ?>
          <p class="note"><?= e(implode(' ', $it['traits'])) ?></p>
          <?php endif; ?>
          <?php if ($it['approx']): ?><p class="badge-note"><span class="badge">Approximate</span> This sign may differ depending on the birth time.</p><?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php foreach ($bodyName as $body => $label): if (in_array($body, $compared, true)) { continue; } ?>
        <article class="card card--empty">
          <h3 class="card__role"><?= e($label) ?></h3>
          <p class="card__text"><?= $body === 'ascendant'
              ? 'Ascendant: add both birth times and cities to compare it.'
              : e($label) . ': add both birth dates to compare it.' ?></p>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($compared): ?>
      <p class="note">Compared: <?= e(implode(', ', array_map(static fn (string $b): string => $bodyName[$b], $compared))) ?>.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php $synastry = $view['synastry']; include __DIR__ . '/partials/synastry.php'; ?>

<?php $sky = $view['sky']; $skyMode = 'love'; $skyNames = $names; include __DIR__ . '/partials/sky.php'; ?>

<?php $geo = $view['geo'] ?? null; include __DIR__ . '/partials/geo.php'; ?>

<section class="block" aria-labelledby="tarot-h">
  <h2 id="tarot-h"><?= icon('bulb') ?> Your connection in three cards</h2>
  <div class="tarot">
    <?php foreach ($view['tarot'] as $t): $c = $t['card']; $pp = TarotSpread::POSITIONS[$t['position']]; ?>
      <article class="tarot__card tarot__card--<?= e($t['position']) ?>">
        <h3 class="card__role"><?= e($pp['title']) ?></h3>
        <p class="hint"><?= e($pp['question']) ?></p>
        <div class="tarot__face">
          <div class="tarot__art<?= $c['suit'] !== null ? ' tarot__art--' . e($c['suit']) : '' ?><?= $t['reversed'] ? ' tarot__art--reversed' : '' ?>" aria-hidden="true">
            <span class="tarot__num"><?= e($c['mark']) ?></span>
            <span class="tarot__name"><?= e($c['name']) ?></span>
          </div>
        </div>
        <p class="card__sign"><?= e($c['name']) ?></p>
        <p class="card__pos"><?= $t['reversed'] ? 'Reversed' : 'Upright' ?></p>
        <p class="card__essence"><?= e($t['reversed'] ? $c['reversed'] : $c['upright']) ?></p>
        <p class="card__text"><?= e($t['text']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="note"><?= $view['tarotShared'] ? 'These cards come from the link you opened. ' : 'The cards depend on your names, birth dates and the day: the same reading appears on every reload. ' ?>For entertainment.</p>
</section>

<?php if ($share !== null) { include __DIR__ . '/partials/share.php'; } ?>

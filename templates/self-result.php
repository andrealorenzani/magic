<?php
/** @var array $view @var string $today */

use Arcana\Content\Bodies;
use Arcana\Content\Signs;

require_once __DIR__ . '/partials/icons.php';
require_once __DIR__ . '/partials/bio.php';

$chart = $view['chart'];
$aff = $view['affinity'];
$bio = $view['bio'];
$refName = ['sun' => 'Sun', 'moon' => 'Moon', 'ascendant' => 'Ascendant', 'venus' => 'Venus', 'mars' => 'Mars'];
$bioClass = ['physical' => 'bio--physical', 'emotional' => 'bio--emotional', 'intellectual' => 'bio--intellectual'];
$entry = static function (array $s, string $key, string $heading): void { ?>
  <article class="card card--<?= e(strtolower($s['sign']['element'])) ?> affinity">
    <div class="card__symbol" aria-hidden="true"><?= e($s['sign']['symbol']) ?></div>
    <h4 class="card__role"><?= e($heading) ?></h4>
    <p class="card__sign"><?= e($s['sign']['name']) ?></p>
    <p class="card__pos"><meter min="0" max="100" value="<?= e(round($s[$key])) ?>"><?= e(round($s[$key])) ?>%</meter> <?= e(round($s[$key])) ?>% match</p>
    <ul class="reasons">
      <?php foreach ($s['reasons'] as $r): ?>
        <li><?= e(ucfirst($r['ref'])) ?> in <?= e($r['refSign']) ?> <?= e($r['aspect']) ?> <?= e($s['sign']['name']) ?>;
          <?php if ($r['ruler'] === 'the same'): ?>both ruled by <?= e($r['rulers']) ?>.<?php else: ?>rulers <?= e($r['rulers']) ?> are <?= e($r['ruler']) ?>.<?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </article>
<?php };
?>
<div class="print-only print-head">
  <h2>Arcana · Self discovery</h2>
  <p>Reading of <?= e($today) ?></p>
</div>
<div class="toolbar no-print">
  <button type="button" class="print-btn" data-print hidden><?= icon('printer') ?> Print</button>
</div>

<div class="trio">
<?php foreach (['sun', 'ascendant', 'moon'] as $role): $p = $chart[$role]; $r = Signs::ROLES[$role]; ?>
  <article class="card card--<?= e(strtolower($p['element'])) ?>">
    <div class="card__symbol" aria-hidden="true"><?= e($p['symbol']) ?></div>
    <h3 class="card__role"><?= e($r['title']) ?></h3>
    <p class="card__sign"><?= e($p['name']) ?></p>
    <p class="card__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?> · <?= e($p['modality']) ?></p>
    <p class="card__tag"><?= e($r['tagline']) ?></p>
    <p class="card__text"><?= e(Signs::TEXT[$p['id']]) ?></p>
  </article>
<?php endforeach; ?>
</div>

<?php if ($chart['planetsSupported']): ?>
<section class="block" aria-labelledby="planets-h">
  <h2 id="planets-h"><?= icon('bolt') ?> The planets</h2>
  <div class="planets">
    <?php foreach ($chart['planets'] as $id => $pl): $p = $pl['position']; $b = Bodies::INFO[$id]; ?>
      <article class="planet card--<?= e(strtolower($p['element'])) ?>">
        <span class="planet__glyph" aria-hidden="true"><?= e($b['glyph']) ?></span>
        <h3><?= e($b['title']) ?><?php if ($pl['retrograde']): ?> <abbr class="badge" title="Retrograde">R</abbr><?php endif; ?></h3>
        <p class="planet__sign"><span aria-hidden="true"><?= e($p['symbol']) ?></span> <?= e($p['name']) ?></p>
        <p class="planet__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?></p>
        <p class="planet__text"><?= e($b['meaning']) ?></p>
      </article>
    <?php endforeach; ?>
    <?php $p = $chart['node']; $b = Bodies::INFO['node']; ?>
    <article class="planet card--<?= e(strtolower($p['element'])) ?>">
      <span class="planet__glyph" aria-hidden="true"><?= e($b['glyph']) ?></span>
      <h3><?= e($b['title']) ?></h3>
      <p class="planet__sign"><span aria-hidden="true"><?= e($p['symbol']) ?></span> <?= e($p['name']) ?></p>
      <p class="planet__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?></p>
      <p class="planet__text"><?= e($b['meaning']) ?></p>
    </article>
  </div>
  <p class="note">Planet positions are approximate (a fraction of a degree); a sign can be off near its boundary. <abbr class="badge" title="Retrograde">R</abbr> = apparently moving backwards.</p>
</section>
<?php endif; ?>

<section class="block" aria-labelledby="aff-h">
  <h2 id="aff-h"><?= icon('heart') ?> Your affinities</h2>
  <p class="note">Computed from <?= e(implode(', ', array_map(static fn (string $k): string => $refName[$k], array_keys($view['refs'])))) ?> with traditional aspects, ruling-planet friendships and modalities.</p>
  <h3>Signs most in tune with you</h3>
  <div class="trio">
    <?php foreach ($aff['mostAffine'] as $i => $s) { $entry($s, 'affinity', '#' . ($i + 1)); } ?>
  </div>
  <h3>The sign of the love of your life</h3>
  <div class="trio">
    <?php $entry($aff['soulmate'], 'love', 'Heart sign'); ?>
  </div>
  <p class="note">Tradition, offered for wonder: love is not decided by a sign.</p>
</section>

<section class="block" aria-labelledby="bio-h">
  <h2 id="bio-h"><?= icon('bolt') ?> Your biorhythms today</h2>
  <?php foreach ($bio as $name => $c): ?>
    <div class="bio-row">
      <h3><?= e(ucfirst($name)) ?> <small>(<?= e($c['period']) ?> days)</small></h3>
      <p><meter min="-100" max="100" value="<?= e($c['value']) ?>"><?= e($c['value']) ?></meter>
        <strong><?= e($c['value']) ?>%</strong>, <?= e($c['trend']) ?>.
        Next peak <?= e($c['nextPeak']) ?>, next low <?= e($c['nextTrough']) ?>.</p>
    </div>
  <?php endforeach; ?>
  <?php
  bio_svg(
      array_map(static fn (string $n): array => ['class' => $bioClass[$n], 'curve' => $bio[$n]['curve']], array_keys($bio)),
      'Biorhythm curves for the next 30 days. Today: ' . implode(', ', array_map(static fn (string $n): string => $n . ' ' . $bio[$n]['value'], array_keys($bio)))
  );
  ?>
  <ul class="legend">
    <?php foreach ($bioClass as $n => $cl): ?>
      <li><span class="swatch <?= e($cl) ?>" aria-hidden="true"></span><?= e(ucfirst($n)) ?></li>
    <?php endforeach; ?>
  </ul>
  <p class="note">Next 30 days from <?= e($today) ?>. Biorhythms are a popular theory, not scientifically validated.</p>
</section>

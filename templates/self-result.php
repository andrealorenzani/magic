<?php
/** @var array $view @var string $today @var ?array $share */

use Magic\Content\Bodies;
use Magic\Content\Houses;
use Magic\Content\Signs;

require_once __DIR__ . '/partials/icons.php';
require_once __DIR__ . '/partials/help.php';
require_once __DIR__ . '/partials/bio.php';
require_once __DIR__ . '/partials/wheel.php';

$chart = $view['chart'];
$aff = $view['affinity'];
$bio = $view['bio'];
$refName = ['sun' => 'Sun', 'moon' => 'Moon', 'ascendant' => 'Ascendant', 'venus' => 'Venus', 'mars' => 'Mars'];
$houseLine = static fn (int $n): string => 'House ' . $n . ' - ' . Houses::THEMES[$n]['title'];
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
<?php $who = (string) ($view['name'] ?? ''); ?>
<div class="print-only print-head">
  <h2>Magic · Self discovery<?= $who !== '' ? ' for ' . e($who) : '' ?></h2>
  <p>Reading of <?= e($today) ?></p>
</div>
<?php if (!empty($view['dayZone'])): ?>
<p class="note">Today for you: <?= e($today) ?> (time zone <?= e($view['dayZone']) ?>)</p>
<?php endif; ?>
<?php if ($who !== ''): ?><h2 class="result-title no-print">Self discovery for <?= e($who) ?></h2><?php endif; ?>
<div class="toolbar no-print">
  <button type="button" class="print-btn" data-print hidden><?= icon('printer') ?> Print</button>
</div>

<div class="trio">
<?php foreach (['sun', 'ascendant', 'moon'] as $role): $p = $chart[$role]; $r = Signs::ROLES[$role]; ?>
  <article class="card card--<?= e(strtolower($p['element'])) ?>">
    <div class="card__symbol" aria-hidden="true"><?= e($p['symbol']) ?></div>
    <div class="head"><h3 class="card__role"><?= e($r['title']) ?></h3><?php help_button('self.' . $role); ?></div>
    <p class="card__sign"><?= e($p['name']) ?></p>
    <p class="card__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?> · <?= e($p['modality']) ?></p>
    <p class="card__tag"><?= e($r['tagline']) ?></p>
    <p class="card__text"><?= e(Signs::TEXT[$p['id']]) ?></p>
    <?php if (isset($chart['houses'][$role])): ?><p class="card__house"><?= e($houseLine($chart['houses'][$role])) ?></p><?php endif; ?>
  </article>
<?php endforeach; ?>
</div>
<?php $born = $view['moonAtBirth']; ?>
<div class="head head--center"><p class="born"><span aria-hidden="true"><?= e($born['symbol']) ?></span> Born under a <strong><?= e($born['phaseName']) ?></strong> (<?= e($born['illumination']) ?>% lit)</p><?php help_button('self.born_under'); ?></div>

<?php
$wheelRows = [['id' => 'sun', 'p' => $chart['sun'], 'h' => $chart['houses']['sun']], ['id' => 'moon', 'p' => $chart['moon'], 'h' => $chart['houses']['moon']],
    ['id' => 'ascendant', 'p' => $chart['ascendant'], 'h' => 1], ['id' => 'midheaven', 'p' => $chart['midheaven'], 'h' => $chart['houses']['midheaven']]];
foreach ($chart['planets'] as $id => $pl) {
    $wheelRows[] = ['id' => $id, 'p' => $pl['position'], 'h' => $chart['houses'][$id]];
}
$wheelRows[] = ['id' => 'node', 'p' => $chart['node'], 'h' => $chart['houses']['node']];
$mc = $chart['midheaven'];
?>
<section class="block chartwheel" aria-labelledby="wheel-h">
  <div class="head"><h2 id="wheel-h"><?= icon('bolt') ?> Your chart wheel</h2><?php help_button('self.wheel'); ?></div>
  <div class="chartwheel__layout">
    <details class="chartwheel__more" open>
    <summary>Chart wheel</summary>
    <figure class="chartwheel__figure">
      <?php wheel_svg($view['wheel'], 'Chart wheel with the Ascendant on the left. ' . implode(', ', array_map(static fn (array $r): string => Bodies::INFO[$r['id']]['title'] . ' in ' . $r['p']['name'] . ' (house ' . $r['h'] . ')', $wheelRows))); ?>
      <figcaption class="note">Ascendant (AC) on the left, Midheaven (MC) near the top. Glyphs close together are spread apart on the drawing; the table has the exact positions.</figcaption>
    </figure>
    </details>
    <div class="table-wrap">
      <table class="chartwheel__table">
        <caption class="sr-only">Position and house of each body</caption>
        <thead><tr><th scope="col">Body</th><th scope="col">Sign</th><th scope="col">Position</th><th scope="col">House</th></tr></thead>
        <tbody>
          <?php foreach ($wheelRows as $r): ?>
            <tr>
              <th scope="row"><span aria-hidden="true"><?= e(Bodies::INFO[$r['id']]['glyph']) ?></span> <?= e(Bodies::INFO[$r['id']]['title']) ?></th>
              <td><span aria-hidden="true"><?= e($r['p']['symbol']) ?></span> <?= e($r['p']['name']) ?></td>
              <td><?= e($r['p']['degree']) ?>&deg;<?= e(sprintf('%02d', $r['p']['minute'])) ?>&prime;</td>
              <td><?= e($r['h']) ?> <small><?= e(Houses::THEMES[$r['h']]['title']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="head"><p><strong><?= e(Bodies::INFO['midheaven']['title']) ?> in <?= e($mc['name']) ?>.</strong> <?= e(Bodies::INFO['midheaven']['meaning']) ?> <?= e(Signs::TEXT[$mc['id']]) ?></p><?php help_button('self.midheaven'); ?></div>
  <div class="head"><p class="note">Whole-sign houses: the sign of the Ascendant is the first house, the next sign the second, and so on.<?= $chart['polar'] ? ' Beyond the polar circle the Ascendant, Midheaven and houses are only approximate.' : '' ?></p><?php help_button('self.houses'); ?></div>
</section>

<?php if ($chart['planetsSupported']): ?>
<section class="block" aria-labelledby="planets-h">
  <h2 id="planets-h"><?= icon('bolt') ?> The planets</h2>
  <div class="planets">
    <?php foreach ($chart['planets'] as $id => $pl): $p = $pl['position']; $b = Bodies::INFO[$id]; ?>
      <article class="planet card--<?= e(strtolower($p['element'])) ?>">
        <span class="planet__glyph" aria-hidden="true"><?= e($b['glyph']) ?></span>
        <div class="head"><h3><?= e($b['title']) ?><?php if ($pl['retrograde']): ?> <abbr class="badge" title="Retrograde">R</abbr><?php endif; ?></h3><?php help_button('self.planet.' . $id); ?></div>
        <p class="planet__sign"><span aria-hidden="true"><?= e($p['symbol']) ?></span> <?= e($p['name']) ?></p>
        <p class="planet__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?></p>
        <p class="planet__house"><?= e($houseLine($chart['houses'][$id])) ?></p>
        <p class="planet__text"><?= e($b['meaning']) ?></p>
      </article>
    <?php endforeach; ?>
    <?php $p = $chart['node']; $b = Bodies::INFO['node']; ?>
    <article class="planet card--<?= e(strtolower($p['element'])) ?>">
      <span class="planet__glyph" aria-hidden="true"><?= e($b['glyph']) ?></span>
      <div class="head"><h3><?= e($b['title']) ?></h3><?php help_button('self.node'); ?></div>
      <p class="planet__sign"><span aria-hidden="true"><?= e($p['symbol']) ?></span> <?= e($p['name']) ?></p>
      <p class="planet__pos"><?= e($p['degree']) ?>°<?= e(sprintf('%02d', $p['minute'])) ?>′ · <?= e($p['element']) ?></p>
      <p class="planet__house"><?= e($houseLine($chart['houses']['node'])) ?></p>
      <p class="planet__text"><?= e($b['meaning']) ?></p>
    </article>
  </div>
  <div class="head"><p class="note">Planet positions are approximate (a fraction of a degree); a sign can be off near its boundary. <abbr class="badge" title="Retrograde">R</abbr> = apparently moving backwards.</p><?php help_button('self.retrograde'); ?></div>
</section>
<?php endif; ?>

<?php $sky = $view['sky']; $skyMode = 'self'; include __DIR__ . '/partials/sky.php'; ?>

<?php $geo = $view['geo'] ?? null; include __DIR__ . '/partials/geo.php'; ?>

<section class="block" aria-labelledby="aff-h">
  <h2 id="aff-h"><?= icon('heart') ?> Your affinities</h2>
  <p class="note">Computed from <?= e(implode(', ', array_map(static fn (string $k): string => $refName[$k], array_keys($view['refs'])))) ?> with traditional aspects, ruling-planet friendships and modalities.</p>
  <div class="head"><h3>Signs most in tune with you</h3><?php help_button('self.affinity.signs'); ?></div>
  <div class="trio">
    <?php foreach ($aff['mostAffine'] as $i => $s) { $entry($s, 'affinity', '#' . ($i + 1)); } ?>
  </div>
  <div class="head"><h3>The sign of the love of your life</h3><?php help_button('self.affinity.soulmate'); ?></div>
  <div class="trio">
    <?php $entry($aff['soulmate'], 'love', 'Heart sign'); ?>
  </div>
  <p class="note">Tradition, offered for wonder: love is not decided by a sign.</p>
</section>

<section class="block" aria-labelledby="bio-h">
  <h2 id="bio-h"><?= icon('bolt') ?> Your biorhythms today</h2>
  <?php foreach ($bio as $name => $c): ?>
    <div class="bio-row">
      <div class="head"><h3><?= e(ucfirst($name)) ?> <small>(<?= e($c['period']) ?> days)</small></h3><?php help_button('bio.' . $name); ?></div>
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

<?php if ($share !== null) { include __DIR__ . '/partials/share.php'; } ?>

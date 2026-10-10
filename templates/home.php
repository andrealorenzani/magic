<?php
/**
 * @var ?string $mode @var array $self @var array $love @var list<string> $errors @var list<string> $notes
 * @var ?array $view @var bool $submitted @var string $today @var bool $consented @var string $returnQuery
 * @var ?string $onOverride @var bool $noAudit @var string $consentAction @var bool $withdrawn @var bool $queryDropped
 * @var ?array $share @var ?array $fixedDay @var bool $hidden @var ?string $hiddenCode
 * @var ?string $nick @var ?array $pendingHidden @var bool $aKnown @var ?string $loveLink
 */

require_once __DIR__ . '/partials/icons.php';
require_once __DIR__ . '/partials/help.php';

$titles = ['love' => 'Soul Affinity', 'self' => 'Self Discovery', 'friends' => 'Friends hidden codes'];
$title = $titles[$mode] ?? 'Sun, Ascendant & Moon';
// Self Discovery is proven by a valid Self result or by valid details of "you" in a Soul Affinity request; the script unlocks the rest from the stored entry.
$unlocked = ($mode === 'self' && $view !== null) || ($mode === 'love' && $aKnown);
$selfHref = '?mode=self' . ($hiddenCode !== null ? '&h=' . rawurlencode($hiddenCode) . ($nick !== null ? '&nick=' . rawurlencode($nick) : '') : '');
$cards = [
    ['mode' => 'love', 'name' => 'Soul Affinity', 'glyph' => '♀', 'icon' => 'heart', 'text' => 'Name affinity, biorhythm synchrony, common signs and a tarot spread.', 'href' => ($unlocked && $loveLink !== null) ? $loveLink : '?mode=love'],
    ['mode' => 'friends', 'name' => 'Friends hidden codes', 'glyph' => '✦', 'icon' => 'bulb', 'text' => 'Hidden codes your friends shared with you.', 'href' => '?mode=friends'],
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Magic · <?= e($title) ?></title>
  <meta name="description" content="Discover your planets, signs and biorhythms, or find your affinity with another soul: name affinity, biorhythm synchrony, common signs and a Past, Present and Future tarot spread.">
  <?php if ($submitted): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="stars" aria-hidden="true"></div>
  <?php if (!$consented): ?>
  <div class="gate" role="dialog" aria-modal="true" aria-labelledby="gate-h" aria-describedby="gate-terms">
    <form class="gate__box" method="post" action="<?= e($consentAction) ?>">
      <h2 id="gate-h">Terms and Conditions</h2>
      <?php if ($withdrawn): ?><p class="note note--gate">You withdrew your acceptance.</p><?php endif; ?>
      <?php if ($queryDropped): ?><p class="note note--gate">Your link could not be kept, please open it again after accepting.</p><?php endif; ?>
      <div id="gate-terms" class="gate__terms" tabindex="0" role="region" aria-label="Terms and Conditions text"><?php include __DIR__ . '/partials/terms.php'; ?></div>
      <input type="hidden" name="next" value="<?= e($returnQuery) ?>">
      <div class="gate__actions">
        <button type="submit" class="btn--primary" name="action" value="accept" autofocus>I accept the Terms and Conditions</button>
      </div>
      <p class="hint">Without accepting you cannot use this page. Your browser must allow cookies to remember your choice.</p>
    </form>
  </div>
  <?php endif; ?>
  <div id="page"<?= $consented ? '' : ' inert aria-hidden="true"' ?><?= $withdrawn ? ' data-forget-memory' : '' ?>>
  <main>
    <header class="hero no-print">
      <p class="hero__eyebrow">✦ Magic ✦</p>
      <h1>Read your sky</h1>
      <p class="hero__lead">Discover yourself, or find out how you and someone you love fit together.</p>
    </header>

    <nav class="chooser no-print" aria-label="Choose a mode">
      <a class="chooser__card<?= $mode === 'self' ? ' is-active' : '' ?>" href="?mode=self"<?= $mode === 'self' ? ' aria-current="page"' : '' ?>>
        <span class="chooser__icon" aria-hidden="true">☉ <?= icon('bulb') ?></span>
        <strong>Self Discovery</strong>
        <?php if ($mode === null): ?><span class="chooser__badge">Start here</span><?php endif; ?>
        <span>Your planets, the signs closest to you, and your biorhythms.</span>
      </a>
      <?php foreach ($cards as $c): ?>
      <a class="chooser__card<?= $mode === $c['mode'] ? ' is-active' : '' ?><?= $unlocked ? '' : ' is-locked' ?>" href="<?= e($unlocked ? $c['href'] : $selfHref) ?>"<?= $mode === $c['mode'] ? ' aria-current="page"' : '' ?><?= $unlocked ? '' : ' aria-disabled="true"' ?> data-needs-self data-unlock-href="<?= e($c['href']) ?>" data-lock-href="<?= e($selfHref) ?>">
        <span class="chooser__icon" aria-hidden="true"><?= e($c['glyph']) ?> <?= icon($c['icon']) ?></span>
        <strong><?= e($c['name']) ?></strong>
        <span data-lock-text<?= $unlocked ? ' hidden' : '' ?>>Self Discovery fields are required to unlock this section.</span>
        <span data-unlock-text<?= $unlocked ? '' : ' hidden' ?>><?= e($c['text']) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>

    <?php if ($mode === 'self'): ?>
      <form id="birth-form" class="panel no-print" method="get" data-memory="self" action="./#results" autocomplete="off">
        <input type="hidden" name="mode" value="self">
        <?php if ($onOverride !== null): ?><input type="hidden" name="on" value="<?= e($onOverride) ?>"><?php endif; ?>
        <?php if ($noAudit): ?><input type="hidden" name="noaudit" value=""><?php endif; ?>
        <?php if ($pendingHidden !== null): ?>
        <div class="pending" data-pending-friend>
          <input type="hidden" name="h" value="<?= e($pendingHidden['code']) ?>">
          <p class="note">A friend shared hidden data with you. Fill in Self Discovery and press Reveal my sky or Save the data: it will be added to Friends hidden codes.</p>
          <div class="field">
            <div class="field__head"><label for="pending-nick">Nickname for this friend <small>(optional)</small></label> <?php help_term('field.nick', 'What is this?'); ?></div>
            <input id="pending-nick" name="nick" type="text" maxlength="40" autocomplete="off" value="<?= e($pendingHidden['nick'] ?? '') ?>">
          </div>
        </div>
        <?php endif; ?>
        <div class="split">
          <div class="split__main">
            <p class="hint">* required</p>
            <?php $pf = ['prefix' => '', 'required' => true, 'name' => true, 'nameRequired' => false, 'values' => $self, 'legend' => 'Your birth', 'person' => 'me']; include __DIR__ . '/partials/person-fields.php'; ?>
            <?php $memoryKind = 'self'; include __DIR__ . '/partials/memory.php'; ?>
          </div>
          <?php include __DIR__ . '/partials/self-actions.php'; ?>
        </div>
      </form>
    <?php elseif ($mode === 'love'): ?>
      <form id="love-form" class="panel no-print" method="get" data-memory="love" action="./#results" autocomplete="off">
        <input type="hidden" name="mode" value="love">
        <?php if ($onOverride !== null): ?><input type="hidden" name="on" value="<?= e($onOverride) ?>"><?php endif; ?>
        <?php if ($noAudit): ?><input type="hidden" name="noaudit" value=""><?php endif; ?>
        <?php if ($hidden): ?><input type="hidden" name="h" value="<?= e($hiddenCode) ?>"><?php if ($nick !== null): ?><input type="hidden" name="nick" value="<?= e($nick) ?>"><?php endif; endif; ?>
        <div class="lock" data-love-locked<?= $aKnown ? ' hidden' : '' ?>>
          <p class="note">Self Discovery fields are required to unlock this section.</p>
          <p><a href="<?= e($selfHref) ?>">Go to Self Discovery</a></p>
          <p class="note" data-nostore hidden>This browser does not allow storage, so Self Discovery data cannot be remembered here. Use a link that carries your details.</p>
          <noscript><p class="hint">Starting Soul Affinity from your stored details needs JavaScript. A link that carries your details works without it.</p></noscript>
        </div>
        <div data-love-body<?= $aKnown ? '' : ' hidden' ?>>
          <?php $carried = $love['a']; include __DIR__ . '/partials/person-carried.php'; ?>
          <p class="hint" data-carried-notice hidden>These results use the details from the link. <button type="button" class="linklike" data-carried-use>Use my Self Discovery data</button></p>
          <p class="hint">* required</p>
          <div class="people split<?= $hidden ? '' : ' split--two' ?>">
            <?php if ($hidden): ?>
              <?php include __DIR__ . '/partials/hidden-person.php'; ?>
            <?php else: ?>
              <?php $pf = ['prefix' => 'b_', 'required' => false, 'name' => true, 'nameRequired' => true, 'values' => $love['b'], 'legend' => "Other soul's info", 'person' => 'loved']; include __DIR__ . '/partials/person-fields.php'; ?>
              <?php include __DIR__ . '/partials/import.php'; ?>
            <?php endif; ?>
          </div>
          <p class="hint">Names and dates appear in the address bar; share the link only with people you trust.</p>
          <?php $memoryKind = 'love'; $memorySaved = !$hidden; include __DIR__ . '/partials/memory.php'; ?>
          <button type="submit" class="btn--primary">Explore our connection</button>
        </div>
      </form>
    <?php elseif ($mode === 'friends'): ?>
      <?php include __DIR__ . '/partials/friends.php'; ?>
    <?php endif; ?>

    <section id="results" class="results">
      <?php foreach ($errors as $err): ?>
        <p class="note note--error" aria-live="polite"><?= e($err) ?></p>
      <?php endforeach; ?>
      <?php if ($fixedDay !== null): ?>
        <p class="note">This reading is fixed to <?= e($fixedDay['date']) ?>.<?php if ($fixedDay['live'] !== null): ?> <a href="<?= e($fixedDay['live']) ?>">Open the live version</a>.<?php endif; ?></p>
      <?php endif; ?>
      <?php if ($view && $mode === 'self'): include __DIR__ . '/self-result.php'; endif; ?>
      <?php if ($view && $mode === 'love'): include __DIR__ . '/love-result.php'; endif; ?>
      <?php foreach ($notes as $n): ?>
        <p class="note"><?= e($n) ?></p>
      <?php endforeach; ?>
    </section>
  </main>
  <footer class="no-print">Tropical zodiac · planets are approximate · astrology, biorhythms, name affinity and tarot are for wonder and entertainment, not advice</footer>
  <section id="terms" class="no-print notice">
    <details>
      <summary>Terms and Conditions</summary>
      <?php include __DIR__ . '/partials/terms.php'; ?>
      <?php if ($consented): ?>
      <form method="post" action="<?= e($consentAction) ?>" data-memory-forget-on-submit><button type="submit" name="action" value="withdraw">Withdraw my acceptance and clear the cookie</button></form>
      <?php endif; ?>
    </details>
  </section>
  <?php include __DIR__ . '/partials/confirm.php'; ?>
  </div>
  <script src="assets/autocomplete.js" defer></script>
  <script src="assets/memory.js" defer></script>
  <script src="assets/print.js" defer></script>
  <script src="assets/share.js" defer></script>
  <script src="assets/help.js" defer></script>
  <script src="assets/import.js" defer></script>
</body>
</html>

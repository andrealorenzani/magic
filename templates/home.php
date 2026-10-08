<?php
/**
 * @var ?string $mode @var array $self @var array $love @var list<string> $errors @var list<string> $notes
 * @var ?array $view @var bool $submitted @var string $today
 */

require_once __DIR__ . '/partials/icons.php';

$onOverride = isset($_GET['on']) && is_string($_GET['on']) && $_GET['on'] === $today ? $today : null;
$title = $mode === 'love' ? 'Love' : ($mode === 'self' ? 'Self discovery' : 'Sun, Ascendant & Moon');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Arcana · <?= e($title) ?></title>
  <meta name="description" content="Discover your planets, signs and biorhythms, or compare two people: name affinity, biorhythm synchrony, common signs and a three-day tarot reading.">
  <?php if ($submitted): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="stars" aria-hidden="true"></div>
  <main>
    <header class="hero no-print">
      <p class="hero__eyebrow">✦ Arcana ✦</p>
      <h1>Read your sky</h1>
      <p class="hero__lead">Discover yourself, or find out how you and someone you love fit together.</p>
    </header>

    <nav class="chooser no-print" aria-label="Choose a mode">
      <a class="chooser__card<?= $mode === 'self' ? ' is-active' : '' ?>" href="?mode=self"<?= $mode === 'self' ? ' aria-current="page"' : '' ?>>
        <span class="chooser__icon" aria-hidden="true">☉ <?= icon('bulb') ?></span>
        <strong>Self discovery</strong>
        <span>Your planets, the signs closest to you, and your biorhythms.</span>
      </a>
      <a class="chooser__card<?= $mode === 'love' ? ' is-active' : '' ?>" href="?mode=love"<?= $mode === 'love' ? ' aria-current="page"' : '' ?>>
        <span class="chooser__icon" aria-hidden="true">♀ <?= icon('heart') ?></span>
        <strong>Love</strong>
        <span>Name affinity, biorhythm synchrony, common signs and a tarot reading.</span>
      </a>
    </nav>

    <?php if ($mode === 'self'): ?>
      <form id="birth-form" class="panel no-print" method="get" action="./#results" autocomplete="off">
        <input type="hidden" name="mode" value="self">
        <?php if ($onOverride !== null): ?><input type="hidden" name="on" value="<?= e($onOverride) ?>"><?php endif; ?>
        <?php $pf = ['prefix' => '', 'required' => true, 'name' => false, 'values' => $self, 'legend' => 'Your birth']; include __DIR__ . '/partials/person-fields.php'; ?>
        <button type="submit">Reveal my sky</button>
      </form>
    <?php elseif ($mode === 'love'): ?>
      <form id="love-form" class="panel no-print" method="get" action="./#results" autocomplete="off">
        <input type="hidden" name="mode" value="love">
        <?php if ($onOverride !== null): ?><input type="hidden" name="on" value="<?= e($onOverride) ?>"><?php endif; ?>
        <div class="people">
          <?php $pf = ['prefix' => 'a_', 'required' => true, 'name' => true, 'values' => $love['a'], 'legend' => 'You']; include __DIR__ . '/partials/person-fields.php'; ?>
          <?php $pf = ['prefix' => 'b_', 'required' => false, 'name' => true, 'values' => $love['b'], 'legend' => 'The person you love', 'hint' => 'Only the name is required. Add the birth date for signs and biorhythms; add time and city too for the Ascendant.']; include __DIR__ . '/partials/person-fields.php'; ?>
        </div>
        <p class="hint">Names and dates appear in the address bar; share the link only with people you trust.</p>
        <button type="submit">Explore our connection</button>
      </form>
    <?php endif; ?>

    <section id="results" class="results" aria-live="polite">
      <?php foreach ($errors as $err): ?>
        <p class="note note--error"><?= e($err) ?></p>
      <?php endforeach; ?>
      <?php if ($view && $mode === 'self'): include __DIR__ . '/self-result.php'; endif; ?>
      <?php if ($view && $mode === 'love'): include __DIR__ . '/love-result.php'; endif; ?>
      <?php foreach ($notes as $n): ?>
        <p class="note"><?= e($n) ?></p>
      <?php endforeach; ?>
    </section>
  </main>
  <footer class="no-print">Tropical zodiac · planets are approximate · astrology, biorhythms, name affinity and tarot are for wonder and entertainment, not advice</footer>
  <p class="no-print notice">Each result request is recorded in an audit log together with what was entered (names, birth date, time and place, including the details of the person you love in the Love mode) and a summary of the result. No IP address and no cookies are stored. The stored data is kept by the site owner and can be removed on request.</p>
  <script src="assets/autocomplete.js" defer></script>
  <script src="assets/print.js" defer></script>
</body>
</html>

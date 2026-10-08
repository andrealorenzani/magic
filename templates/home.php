<?php
/** @var array $form @var ?array $chart @var list<string> $errors @var list<string> $notes */

use Arcana\Content\Signs;

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Arcana · Sun, Ascendant &amp; Moon</title>
  <meta name="description" content="Enter your birth date, city and time to discover your sun sign, ascendant and moon sign.">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="stars" aria-hidden="true"></div>
  <main>
    <header class="hero">
      <p class="hero__eyebrow">✦ Arcana ✦</p>
      <h1>Read your sky</h1>
      <p class="hero__lead">Tell us when and where you were born, and we'll reveal your Sun, Ascendant and Moon.</p>
    </header>

    <form id="birth-form" class="panel" method="get" action="./#results" autocomplete="off">
      <label>Birth date
        <input id="date" name="date" type="date" required min="1000-01-01" max="2100-12-31" value="<?= e($form['date']) ?>">
      </label>
      <label>Birth time <small>(hh:mm, local time)</small>
        <input id="time" name="time" type="time" required value="<?= e($form['time']) ?>">
      </label>
      <label class="city">Birth city
        <input id="city" name="city" type="text" required maxlength="80" placeholder="Start typing… e.g. Rome"
               role="combobox" aria-expanded="false" aria-controls="city-list" value="<?= e($form['city']) ?>">
        <ul id="city-list" role="listbox" hidden></ul>
      </label>
      <input id="lat" name="lat" type="hidden" value="<?= e($form['lat']) ?>">
      <input id="lon" name="lon" type="hidden" value="<?= e($form['lon']) ?>">
      <input id="tz" name="tz" type="hidden" value="<?= e($form['tz']) ?>">
      <button type="submit">Reveal my signs</button>
      <p id="status" role="status" aria-live="polite"></p>
    </form>

    <section id="results" class="results" aria-live="polite">
      <?php foreach ($errors as $err): ?>
        <p class="note note--error"><?= e($err) ?></p>
      <?php endforeach; ?>
      <?php if ($chart): ?>
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
        <?php foreach ($notes as $n): ?>
          <p class="note"><?= e($n) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
  <footer>Tropical zodiac · for wonder, not medical or financial advice</footer>
  <script src="assets/autocomplete.js" defer></script>
</body>
</html>

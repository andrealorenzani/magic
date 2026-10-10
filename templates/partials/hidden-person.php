<?php
/**
 * Replaces the loved-person fields while hidden details are loaded. Nothing about that person is printed here.
 * @var ?string $hiddenCode @var ?string $nick the receiver's own label, shown instead of "Your match"
 */
require_once __DIR__ . '/help.php';
?>
<div class="hiddenperson" data-hidden-person>
  <p><strong><?= e($nick ?? 'Your match') ?></strong></p>
  <p>Details shared with you are loaded and hidden. You will only see the match results.</p>
  <p><a href="./?mode=love">Remove them</a></p>
</div>

<?php
/**
 * Share section: frozen link, QR (or a too-long note) and the live link.
 * @var array{frozen:string, live:string, qr:?array, tooLong:bool} $share
 */

require_once __DIR__ . '/qr.php';
?>
<section class="block share" aria-labelledby="share-h">
  <h2 id="share-h"><?= icon('bolt') ?> Share this reading</h2>
  <div class="toolbar no-print">
    <button type="button" data-copy="share-link" hidden>Copy link</button>
    <button type="button" data-share="share-link" hidden>Share…</button>
  </div>
  <?php if ($share['qr'] !== null): ?>
    <div class="share__qr"><?php qr_svg($share['qr'], 'QR code of the link'); ?></div>
  <?php elseif ($share['tooLong']): ?>
    <p class="hint">This link is too long for a QR code. Long names with many non-Latin characters make links longer; the link still works.</p>
  <?php endif; ?>
  <details class="share__more">
    <summary>Show the link</summary>
    <label class="share__label">Link to this exact reading
      <input type="text" id="share-link" class="share__input" readonly value="<?= e($share['frozen']) ?>">
    </label>
    <p class="share__live">Link to a live reading (today's values, same people): <a href="<?= e($share['live']) ?>"><?= e($share['live']) ?></a></p>
    <p class="note">The link contains the names and birth details you entered. Share it only with people you trust.</p>
  </details>
</section>

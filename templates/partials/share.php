<?php
/**
 * Share section: frozen link, QR (or a too-long note) and the live link.
 * @var array{frozen:string, live:string, qr:?array, tooLong:bool} $share
 */

require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/help.php';
?>
<section class="block share" aria-labelledby="share-h">
  <h2 id="share-h"><?= icon('bolt') ?> Share this reading</h2>
  <div class="toolbar no-print">
    <button type="button" data-copy="share-link" hidden>Copy link</button>
    <button type="button" data-share="share-link" hidden>Share…</button>
  </div>
  <?php if ($share['qr'] !== null): ?>
    <details class="share__qrbox" open>
      <summary>QR code</summary>
      <div class="head"><p class="hint" data-qr-hint hidden>Click the QR code to copy the link.</p><?php help_button('share.qr'); ?></div>
      <div class="share__qr" data-qr-copy="share-link"><?php qr_svg($share['qr'], 'QR code of the link'); ?></div>
    </details>
  <?php elseif ($share['tooLong']): ?>
    <p class="hint">This link is too long for a QR code. Long names with many non-Latin characters make links longer; the link still works.</p>
  <?php endif; ?>
  <p class="status" data-copy-status role="status" aria-live="polite"></p>
  <details class="share__more">
    <summary>Show the link</summary>
    <div class="field share__label">
      <div class="field__head"><label for="share-link">Link to this exact reading</label><?php help_button('share.link'); ?></div>
      <input type="text" id="share-link" class="share__input" readonly value="<?= e($share['frozen']) ?>">
    </div>
    <div class="head"><p class="share__live">Link to a live reading (today's values, same people): <a href="<?= e($share['live']) ?>"><?= e($share['live']) ?></a></p><?php help_button('share.live'); ?></div>
    <p class="note">The link contains the names and birth details you entered. Share it only with people you trust.</p>
  </details>
</section>

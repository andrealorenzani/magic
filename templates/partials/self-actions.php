<?php
/** Right column of Self Discovery: the four buttons, their status line and the hidden-code panel. */
require_once __DIR__ . '/help.php';
?>
<div class="selfactions" data-self-actions>
  <div class="selfactions__row">
    <button type="button" class="is-empty" aria-disabled="true" data-self-hidden-code>Generate hidden data code</button><?php help_button('self.hidden_code'); ?>
  </div>
  <div class="selfactions__row">
    <button type="button" class="is-empty" aria-disabled="true" data-self-clear>Clear data</button><?php help_button('self.clear'); ?>
  </div>
  <div class="selfactions__row">
    <button type="submit" data-self-reveal>Reveal my sky</button><?php help_button('self.reveal'); ?>
  </div>
  <div class="selfactions__row">
    <button type="button" data-self-save hidden>Save the data</button><?php help_button('self.save'); ?>
  </div>
  <p class="status" data-self-status role="status" aria-live="polite"></p>
  <div class="selfactions__panel" data-hidden-panel role="group" aria-labelledby="hidden-code-h" hidden>
    <h3 id="hidden-code-h">Your hidden link</h3>
    <p class="note">Anyone who gets this link or QR code can read your name, birth details and place from it. The other person's screen will not show them, but they are inside the link. Opening the link records the details in the audit like any other request. Share it only with someone you trust.</p>
    <details class="share__qrbox" open data-hidden-qrbox hidden>
      <summary>QR code</summary>
      <p class="hint">Click the QR code to copy the link.</p>
      <div class="share__qr" data-hidden-qr data-qr-copy="hidden-link"></div>
    </details>
    <div class="field">
      <label for="hidden-link">Link</label>
      <input type="text" id="hidden-link" class="share__input" readonly value="" data-hidden-link>
    </div>
    <div class="selfactions__actions"><button type="button" data-hidden-copy>Copy link</button></div>
    <p class="status" data-hidden-status role="status" aria-live="polite"></p>
  </div>
</div>

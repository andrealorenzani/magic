<?php
/** Right column of Self Discovery: the four buttons, their status line and the hidden-code panel. */
require_once __DIR__ . '/help.php';
?>
<div class="selfactions" data-self-actions>
  <div class="selfactions__row">
    <button type="button" class="is-empty" aria-disabled="true" data-self-hidden-code title="Makes a link and QR code with your details so another person can compare themselves with you.">Generate hidden data code</button>
  </div>
  <div class="selfactions__row">
    <button type="button" class="is-empty" aria-disabled="true" data-self-clear title="Erases everything this browser remembers.">Clear data</button>
  </div>
  <div class="selfactions__row">
    <button type="submit" class="btn--primary" data-self-reveal title="Opens your reading and keeps your details in this browser.">Reveal my sky</button>
  </div>
  <div class="selfactions__row">
    <button type="button" data-self-save hidden title="Keeps your details in this browser without opening the reading.">Save the data</button>
  </div>
  <p class="status" data-self-status role="status" aria-live="polite"></p>
  <div class="selfactions__panel" data-hidden-panel role="group" aria-labelledby="hidden-code-h" hidden>
    <h3 id="hidden-code-h">Your hidden link</h3>
    <p class="note">This link is not a secret. It only packs your name, birth date, time and place into a code; it is not encrypted, and anyone who has the link or the QR code can unpack it. The other person's screen will not show your details, but they are inside. Opening the link is recorded in the audit like any other request. Share it only with someone you trust.</p>
    <details class="share__qrbox" open data-hidden-qrbox hidden>
      <summary>QR code</summary>
      <p class="hint">Click the QR code to copy the link.</p>
      <div class="share__qr" data-hidden-qr data-qr-copy="hidden-link"></div>
    </details>
    <div class="field">
      <label for="hidden-link">Link</label>
      <input type="text" id="hidden-link" class="share__input" readonly value="" data-hidden-link>
    </div>
    <div class="selfactions__actions">
      <button type="button" data-hidden-copy>Copy link</button>
      <a class="btn" data-hidden-whatsapp href="#" target="_blank" rel="noopener noreferrer" hidden>Share on WhatsApp</a>
    </div>
    <p class="hint">WhatsApp will open with this link as your message. The link then also passes through WhatsApp, so send it only to someone you trust.</p>
    <p class="status" data-hidden-status role="status" aria-live="polite"></p>
  </div>
</div>

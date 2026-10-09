<?php
/**
 * Menu above the mode chooser: clean browser data (in-page confirmation) and share hidden data.
 * @var string $consentAction
 */
require_once __DIR__ . '/help.php';
?>
<details class="menu no-print" data-menu>
  <summary>Menu</summary>
  <div class="menu__body">
    <div class="menu__row" data-menu-clean-row hidden>
      <button type="button" class="menu__item" data-menu-clean>Clean browser data</button><?php help_button('menu.clean'); ?>
    </div>
    <div class="menu__panel" data-menu-clean-panel role="group" aria-labelledby="menu-clean-h" hidden>
      <h3 id="menu-clean-h" tabindex="-1">Clean browser data?</h3>
      <p>This forgets the details remembered in this browser (yours and your saved people) and your acceptance of the Terms. The Terms popup will appear again.</p>
      <div class="menu__actions">
        <button type="button" data-menu-clean-yes>Yes, clean</button>
        <button type="button" class="linklike" data-menu-clean-no>Cancel</button>
      </div>
    </div>
    <form method="post" action="<?= e($consentAction) ?>" data-menu-clean-form><input type="hidden" name="action" value="clean"></form>

    <div class="menu__row">
      <a class="menu__item" href="?mode=self" data-menu-share-hidden>Share my Self Discovery hidden data</a><?php help_button('menu.share_hidden'); ?>
    </div>
    <div class="menu__panel" data-hidden-panel role="group" aria-labelledby="menu-hidden-h" hidden>
      <h3 id="menu-hidden-h">Your hidden link</h3>
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
      <div class="menu__actions"><button type="button" data-hidden-copy>Copy link</button></div>
      <p class="status" data-hidden-status role="status" aria-live="polite"></p>
    </div>
  </div>
</details>

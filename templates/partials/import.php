<?php
/** Import of hidden details shared by another person: paste a link or code, or scan a QR code where the browser can. */
require_once __DIR__ . '/help.php';
?>
<fieldset class="import" data-import>
  <legend>Import hidden details <small>(optional)</small></legend>
  <p class="hint">Someone shared their hidden details with you? Scan their QR code or paste their link. Their name, birth date, time and city are not shown on your screen; you only see the match results. The link itself contains these details, so only use links from someone you trust.</p>
  <div class="field">
    <div class="field__head"><label for="import">Paste a link or code</label><?php help_button('field.import'); ?></div>
    <input id="import" name="import" type="text" maxlength="600" autocomplete="off" spellcheck="false" value="">
  </div>
  <div class="import__scan">
    <button type="button" data-import-scan hidden>Scan a QR code</button>
    <button type="button" class="linklike" data-import-stop hidden>Stop scanning</button>
  </div>
  <video class="import__video" data-import-video muted playsinline hidden></video>
  <p class="hint">Scanning needs a browser that supports it, such as Chrome on Android. Pasting always works.</p>
  <button type="submit" formnovalidate class="import__go">Explore with these details</button>
  <p class="status" data-import-status role="status" aria-live="polite"></p>
</fieldset>

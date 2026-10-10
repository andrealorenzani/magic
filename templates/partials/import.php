<?php
/** Import from a user: a nickname, a pasted link or code, or a scanned QR code where the browser can. */
require_once __DIR__ . '/help.php';
?>
<fieldset class="import" data-import>
  <legend>Import from a user</legend>
  <p class="hint">Someone shared their hidden details with you? Scan their QR code or paste their link. Their details are not shown on your screen; you only see the match results.</p>
  <div class="field">
    <div class="field__head"><label for="nick">Nickname <small>(optional)</small></label> <?php help_term('field.nick', 'What is this?'); ?></div>
    <input id="nick" name="nick" type="text" maxlength="40" autocomplete="off" value="">
  </div>
  <div class="field">
    <div class="field__head"><label for="import">Paste a link or code</label></div>
    <input id="import" name="import" type="text" maxlength="600" autocomplete="off" spellcheck="false" value="">
  </div>
  <div class="import__scan">
    <button type="button" data-import-scan hidden>Scan a QR code</button>
    <button type="button" class="linklike" data-import-stop hidden>Stop scanning</button>
  </div>
  <video class="import__video" data-import-video muted playsinline hidden></video>
  <p class="hint">Scanning needs a browser that supports it, such as Chrome on Android. Pasting always works. The link itself contains the details, so only use links from someone you trust.</p>
  <button type="submit" formnovalidate class="import__go">Explore with these details</button>
  <p class="status" data-import-status role="status" aria-live="polite"></p>
</fieldset>

<?php /** The one confirmation dialog, filled and opened by assets/memory.js. */ ?>
<dialog class="confirm no-print" data-confirm aria-labelledby="confirm-h">
  <div class="confirm__box">
    <h2 id="confirm-h" data-confirm-title>Are you sure?</h2>
    <p data-confirm-text></p>
    <div class="confirm__actions">
      <button type="button" class="btn--primary" data-confirm-yes>Yes, continue</button>
      <button type="button" class="linklike" data-confirm-no>Cancel</button>
    </div>
  </div>
</dialog>

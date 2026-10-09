<?php
/**
 * Browser memory controls, shown by assets/memory.js when the browser allows storage.
 * @var string $memoryKind 'self' | 'love'
 */
?>
<div class="memory no-print" data-memory-bar hidden>
  <p class="hint">Your details are remembered in this browser only.</p>
  <?php if ($memoryKind === 'love'): ?>
  <div class="saved" data-saved hidden>
    <label>Saved people
      <select data-saved-select>
        <option value="">Choose&hellip;</option>
      </select>
    </label>
    <button type="button" class="linklike" data-saved-remove>Remove from this browser</button>
  </div>
  <?php endif; ?>
  <div class="memory__actions">
    <button type="button" data-memory-save hidden>Remember these details</button>
    <button type="button" class="linklike" data-memory-forget>Forget my data</button>
  </div>
  <p class="status" data-memory-status role="status" aria-live="polite"></p>
</div>

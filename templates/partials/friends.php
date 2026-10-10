<?php
/**
 * Friends hidden codes: the list is built by assets/memory.js from the codes kept in this browser.
 * @var bool $unlocked
 */
require_once __DIR__ . '/icons.php';
?>
<section class="panel friends no-print" data-friends>
  <div class="lock" data-friends-locked<?= $unlocked ? ' hidden' : '' ?>>
    <p class="note">Self Discovery fields are required to unlock this section.</p>
    <p><a href="?mode=self">Go to Self Discovery</a></p>
    <noscript><p class="hint">This section needs JavaScript, because the codes are kept in your browser.</p></noscript>
  </div>
  <p class="note" data-nostore hidden>This browser does not allow storage, so the hidden codes of your friends cannot be kept here.</p>
  <div class="friends__body" data-friends-body<?= $unlocked ? '' : ' hidden' ?>>
    <h2 class="friends__title">Friends hidden codes</h2>
    <noscript><p class="hint">This section needs JavaScript, because the codes are kept in your browser.</p></noscript>
    <div class="friends__tools">
      <label for="friends-search" class="sr-only">Search by nickname</label>
      <input id="friends-search" class="friends__search" type="search" maxlength="40" autocomplete="off" placeholder="Search" data-friends-search>
      <p class="friends__count" data-friends-count role="status" aria-live="polite"></p>
      <div class="friends__bulk">
        <button type="button" class="iconbtn" data-friends-selectall aria-label="Select all shown" title="Select all shown"><?= icon('check-all') ?></button>
        <button type="button" class="iconbtn" data-friends-remove-selected disabled aria-label="Remove selected (0)" title="Remove selected (0)"><?= icon('trash') ?><span data-friends-selected></span></button>
      </div>
    </div>
    <ul class="friends__list" data-friends-list></ul>
    <p class="hint" data-friends-empty hidden>No hidden codes yet. When a friend shares a hidden link or QR code with you, add it in Soul Affinity (Import from a user) and it appears here.</p>
    <p class="status" data-friends-status role="status" aria-live="polite"></p>
    <template data-friends-row>
      <li class="friends__row">
        <input type="checkbox" data-friend-check aria-label="Select this friend">
        <input type="text" class="friends__nick" data-friend-nick maxlength="40" placeholder="Unnamed friend" aria-label="Nickname">
        <span class="friends__date" data-friend-date></span>
        <a class="iconbtn" data-friend-compare href="?mode=love" aria-label="Compare with this friend" title="Compare"><?= icon('compare') ?></a>
        <button type="button" class="iconbtn" data-friend-remove aria-label="Remove this friend" title="Remove"><?= icon('trash') ?></button>
      </li>
    </template>
  </div>
</section>

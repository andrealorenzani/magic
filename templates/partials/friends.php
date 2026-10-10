<?php
/**
 * Friends hidden codes: the list is built by assets/memory.js from the codes kept in this browser.
 * @var bool $unlocked
 */
require_once __DIR__ . '/help.php';
?>
<section class="panel friends no-print" data-friends>
  <div class="lock" data-friends-locked<?= $unlocked ? ' hidden' : '' ?>>
    <p class="note">Self Discovery fields are required to unlock this section.</p>
    <p><a href="?mode=self">Go to Self Discovery</a></p>
    <noscript><p class="hint">This section needs JavaScript, because the codes are kept in your browser.</p></noscript>
  </div>
  <p class="note" data-nostore hidden>This browser does not allow storage, so the hidden codes of your friends cannot be kept here.</p>
  <div class="friends__body" data-friends-body<?= $unlocked ? '' : ' hidden' ?>>
    <div class="head"><h2>Friends hidden codes</h2><?php help_button('friends.list'); ?></div>
    <noscript><p class="hint">This section needs JavaScript, because the codes are kept in your browser.</p></noscript>
    <div class="friends__tools">
      <div class="field">
        <div class="field__head"><label for="friends-search">Search by nickname</label><?php help_button('friends.search'); ?></div>
        <input id="friends-search" type="search" maxlength="40" autocomplete="off" data-friends-search>
      </div>
      <p class="hint" data-friends-count role="status" aria-live="polite"></p>
      <div class="friends__bulk">
        <button type="button" class="linklike" data-friends-selectall>Select all shown</button>
        <button type="button" data-friends-remove-selected disabled>Remove selected (0)</button>
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
        <a class="friends__compare" data-friend-compare href="?mode=love">Compare</a>
        <button type="button" class="linklike" data-friend-remove>Remove</button>
      </li>
    </template>
  </div>
</section>

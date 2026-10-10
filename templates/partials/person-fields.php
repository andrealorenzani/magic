<?php
/**
 * Birth fields for one person. Help terms sit beside the labels, never inside them.
 * @var array{prefix:string, required:bool, name:bool, nameRequired?:bool, values:array<string,string>, legend:string, hint?:string, person?:string} $pf
 */
require_once __DIR__ . '/help.php';

$p = $pf['prefix'];
$v = $pf['values'];
$req = '<span class="req" aria-hidden="true">*</span>';
$nameRequired = $pf['nameRequired'] ?? $pf['name'];
?>
<fieldset class="person"<?= isset($pf['person']) ? ' data-person="' . e($pf['person']) . '" data-prefix="' . e($p) . '"' : '' ?>>
  <legend><?= e($pf['legend']) ?></legend>
  <?php if (!empty($pf['hint'])): ?><p class="hint"><?= e($pf['hint']) ?></p><?php endif; ?>
  <?php if ($pf['name']): ?>
    <div class="field">
      <div class="field__head"><label for="<?= e($p) ?>name">Name<?= $nameRequired ? ' ' . $req : ' <small>(optional)</small>' ?></label></div>
      <input id="<?= e($p) ?>name" name="<?= e($p) ?>name" type="text"<?= $nameRequired ? ' required' : '' ?> maxlength="40" value="<?= e($v['name']) ?>">
    </div>
  <?php endif; ?>
  <div class="field">
    <div class="field__head"><label for="<?= e($p) ?>date">Birth date<?= $pf['required'] ? ' ' . $req : '' ?></label></div>
    <input id="<?= e($p) ?>date" name="<?= e($p) ?>date" type="date"<?= $pf['required'] ? ' required' : '' ?> min="1000-01-01" max="2100-12-31" value="<?= e($v['date']) ?>">
  </div>
  <div class="field">
    <div class="field__head"><label for="<?= e($p) ?>time">Birth time <small>(hh:mm, local time)</small><?= $pf['required'] ? ' ' . $req : '' ?></label></div>
    <input id="<?= e($p) ?>time" name="<?= e($p) ?>time" type="time"<?= $pf['required'] ? ' required' : '' ?> value="<?= e($v['time']) ?>">
  </div>
  <div class="place" data-place data-kind="birth">
    <div class="field city">
      <div class="field__head"><label for="<?= e($p) ?>city">Birth city<?= $pf['required'] ? ' ' . $req : '' ?></label> <?php help_term('field.city', 'Why pick a suggestion?'); ?></div>
      <input id="<?= e($p) ?>city" name="<?= e($p) ?>city" data-field="city" type="text"<?= $pf['required'] ? ' required' : '' ?> maxlength="80" placeholder="Start typing… e.g. Rome"
             role="combobox" aria-expanded="false" aria-controls="<?= e($p) ?>city-list" value="<?= e($v['city']) ?>">
      <ul id="<?= e($p) ?>city-list" data-role="list" role="listbox" hidden></ul>
    </div>
    <input name="<?= e($p) ?>lat" data-field="lat" type="hidden" value="<?= e($v['lat']) ?>">
    <input name="<?= e($p) ?>lon" data-field="lon" type="hidden" value="<?= e($v['lon']) ?>">
    <input name="<?= e($p) ?>tz" data-field="tz" type="hidden" value="<?= e($v['tz']) ?>">
    <p class="status" data-role="status" role="status" aria-live="polite"></p>
  </div>
  <div class="place" data-place data-kind="pos">
    <div class="field city">
      <div class="field__head"><label for="<?= e($p) ?>pos_city">Current city <small>(optional)</small></label> <?php help_term('field.pos_city', 'Why add it?'); ?></div>
      <input id="<?= e($p) ?>pos_city" name="<?= e($p) ?>pos_city" data-field="city" type="text" maxlength="80" placeholder="Where are you now?"
             role="combobox" aria-expanded="false" aria-controls="<?= e($p) ?>pos_city-list" value="<?= e($v['pos_city'] ?? '') ?>">
      <ul id="<?= e($p) ?>pos_city-list" data-role="list" role="listbox" hidden></ul>
    </div>
    <p class="hint">Optional. Used for your local &lsquo;today&rsquo; and for distances.</p>
    <input name="<?= e($p) ?>pos_lat" data-field="lat" type="hidden" value="<?= e($v['pos_lat'] ?? '') ?>">
    <input name="<?= e($p) ?>pos_lon" data-field="lon" type="hidden" value="<?= e($v['pos_lon'] ?? '') ?>">
    <input name="<?= e($p) ?>pos_tz" data-field="tz" type="hidden" value="<?= e($v['pos_tz'] ?? '') ?>">
    <p class="status" data-role="status" role="status" aria-live="polite"></p>
  </div>
</fieldset>

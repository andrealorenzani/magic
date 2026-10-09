<?php
/**
 * Birth fields for one person.
 * @var array{prefix:string, required:bool, name:bool, values:array<string,string>, legend:string, hint?:string, person?:string} $pf
 */
$p = $pf['prefix'];
$v = $pf['values'];
?>
<fieldset class="person"<?= isset($pf['person']) ? ' data-person="' . e($pf['person']) . '" data-prefix="' . e($p) . '"' : '' ?>>
  <legend><?= e($pf['legend']) ?></legend>
  <?php if (!empty($pf['hint'])): ?><p class="hint"><?= e($pf['hint']) ?></p><?php endif; ?>
  <?php if ($pf['name']): ?>
    <label>Name
      <input id="<?= e($p) ?>name" name="<?= e($p) ?>name" type="text" required maxlength="40" value="<?= e($v['name']) ?>">
    </label>
  <?php endif; ?>
  <label>Birth date
    <input id="<?= e($p) ?>date" name="<?= e($p) ?>date" type="date"<?= $pf['required'] ? ' required' : '' ?> min="1000-01-01" max="2100-12-31" value="<?= e($v['date']) ?>">
  </label>
  <label>Birth time <small>(hh:mm, local time)</small>
    <input id="<?= e($p) ?>time" name="<?= e($p) ?>time" type="time"<?= $pf['required'] ? ' required' : '' ?> value="<?= e($v['time']) ?>">
  </label>
  <div class="place" data-place data-kind="birth">
    <label class="city">Birth city
      <input id="<?= e($p) ?>city" name="<?= e($p) ?>city" data-field="city" type="text"<?= $pf['required'] ? ' required' : '' ?> maxlength="80" placeholder="Start typing… e.g. Rome"
             role="combobox" aria-expanded="false" aria-controls="<?= e($p) ?>city-list" value="<?= e($v['city']) ?>">
      <ul id="<?= e($p) ?>city-list" data-role="list" role="listbox" hidden></ul>
    </label>
    <input name="<?= e($p) ?>lat" data-field="lat" type="hidden" value="<?= e($v['lat']) ?>">
    <input name="<?= e($p) ?>lon" data-field="lon" type="hidden" value="<?= e($v['lon']) ?>">
    <input name="<?= e($p) ?>tz" data-field="tz" type="hidden" value="<?= e($v['tz']) ?>">
    <p class="status" data-role="status" role="status" aria-live="polite"></p>
  </div>
  <div class="place" data-place data-kind="pos">
    <label class="city">Current city <small>(optional)</small>
      <input id="<?= e($p) ?>pos_city" name="<?= e($p) ?>pos_city" data-field="city" type="text" maxlength="80" placeholder="Where are you now?"
             role="combobox" aria-expanded="false" aria-controls="<?= e($p) ?>pos_city-list" value="<?= e($v['pos_city'] ?? '') ?>">
      <ul id="<?= e($p) ?>pos_city-list" data-role="list" role="listbox" hidden></ul>
    </label>
    <p class="hint">Optional. Used for your local &lsquo;today&rsquo; and for distances.</p>
    <input name="<?= e($p) ?>pos_lat" data-field="lat" type="hidden" value="<?= e($v['pos_lat'] ?? '') ?>">
    <input name="<?= e($p) ?>pos_lon" data-field="lon" type="hidden" value="<?= e($v['pos_lon'] ?? '') ?>">
    <input name="<?= e($p) ?>pos_tz" data-field="tz" type="hidden" value="<?= e($v['pos_tz'] ?? '') ?>">
    <p class="status" data-role="status" role="status" aria-live="polite"></p>
  </div>
</fieldset>

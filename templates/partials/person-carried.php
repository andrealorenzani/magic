<?php
/**
 * Soul Affinity: the visitor's own details travel as hidden inputs (prefilled from the request or filled by assets/memory.js from the stored Self entry).
 * @var array<string,string> $carried
 */
$fields = ['name', 'date', 'time', 'city', 'lat', 'lon', 'tz', 'pos_city', 'pos_lat', 'pos_lon', 'pos_tz'];
?>
<fieldset class="carried" data-person="me" data-prefix="a_" data-carried hidden>
  <?php foreach ($fields as $f): ?><input type="hidden" name="a_<?= e($f) ?>" value="<?= e($carried[$f] ?? '') ?>">
  <?php endforeach; ?>
</fieldset>

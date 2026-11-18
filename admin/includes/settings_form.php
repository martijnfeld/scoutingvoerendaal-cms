<?php /* Verwacht de toegestane $group en $activeTab. */ ?>
<?php if (!empty($group['intro'])): ?>
<div class="admin-card">
  <?= $group['intro'] /* vaste tekst uit deze file */ ?>
</div>
<?php endif; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
    <?php foreach ($group['fields'] as $key => $field):
        $value = get_setting($key);
    ?>
    <div class="field">
      <label for="f_<?= e($key) ?>"><?= e($field['label']) ?></label>
      <?php if ($field['type'] === 'html'): ?>
        <textarea class="rich-text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="6"><?= e($value) ?></textarea>
      <?php elseif ($field['type'] === 'textarea'): ?>
        <textarea id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($value) ?></textarea>
      <?php elseif ($field['type'] === 'checkbox'): ?>
        <label class="field-inline-label"><input type="checkbox" id="f_<?= e($key) ?>" name="<?= e($key) ?>" <?= $value === '1' ? 'checked' : '' ?>> Ingeschakeld</label>
      <?php elseif ($field['type'] === 'image'): ?>
        <?php if ($value): ?><div style="margin-bottom:8px;"><img src="../<?= e($value) ?>" alt="" class="thumb" style="width:120px; height:90px;"></div><?php endif; ?>
        <input type="text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <select class="file-picker" data-target="f_<?= e($key) ?>" style="margin-top:6px;">
          <option value="">— of kies een reeds geüpload bestand —</option>
          <?php foreach ($existingImages as $img): ?>
          <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Of upload hieronder een nieuw bestand:</span>
        <input type="file" name="upload_<?= e($key) ?>" accept="image/*" style="margin-top:6px;">
      <?php else: ?>
        <input type="text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>">
      <?php endif; ?>
      <?php if (!empty($field['hint'])): ?><span class="hint"><?= e($field['hint']) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn">Opslaan</button>
  </form>
</div>

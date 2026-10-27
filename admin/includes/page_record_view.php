<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="field-row">
      <div class="field">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" value="<?= e($values['titel']) ?>" required>
      </div>
      <div class="field">
        <label for="slug">Slug (URL)</label>
        <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" placeholder="wordt automatisch aangemaakt op basis van de titel">
        <span class="hint">De pagina is bereikbaar op <code>/slug</code>. Leeg laten = automatisch op basis van de titel.</span>
      </div>
    </div>

    <div class="field">
      <label for="inhoud">Inhoud</label>
      <textarea class="rich-text" id="inhoud" name="inhoud" rows="16"><?= e($values['inhoud']) ?></textarea>
      <span class="hint">Je kunt hier ook afbeeldingen uploaden en YouTube-video's plakken (gewoon de video-URL op een eigen regel).</span>
    </div>

    <div class="field">
      <label for="meta_omschrijving">Meta-omschrijving (optioneel)</label>
      <input type="text" id="meta_omschrijving" name="meta_omschrijving" value="<?= e($values['meta_omschrijving']) ?>" maxlength="255">
      <span class="hint">Getoond in zoekmachines en bij delen op social media. Leeg laten = standaard omschrijving van de website.</span>
    </div>

    <div class="field">
      <label for="og_afbeelding">Deelafbeelding (optioneel)</label>
      <?php if (!empty($values['og_afbeelding'])): ?><div style="margin-bottom:8px;"><img src="<?= e(preg_match('#^https?://#i', $values['og_afbeelding']) ? $values['og_afbeelding'] : '../' . $values['og_afbeelding']) ?>" alt="" class="thumb" style="width:120px; height:90px;"></div><?php endif; ?>
      <input type="text" id="og_afbeelding" name="og_afbeelding" value="<?= e($values['og_afbeelding'] ?? '') ?>" maxlength="255">
      <select class="file-picker" data-target="og_afbeelding" style="margin-top:6px;">
        <option value="">— of kies een reeds geüpload bestand —</option>
        <?php foreach ($existingImages as $img): ?>
        <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Of upload hieronder een nieuw bestand:</span>
      <input type="file" name="upload_og_afbeelding" accept="image/*" style="margin-top:6px;">
      <span class="hint">Getoond als deze pagina wordt gedeeld (WhatsApp, Facebook, …). Bij voorkeur 1200 × 630 pixels. Leeg laten = standaard deelafbeelding van de website.</span>
    </div>

    <div class="field">
      <?php $publicationBlocked = $page && $page['actief'] && page_header_used('content', $page); ?>
      <?php if ($publicationBlocked): ?><input type="hidden" name="actief" value="1"><?php endif; ?>
      <label class="field-inline-label"><input type="checkbox" name="actief" <?= $values['actief'] ? 'checked' : '' ?><?= $publicationBlocked ? ' disabled' : '' ?>> Actief (bereikbaar op de website)</label>
      <?php if ($publicationBlocked): ?><span class="hint">Verwijder eerst de navigatiekoppelingen via <a href="settings.php?tab=header">Instellingen → Header</a> om deze pagina uit te schakelen.</span><?php endif; ?>
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="paginas.php" class="btn btn-secondary">Annuleren</a>
  </form>
</div>

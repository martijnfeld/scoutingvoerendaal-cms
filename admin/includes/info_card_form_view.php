<h2><?= e($cardFormTitle) ?></h2>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="field-row">
      <div class="field">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" value="<?= e($values['titel']) ?>" required>
      </div>
      <div class="field">
        <label for="sectie">Sectie</label>
        <input type="text" id="sectie" name="sectie" value="<?= e($values['sectie']) ?>">
        <span class="hint">Alleen sectie lidworden wordt op de homepage bij Doe mee! getoond. Andere secties blijven bewaard.</span>
      </div>
    </div>

    <div class="field">
      <label for="tekst">Tekst</label>
      <textarea class="rich-text" id="tekst" name="tekst" rows="6"><?= e($values['tekst']) ?></textarea>
    </div>

    <div class="field">
      <label for="afbeelding">Afbeelding (optioneel)</label>
      <?php if (!empty($values['afbeelding'])): ?>
        <div style="margin-bottom:8px;"><img src="../<?= e($values['afbeelding']) ?>" class="thumb" style="width:120px; height:90px;" alt=""></div>
      <?php endif; ?>
      <input type="text" id="afbeelding" name="afbeelding" value="<?= e($values['afbeelding']) ?>" placeholder="assets/uploads/....jpg">
      <select class="file-picker" data-target="afbeelding" style="margin-top:6px;">
        <option value="">— of kies een reeds geüpload bestand —</option>
        <?php foreach (get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']) as $img): ?>
        <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Of upload hieronder een nieuwe afbeelding:</span>
      <input type="file" name="upload_afbeelding" accept="image/*" style="margin-top:6px;">
    </div>

    <div class="field-row">
      <div class="field">
        <label for="document_id">Gekoppeld document (PDF)</label>
        <select id="document_id" name="document_id">
          <option value="">Geen</option>
          <?php foreach ($documents as $doc): ?>
          <option value="<?= $doc['id'] ?>" <?= (int) $values['document_id'] === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['naam']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Beheer documenten via <a href="documents.php" target="_blank">Documenten</a>.</span>
      </div>
      <div class="field">
        <label for="knop_tekst">Knoptekst voor document</label>
        <input type="text" id="knop_tekst" name="knop_tekst" value="<?= e($values['knop_tekst']) ?>" placeholder="Bijv. Inschrijfformulier (PDF)">
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="anchor">Anchor / verwijzing (optioneel)</label>
        <input type="text" id="anchor" name="anchor" value="<?= e($values['anchor']) ?>" placeholder="bv. inschrijven">
        <span class="hint">Hiermee kun je vanaf elders op de site rechtstreeks naar dit vakje linken (#anchor).</span>
      </div>
      <div class="field">
        <label for="volgorde">Volgorde</label>
        <input type="number" id="volgorde" name="volgorde" value="<?= (int) $values['volgorde'] ?>">
        <span class="hint">Laag getal = eerder in de lijst. Laat op 0 voor automatisch achteraan.</span>
      </div>
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="<?= e($editorUrl) ?>" class="btn btn-secondary">Annuleren</a>
  </form>
</div>


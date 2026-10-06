<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$card = $id ? get_info_card($id) : null;
if ($id && !$card) {
    flash_set('error', 'Info-vakje niet gevonden.');
    header('Location: info_cards.php');
    exit;
}

$pageTitle = $card ? 'Info-vakje bewerken' : 'Nieuw info-vakje';
$documents = get_documents();
$error = '';

$values = $card ?: [
    'sectie' => 'lidworden',
    'titel' => '',
    'tekst' => '',
    'afbeelding' => '',
    'document_id' => null,
    'knop_tekst' => '',
    'anchor' => '',
    'volgorde' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $values['sectie'] = trim($_POST['sectie'] ?? 'lidworden') ?: 'lidworden';
        $values['titel'] = trim($_POST['titel'] ?? '');
        $values['tekst'] = $_POST['tekst'] ?? '';
        $values['afbeelding'] = trim($_POST['afbeelding'] ?? '');
        $values['document_id'] = $_POST['document_id'] !== '' ? (int) $_POST['document_id'] : null;
        $values['knop_tekst'] = trim($_POST['knop_tekst'] ?? '');
        $values['anchor'] = trim($_POST['anchor'] ?? '');
        $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

        if ($values['titel'] === '') {
            $error = 'Titel is verplicht.';
        } else {
            try {
                $uploaded = handle_upload('upload_afbeelding', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                if ($uploaded !== null) {
                    $values['afbeelding'] = $uploaded;
                }

                if ($card) {
                    $stmt = db()->prepare(
                        'UPDATE info_cards SET sectie=:sectie, titel=:titel, tekst=:tekst, afbeelding=:afbeelding,
                         document_id=:document_id, knop_tekst=:knop_tekst, anchor=:anchor, volgorde=:volgorde WHERE id=:id'
                    );
                    $stmt->execute([
                        'sectie' => $values['sectie'], 'titel' => $values['titel'], 'tekst' => $values['tekst'],
                        'afbeelding' => $values['afbeelding'] ?: null, 'document_id' => $values['document_id'],
                        'knop_tekst' => $values['knop_tekst'] ?: null, 'anchor' => $values['anchor'] ?: null,
                        'volgorde' => $values['volgorde'], 'id' => $id,
                    ]);
                    flash_set('success', 'Info-vakje bijgewerkt.');
                } else {
                    if (!$values['volgorde']) {
                        $max = (int) db()->query("SELECT COALESCE(MAX(volgorde),0) FROM info_cards WHERE sectie = " . db()->quote($values['sectie']))->fetchColumn();
                        $values['volgorde'] = $max + 1;
                    }
                    $stmt = db()->prepare(
                        'INSERT INTO info_cards (sectie, titel, tekst, afbeelding, document_id, knop_tekst, anchor, volgorde)
                         VALUES (:sectie, :titel, :tekst, :afbeelding, :document_id, :knop_tekst, :anchor, :volgorde)'
                    );
                    $stmt->execute([
                        'sectie' => $values['sectie'], 'titel' => $values['titel'], 'tekst' => $values['tekst'],
                        'afbeelding' => $values['afbeelding'] ?: null, 'document_id' => $values['document_id'],
                        'knop_tekst' => $values['knop_tekst'] ?: null, 'anchor' => $values['anchor'] ?: null,
                        'volgorde' => $values['volgorde'],
                    ]);
                    flash_set('success', 'Info-vakje toegevoegd.');
                }
                header('Location: info_cards.php');
                exit;
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:720px;">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
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
        <span class="hint">Vakjes met dezelfde sectie staan bij elkaar gegroepeerd. Standaard: lidworden.</span>
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
    <a href="info_cards.php" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

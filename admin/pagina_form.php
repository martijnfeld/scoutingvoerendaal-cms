<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$page = $id ? get_page($id) : null;
if ($id && !$page) {
    flash_set('error', 'Pagina niet gevonden.');
    header('Location: paginas.php');
    exit;
}

$pageTitle = $page ? 'Pagina bewerken' : 'Nieuwe pagina';
$error = '';

$values = $page ?: [
    'titel' => '',
    'slug' => '',
    'inhoud' => '',
    'meta_omschrijving' => '',
    'og_afbeelding' => '',
    'in_menu' => 0,
    'actief' => 1,
    'volgorde' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $values['titel'] = trim($_POST['titel'] ?? '');
        $values['slug'] = trim($_POST['slug'] ?? '');
        $values['inhoud'] = $_POST['inhoud'] ?? '';
        $values['meta_omschrijving'] = trim($_POST['meta_omschrijving'] ?? '');
        $values['og_afbeelding'] = trim($_POST['og_afbeelding'] ?? '');
        $values['in_menu'] = isset($_POST['in_menu']) ? 1 : 0;
        $values['actief'] = isset($_POST['actief']) ? 1 : 0;
        $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

        try {
            $uploaded = handle_upload('upload_og_afbeelding', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['og_afbeelding'] = $uploaded;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error !== '') {
            // Uploadfout: melding hierboven, niets opslaan.
        } elseif ($values['titel'] === '') {
            $error = 'Titel is verplicht.';
        } elseif (strlen($values['og_afbeelding']) > 255) {
            $error = 'Het pad van de deelafbeelding is te lang (maximaal 255 tekens).';
        } else {
            $slug = unique_page_slug($values['slug'] !== '' ? $values['slug'] : $values['titel'], $id ?: null);

            if ($page) {
                $stmt = db()->prepare(
                    'UPDATE pages SET titel=:titel, slug=:slug, inhoud=:inhoud, meta_omschrijving=:meta_omschrijving,
                     og_afbeelding=:og_afbeelding, in_menu=:in_menu, actief=:actief, volgorde=:volgorde WHERE id=:id'
                );
                $stmt->execute([
                    'titel' => $values['titel'], 'slug' => $slug, 'inhoud' => $values['inhoud'],
                    'meta_omschrijving' => $values['meta_omschrijving'] ?: null,
                    'og_afbeelding' => $values['og_afbeelding'] ?: null,
                    'in_menu' => $values['in_menu'], 'actief' => $values['actief'],
                    'volgorde' => $values['volgorde'], 'id' => $id,
                ]);
                audit_log('update', 'pagina', (int) $id, 'Pagina "' . $values['titel'] . '" bijgewerkt');
                flash_set('success', 'Pagina bijgewerkt.');
            } else {
                if (!$values['volgorde']) {
                    $values['volgorde'] = (int) db()->query('SELECT COALESCE(MAX(volgorde),0) FROM pages')->fetchColumn() + 1;
                }
                $stmt = db()->prepare(
                    'INSERT INTO pages (titel, slug, inhoud, meta_omschrijving, og_afbeelding, in_menu, actief, volgorde)
                     VALUES (:titel, :slug, :inhoud, :meta_omschrijving, :og_afbeelding, :in_menu, :actief, :volgorde)'
                );
                $stmt->execute([
                    'titel' => $values['titel'], 'slug' => $slug, 'inhoud' => $values['inhoud'],
                    'meta_omschrijving' => $values['meta_omschrijving'] ?: null,
                    'og_afbeelding' => $values['og_afbeelding'] ?: null,
                    'in_menu' => $values['in_menu'], 'actief' => $values['actief'],
                    'volgorde' => $values['volgorde'],
                ]);
                audit_log('create', 'pagina', (int) db()->lastInsertId(), 'Pagina "' . $values['titel'] . '" toegevoegd');
                flash_set('success', 'Pagina toegevoegd.');
            }
            header('Location: paginas.php');
            exit;
        }
    }
}

$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:900px;">
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

    <div class="field-row">
      <div class="field">
        <label class="field-inline-label"><input type="checkbox" name="in_menu" <?= $values['in_menu'] ? 'checked' : '' ?>> Tonen in het hoofdmenu</label>
      </div>
      <div class="field">
        <label class="field-inline-label"><input type="checkbox" name="actief" <?= $values['actief'] ? 'checked' : '' ?>> Actief (bereikbaar op de website)</label>
      </div>
      <div class="field">
        <label for="volgorde">Volgorde</label>
        <input type="number" id="volgorde" name="volgorde" value="<?= (int) $values['volgorde'] ?>">
        <span class="hint">Laag getal = eerder. 0 = automatisch achteraan bij een nieuwe pagina.</span>
      </div>
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="paginas.php" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

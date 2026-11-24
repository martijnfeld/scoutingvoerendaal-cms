<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$groep = $id ? get_vrijwilliger_groep($id) : null;
if ($id && !$groep) {
    flash_set('error', 'Groep niet gevonden.');
    header('Location: vrijwilligers.php?tab=groepen');
    exit;
}

$pageTitle = $groep ? 'Groep bewerken' : 'Nieuwe groep';
$error = '';
$values = $groep ?: ['naam' => '', 'slug' => '', 'kleur' => '#00A551', 'logo' => '', 'toelichting' => '', 'actief' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $values['naam'] = trim($_POST['naam'] ?? '');
        $values['slug'] = trim($_POST['slug'] ?? '');
        $values['kleur'] = trim($_POST['kleur'] ?? '#00A551');
        $values['logo'] = trim($_POST['logo'] ?? '');
        $values['toelichting'] = $_POST['toelichting'] ?? '';
        $values['actief'] = isset($_POST['actief']) ? 1 : 0;

        try {
            $uploaded = handle_upload('upload_logo', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['logo'] = $uploaded;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error !== '') {
            // Uploadfout: melding hierboven, niets opslaan.
        } elseif ($values['naam'] === '') {
            $error = 'Naam is verplicht.';
        } elseif (mb_strlen($values['naam']) > 100) {
            $error = 'De naam is te lang (maximaal 100 tekens).';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $values['kleur'])) {
            $error = 'Kies een geldige kleur.';
        } elseif (strlen($values['logo']) > 255) {
            $error = 'Het pad van het logo is te lang (maximaal 255 tekens).';
        } else {
            $slug = unique_vrijwilliger_groep_slug($values['slug'] !== '' ? $values['slug'] : $values['naam'], $id ?: null);
            $params = [
                'naam' => $values['naam'], 'slug' => $slug, 'kleur' => $values['kleur'],
                'logo' => $values['logo'] ?: null, 'toelichting' => $values['toelichting'] ?: null,
                'actief' => $values['actief'],
            ];
            if ($groep) {
                $params['id'] = $id;
                db()->prepare('UPDATE vrijwilliger_groepen SET naam=:naam, slug=:slug, kleur=:kleur, logo=:logo,
                    toelichting=:toelichting, actief=:actief WHERE id=:id')->execute($params);
                audit_log('update', 'vrijwilliger_groep', $id, 'Groep "' . $values['naam'] . '" bijgewerkt');
                flash_set('success', 'Groep bijgewerkt.');
            } else {
                $params['volgorde'] = (int) db()->query('SELECT COALESCE(MAX(volgorde),0) FROM vrijwilliger_groepen')->fetchColumn() + 1;
                db()->prepare('INSERT INTO vrijwilliger_groepen (naam, slug, kleur, logo, toelichting, actief, volgorde)
                    VALUES (:naam, :slug, :kleur, :logo, :toelichting, :actief, :volgorde)')->execute($params);
                audit_log('create', 'vrijwilliger_groep', (int) db()->lastInsertId(), 'Groep "' . $values['naam'] . '" toegevoegd');
                flash_set('success', 'Groep toegevoegd.');
            }
            header('Location: vrijwilligers.php?tab=groepen');
            exit;
        }
    }
}

$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

$editorNavigation = [
    'public_url' => null,
    'unavailable_text' => 'Een groep heeft geen eigen pagina; plaats hem met een shortcode in een pagina.',
    'back_url' => 'vrijwilligers.php?tab=groepen',
    'back_label' => 'Terug naar vrijwilligers',
];
include __DIR__ . '/includes/layout_top.php';
include __DIR__ . '/includes/page_editor_top.php';
?>
<div class="admin-card">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="field-row">
      <div class="field">
        <label for="naam">Naam</label>
        <input type="text" id="naam" name="naam" value="<?= e($values['naam']) ?>" maxlength="100" required placeholder="bv. Bestuur">
      </div>
      <div class="field">
        <label for="kleur">Kleur</label>
        <input type="color" id="kleur" name="kleur" value="<?= e($values['kleur']) ?>">
        <span class="hint">Accentkleur van de kop en de foto&rsquo;s in het smoelenboek.</span>
      </div>
    </div>

    <div class="field">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" placeholder="wordt automatisch aangemaakt op basis van de naam">
      <span class="hint">Gebruikt in de shortcode: <code>[smoelenboek groep="<?= e($values['slug'] !== '' ? $values['slug'] : 'slug') ?>"]</code>.</span>
    </div>

    <div class="field">
      <label for="logo">Logo (optioneel)</label>
      <?php if (!empty($values['logo'])): ?><div style="margin-bottom:8px;"><img src="<?= e(preg_match('#^https?://#i', $values['logo']) ? $values['logo'] : '../' . $values['logo']) ?>" alt="" class="thumb" style="width:90px; height:90px; object-fit:contain;"></div><?php endif; ?>
      <input type="text" id="logo" name="logo" value="<?= e($values['logo'] ?? '') ?>" maxlength="255">
      <select class="file-picker" data-target="logo" style="margin-top:6px;">
        <option value="">— of kies een reeds geüpload bestand —</option>
        <?php foreach ($existingImages as $img): ?>
        <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Of upload hieronder een nieuw bestand:</span>
      <input type="file" name="upload_logo" accept="image/*" style="margin-top:6px;">
      <span class="hint">Getoond naast de groepsnaam in het smoelenboek. Bij voorkeur een vierkante PNG met transparante achtergrond.</span>
    </div>

    <div class="field">
      <label for="toelichting">Toelichting (optioneel)</label>
      <textarea class="rich-text" id="toelichting" name="toelichting" rows="8"><?= e($values['toelichting'] ?? '') ?></textarea>
      <span class="hint">Tekst onder de groepsnaam in het smoelenboek, bv. wat het bestuur doet.</span>
    </div>

    <div class="field">
      <label class="field-inline-label"><input type="checkbox" name="actief" <?= $values['actief'] ? 'checked' : '' ?>> Actief (zichtbaar op de website)</label>
    </div>
    <button type="submit" class="btn">Opslaan</button>
    <a href="vrijwilligers.php?tab=groepen" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/page_editor_bottom.php'; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

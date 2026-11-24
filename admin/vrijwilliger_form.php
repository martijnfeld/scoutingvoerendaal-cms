<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$persoon = $id ? get_vrijwilliger($id) : null;
if ($id && !$persoon) {
    flash_set('error', 'Vrijwilliger niet gevonden.');
    header('Location: vrijwilligers.php?tab=personen');
    exit;
}

$pageTitle = $persoon ? 'Vrijwilliger bewerken' : 'Nieuwe vrijwilliger';
$error = '';
$values = $persoon ?: ['naam' => '', 'foto' => '', 'email' => '', 'actief' => 1];

$groepen = get_vrijwilliger_groepen();
$speltakken = get_speltakken();

// Rollen als formulierrijen: ['id' => rol-id of 0, 'target' => 's:1'|'g:2', 'functie' => '…'].
$roleRows = [];
if ($persoon) {
    foreach (get_vrijwilliger_rollen($id) as $r) {
        $roleRows[] = ['id' => (int) $r['id'], 'target' => $r['speltak_id'] ? 's:' . $r['speltak_id'] : 'g:' . $r['groep_id'], 'functie' => $r['functie']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $values['naam'] = trim($_POST['naam'] ?? '');
        $values['email'] = trim($_POST['email'] ?? '');
        $values['foto'] = trim($_POST['foto'] ?? '');
        $values['actief'] = isset($_POST['actief']) ? 1 : 0;

        $roleRows = [];
        $postedIds = (array) ($_POST['rol_id'] ?? []);
        $postedTargets = (array) ($_POST['rol_target'] ?? []);
        $postedFuncties = (array) ($_POST['rol_functie'] ?? []);
        foreach ($postedTargets as $i => $target) {
            if ((string) $target === '') {
                continue;
            }
            $roleRows[] = [
                'id' => (int) ($postedIds[$i] ?? 0),
                'target' => (string) $target,
                'functie' => trim((string) ($postedFuncties[$i] ?? '')),
            ];
        }

        try {
            $uploaded = handle_upload('upload_foto', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['foto'] = $uploaded;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error !== '') {
            // Uploadfout: melding hierboven, niets opslaan.
        } elseif ($values['naam'] === '') {
            $error = 'Naam is verplicht.';
        } elseif (mb_strlen($values['naam']) > 150) {
            $error = 'De naam is te lang (maximaal 150 tekens).';
        } elseif ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190)) {
            $error = 'Het e-mailadres is ongeldig.';
        } elseif (strlen($values['foto']) > 255) {
            $error = 'Het pad van de foto is te lang (maximaal 255 tekens).';
        } else {
            $params = [
                'naam' => $values['naam'], 'foto' => $values['foto'] ?: null,
                'email' => $values['email'] ?: null, 'actief' => $values['actief'],
            ];
            if ($persoon) {
                $params['id'] = $id;
                db()->prepare('UPDATE vrijwilligers SET naam=:naam, foto=:foto, email=:email, actief=:actief WHERE id=:id')->execute($params);
                $savedId = $id;
                audit_log('update', 'vrijwilliger', $savedId, 'Vrijwilliger "' . $values['naam'] . '" bijgewerkt');
                flash_set('success', 'Vrijwilliger bijgewerkt.');
            } else {
                db()->prepare('INSERT INTO vrijwilligers (naam, foto, email, actief) VALUES (:naam, :foto, :email, :actief)')->execute($params);
                $savedId = (int) db()->lastInsertId();
                audit_log('create', 'vrijwilliger', $savedId, 'Vrijwilliger "' . $values['naam'] . '" toegevoegd');
                flash_set('success', 'Vrijwilliger toegevoegd.');
            }
            save_vrijwilliger_rollen($savedId, $roleRows);
            header('Location: vrijwilligers.php?tab=personen');
            exit;
        }
    }
}

// Altijd een lege extra rij, ook zonder JavaScript.
$roleRows[] = ['id' => 0, 'target' => '', 'functie' => ''];
$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

/** Eén rol-rij (ook gebruikt als <template> voor "Rol toevoegen"). */
function vrijwilliger_role_row(array $row, array $groepen, array $speltakken): string
{
    $html = '<div class="role-row" data-role-row><input type="hidden" name="rol_id[]" value="' . (int) $row['id'] . '">'
        . '<select name="rol_target[]" aria-label="Groep of speltak"><option value="">— kies groep of speltak —</option>';
    if ($groepen) {
        $html .= '<optgroup label="Groepen">';
        foreach ($groepen as $g) {
            $html .= '<option value="g:' . (int) $g['id'] . '"' . ($row['target'] === 'g:' . $g['id'] ? ' selected' : '') . '>' . e($g['naam']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    if ($speltakken) {
        $html .= '<optgroup label="Speltakken">';
        foreach ($speltakken as $s) {
            $html .= '<option value="s:' . (int) $s['id'] . '"' . ($row['target'] === 's:' . $s['id'] ? ' selected' : '') . '>' . e($s['naam']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html . '</select>'
        . '<input type="text" name="rol_functie[]" value="' . e($row['functie']) . '" maxlength="120" placeholder="Functie, bv. Leiding of Penningmeester" aria-label="Functie">'
        . '<button type="button" class="btn btn-danger btn-small" data-remove-role title="Rol verwijderen"><i class="fa-solid fa-trash"></i></button></div>';
}

$editorNavigation = [
    'public_url' => null,
    'unavailable_text' => 'Een vrijwilliger heeft geen eigen pagina; hij of zij staat in het smoelenboek en op de speltakpagina.',
    'back_url' => 'vrijwilligers.php?tab=personen',
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
        <input type="text" id="naam" name="naam" value="<?= e($values['naam']) ?>" maxlength="150" required>
      </div>
      <div class="field">
        <label for="email">E-mailadres (optioneel)</label>
        <input type="email" id="email" name="email" value="<?= e($values['email'] ?? '') ?>" maxlength="190">
        <span class="hint">Wordt op de website niet als gewone tekst getoond, maar versluierd tegen spam. Vraag eerst toestemming aan de vrijwilliger om het adres te publiceren.</span>
      </div>
    </div>

    <div class="field">
      <label for="foto">Foto (optioneel)</label>
      <?php if (!empty($values['foto'])): ?><div style="margin-bottom:8px;"><img src="<?= e(preg_match('#^https?://#i', $values['foto']) ? $values['foto'] : '../' . $values['foto']) ?>" alt="" class="thumb" style="width:90px; height:90px; object-fit:cover;"></div><?php endif; ?>
      <input type="text" id="foto" name="foto" value="<?= e($values['foto'] ?? '') ?>" maxlength="255">
      <select class="file-picker" data-target="foto" style="margin-top:6px;">
        <option value="">— of kies een reeds geüpload bestand —</option>
        <?php foreach ($existingImages as $img): ?>
        <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Of upload hieronder een nieuw bestand:</span>
      <input type="file" name="upload_foto" accept="image/*" style="margin-top:6px;">
      <span class="hint">Bij voorkeur een vierkante foto. Leeg laten = een neutraal icoon.</span>
    </div>

    <div class="field" data-roles-editor>
      <label>Groepen en functies</label>
      <span class="hint">Een vrijwilliger zonder groep of speltak is niet zichtbaar op de website. Een nieuwe rol komt achteraan in de groep; de volgorde pas je aan bij Vrijwilligers → Per groep.</span>
      <div data-roles-list>
        <?php foreach ($roleRows as $row): ?>
        <?= vrijwilliger_role_row($row, $groepen, $speltakken) ?>
        <?php endforeach; ?>
      </div>
      <template data-role-template><?= vrijwilliger_role_row(['id' => 0, 'target' => '', 'functie' => ''], $groepen, $speltakken) ?></template>
      <button type="button" class="btn btn-secondary btn-small" data-add-role style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Rol toevoegen</button>
      <?php if (!$groepen && !$speltakken): ?><span class="hint">Er zijn nog geen groepen of speltakken. <a href="vrijwilliger_groep_form.php">Maak eerst een groep aan.</a></span><?php endif; ?>
    </div>

    <div class="field">
      <label class="field-inline-label"><input type="checkbox" name="actief" <?= $values['actief'] ? 'checked' : '' ?>> Actief (zichtbaar op de website)</label>
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="vrijwilligers.php?tab=personen" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/page_editor_bottom.php'; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

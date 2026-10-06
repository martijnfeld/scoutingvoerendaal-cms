<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$doc = $id ? get_document($id) : null;
if ($id && !$doc) {
    flash_set('error', 'Document niet gevonden.');
    header('Location: documents.php');
    exit;
}

$pageTitle = $doc ? 'Document bewerken' : 'Nieuw document';
$error = '';
$naam = $doc['naam'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $naam = trim($_POST['naam'] ?? '');
        if ($naam === '') {
            $error = 'Naam is verplicht.';
        } elseif (!$doc && empty($_FILES['bestand']['name'])) {
            $error = 'Kies een PDF-bestand om te uploaden.';
        } else {
            try {
                $uploaded = handle_upload('bestand', ['pdf']);
                if ($doc) {
                    if ($uploaded !== null) {
                        $oldPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($doc['bestand']);
                        $stmt = db()->prepare('UPDATE documents SET naam=:naam, bestand=:bestand WHERE id=:id');
                        $stmt->execute(['naam' => $naam, 'bestand' => basename($uploaded), 'id' => $id]);
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    } else {
                        $stmt = db()->prepare('UPDATE documents SET naam=:naam WHERE id=:id');
                        $stmt->execute(['naam' => $naam, 'id' => $id]);
                    }
                    flash_set('success', 'Document bijgewerkt.');
                } else {
                    $stmt = db()->prepare('INSERT INTO documents (naam, bestand) VALUES (:naam, :bestand)');
                    $stmt->execute(['naam' => $naam, 'bestand' => basename($uploaded)]);
                    flash_set('success', 'Document toegevoegd.');
                }
                header('Location: documents.php');
                exit;
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:600px;">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="field">
      <label for="naam">Naam</label>
      <input type="text" id="naam" name="naam" value="<?= e($naam) ?>" required placeholder="bv. Inschrijfformulier">
    </div>

    <div class="field">
      <label for="bestand">PDF-bestand<?= $doc ? ' (laat leeg om te behouden)' : '' ?></label>
      <?php if ($doc): ?>
      <p class="muted">Huidig bestand: <a href="../<?= e(rtrim(UPLOAD_URL, '/')) ?>/<?= e($doc['bestand']) ?>" target="_blank"><?= e($doc['bestand']) ?></a></p>
      <?php endif; ?>
      <input type="file" id="bestand" name="bestand" accept="application/pdf">
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="documents.php" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

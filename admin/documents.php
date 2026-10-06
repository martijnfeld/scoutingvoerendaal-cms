<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = "Documenten (PDF's)";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $doc = get_document($id);
        db()->prepare('DELETE FROM documents WHERE id = :id')->execute(['id' => $id]);
        if ($doc) {
            $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($doc['bestand']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        flash_set('success', 'Document verwijderd.');
    }
    header('Location: documents.php');
    exit;
}

$documents = db()->query(
    'SELECT d.*, (SELECT COUNT(*) FROM info_cards ic WHERE ic.document_id = d.id) AS gebruikt_door
     FROM documents d ORDER BY d.naam ASC'
)->fetchAll();

include __DIR__ . '/includes/layout_top.php';
?>
<p>Upload en beheer hier PDF-bestanden zoals het inschrijfformulier. Gekoppelde info-vakjes tonen automatisch een downloadknop.</p>
<div class="top-actions">
  <a href="document_form.php" class="btn">+ Nieuw document</a>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Naam</th><th>Bestand</th><th>Bijgewerkt</th><th>In gebruik</th><th>Acties</th></tr>
    <?php foreach ($documents as $doc): ?>
    <tr>
      <td><strong><?= e($doc['naam']) ?></strong></td>
      <td><a href="../<?= e(rtrim(UPLOAD_URL, '/')) ?>/<?= e($doc['bestand']) ?>" target="_blank"><?= e($doc['bestand']) ?></a></td>
      <td><?= e($doc['bijgewerkt']) ?></td>
      <td><?= (int) $doc['gebruikt_door'] ?> info-vakje(s)</td>
      <td class="actions">
        <a class="btn btn-small" href="document_form.php?id=<?= $doc['id'] ?>">Bewerken / vervangen</a>
        <form method="post" data-confirm="Dit document verwijderen? Info-vakjes die dit document gebruiken verliezen de koppeling." style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $doc['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$documents): ?>
    <tr><td colspan="5" class="muted">Nog geen documenten.</td></tr>
    <?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

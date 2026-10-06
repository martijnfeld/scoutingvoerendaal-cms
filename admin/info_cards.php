<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Info-vakjes';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('DELETE FROM info_cards WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash_set('success', 'Info-vakje verwijderd.');
    } elseif ($action === 'move' && $id) {
        $dir = $_POST['dir'] ?? '';
        $card = get_info_card($id);
        if ($card) {
            $sql = $dir === 'up'
                ? 'SELECT * FROM info_cards WHERE sectie = :s AND (volgorde < :v OR (volgorde = :v AND id < :id)) ORDER BY volgorde DESC, id DESC LIMIT 1'
                : 'SELECT * FROM info_cards WHERE sectie = :s AND (volgorde > :v OR (volgorde = :v AND id > :id)) ORDER BY volgorde ASC, id ASC LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute(['s' => $card['sectie'], 'v' => $card['volgorde'], 'id' => $card['id']]);
            $neighbor = $stmt->fetch();
            if ($neighbor) {
                $upd = db()->prepare('UPDATE info_cards SET volgorde = :v WHERE id = :id');
                $upd->execute(['v' => $neighbor['volgorde'], 'id' => $card['id']]);
                $upd->execute(['v' => $card['volgorde'], 'id' => $neighbor['id']]);
            }
        }
    }
    header('Location: info_cards.php');
    exit;
}

$cards = db()->query('SELECT ic.*, d.naam AS document_naam FROM info_cards ic LEFT JOIN documents d ON d.id = ic.document_id ORDER BY ic.sectie ASC, ic.volgorde ASC, ic.id ASC')->fetchAll();

include __DIR__ . '/includes/layout_top.php';
?>
<p>Dit zijn de info-vakjes die op de website staan, bijvoorbeeld bij "Doe mee!" (zoals <em>Gratis kennismaken</em>). Je kunt er zoveel toevoegen als je wilt.</p>
<div class="top-actions">
  <a href="info_card_form.php" class="btn">+ Nieuw info-vakje</a>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Volgorde</th><th>Sectie</th><th>Titel</th><th>Document</th><th>Acties</th></tr>
    <?php foreach ($cards as $card): ?>
    <tr>
      <td>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <input type="hidden" name="dir" value="up">
          <button type="submit" class="btn btn-secondary btn-small" title="Omhoog"><i class="fa-solid fa-arrow-up"></i></button>
        </form>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <input type="hidden" name="dir" value="down">
          <button type="submit" class="btn btn-secondary btn-small" title="Omlaag"><i class="fa-solid fa-arrow-down"></i></button>
        </form>
      </td>
      <td><?= e($card['sectie']) ?></td>
      <td><strong><?= e($card['titel']) ?></strong><?= $card['anchor'] ? '<br><span class="muted">#' . e($card['anchor']) . '</span>' : '' ?></td>
      <td><?= $card['document_naam'] ? e($card['document_naam']) : '<span class="muted">geen</span>' ?></td>
      <td class="actions">
        <a class="btn btn-small" href="info_card_form.php?id=<?= $card['id'] ?>">Bewerken</a>
        <form method="post" data-confirm="Dit info-vakje verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$cards): ?>
    <tr><td colspan="5" class="muted">Nog geen info-vakjes.</td></tr>
    <?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

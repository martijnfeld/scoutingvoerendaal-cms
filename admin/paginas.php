<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = "Pagina's";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash_set('success', 'Pagina verwijderd.');
    } elseif ($action === 'move' && $id) {
        $dir = $_POST['dir'] ?? '';
        $page = get_page($id);
        if ($page) {
            $sql = $dir === 'up'
                ? 'SELECT * FROM pages WHERE (volgorde < :v OR (volgorde = :v AND id < :id)) ORDER BY volgorde DESC, id DESC LIMIT 1'
                : 'SELECT * FROM pages WHERE (volgorde > :v OR (volgorde = :v AND id > :id)) ORDER BY volgorde ASC, id ASC LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute(['v' => $page['volgorde'], 'id' => $page['id']]);
            $neighbor = $stmt->fetch();
            if ($neighbor) {
                $upd = db()->prepare('UPDATE pages SET volgorde = :v WHERE id = :id');
                $upd->execute(['v' => $neighbor['volgorde'], 'id' => $page['id']]);
                $upd->execute(['v' => $page['volgorde'], 'id' => $neighbor['id']]);
            }
        }
    }
    header('Location: paginas.php');
    exit;
}

$pages = get_pages();

include __DIR__ . '/includes/layout_top.php';
?>
<p>Losse informatieve pagina's naast de hoofdpagina (bv. voor leden). De inhoud vul je in met een uitgebreide tekstverwerker, inclusief afbeeldingen en YouTube-video's.</p>
<div class="top-actions">
  <a href="pagina_form.php" class="btn">+ Nieuwe pagina</a>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Volgorde</th><th>Titel</th><th>Slug</th><th>Menu</th><th>Actief</th><th>Acties</th></tr>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input type="hidden" name="dir" value="up">
          <button type="submit" class="btn btn-secondary btn-small" title="Omhoog"><i class="fa-solid fa-arrow-up"></i></button>
        </form>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input type="hidden" name="dir" value="down">
          <button type="submit" class="btn btn-secondary btn-small" title="Omlaag"><i class="fa-solid fa-arrow-down"></i></button>
        </form>
      </td>
      <td><strong><?= e($p['titel']) ?></strong></td>
      <td><a href="../<?= e(page_url($p['slug'])) ?>" target="_blank">/<?= e($p['slug']) ?></a></td>
      <td><?= $p['in_menu'] ? 'Ja' : 'Nee' ?></td>
      <td><?= $p['actief'] ? 'Ja' : 'Nee' ?></td>
      <td class="actions">
        <a class="btn btn-small" href="pagina_form.php?id=<?= $p['id'] ?>">Bewerken</a>
        <form method="post" data-confirm="Deze pagina verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$pages): ?>
    <tr><td colspan="6" class="muted">Nog geen pagina's.</td></tr>
    <?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

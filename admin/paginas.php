<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/header_menu.php';
require_once __DIR__ . '/includes/page_definitions.php';

$pageTitle = "Pagina's";

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $action = $_POST['action'] ?? '';
        $requestedId = $_POST['id'] ?? '';
        $id = is_string($requestedId) && ctype_digit($requestedId) ? (int) $requestedId : 0;
        $record = $id ? get_page($id) : null;
        $definition = $record ? page_editor_definition('content', $record) : null;
        if (!$definition || !in_array($action, ['delete', 'move'], true)) {
            $error = 'Pagina of actie niet gevonden.';
        } elseif ($action === 'delete' && $definition['can_delete']) {
            if (header_menu_page_used($id)) {
                $error = 'Deze pagina wordt gebruikt in de header. Verwijder of wijzig eerst de menukoppeling via Systeem → Instellingen → Header.';
            } else {
                try {
                    $stmt = db()->prepare('DELETE FROM pages WHERE id = :id');
                    $stmt->execute(['id' => $id]);
                    audit_log('delete', 'pagina', $id, 'Pagina "' . $record['titel'] . '" verwijderd');
                    flash_set('success', 'Pagina verwijderd.');
                } catch (PDOException $exception) {
                    // De foreign key beschermt ook tegen een gelijktijdig toegevoegde menukoppeling.
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1451) { throw $exception; }
                    $error = 'Deze pagina wordt gebruikt in de header. Wijzig eerst de menukoppeling via Systeem → Instellingen → Header.';
                }
            }
        } elseif ($action === 'move' && $definition['can_reorder']) {
            require_once __DIR__ . '/includes/content_order.php';
            move_content_record('pages', $id, is_string($_POST['dir'] ?? null) ? $_POST['dir'] : '');
        } else {
            $error = 'Deze actie is niet beschikbaar voor deze pagina.';
        }
        if ($error === '') {
            header('Location: paginas.php');
            exit;
        }
    }
}

$pages = page_overview_entries(get_pages());

include __DIR__ . '/includes/layout_top.php';
?>
<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
<p>Beheer de inhoud van je websitepagina’s.</p>
<div class="top-actions">
  <a href="pagina_form.php" class="btn">+ Nieuwe pagina</a>
</div>

<div class="admin-card table-responsive">
  <table class="admin-table">
    <tr><th>Volgorde</th><th>Titel</th><th>URL</th><th>Actief</th><th>Acties</th></tr>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td>
        <?php if (!$p['can_reorder']): ?>
        <i class="fa-solid fa-lock" aria-hidden="true"></i><span class="visually-hidden">Niet verwijderbare pagina</span>
        <?php else: ?>
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
        <?php endif; ?>
      </td>
      <td><strong><?= e($p['titel']) ?></strong></td>
      <td><a href="<?= e($p['public_url']) ?>" target="_blank" rel="noopener">/<?= e($p['slug']) ?></a></td>
      <td><?= $p['actief'] ? 'Ja' : 'Nee' ?></td>
      <td class="actions">
        <a class="btn btn-small" href="<?= e($p['edit_url']) ?>">Bewerken</a>
        <?php if ($p['can_delete']): ?>
        <form method="post" data-confirm="Deze pagina verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$pages): ?>
    <tr><td colspan="5" class="muted">Nog geen pagina's.</td></tr>
    <?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

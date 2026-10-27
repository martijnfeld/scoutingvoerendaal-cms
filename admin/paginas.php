<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/page_definitions.php';
require_once __DIR__ . '/includes/page_actions.php';

$pageTitle = "Pagina's";
$adminMainClass = 'header-settings-main';
$loadRichTextEditor = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $key = $_POST['pagina'] ?? 'content';
        $action = $_POST['action'] ?? '';
        $requestedId = $_POST['id'] ?? '0';
        if (!is_string($key) || !isset(page_editor_definitions()[$key]) || !is_string($action)
            || !is_string($requestedId) || !ctype_digit($requestedId)) {
            $error = 'Pagina of actie niet gevonden.';
        } else {
            try {
                page_overview_mutate($key, (int) $requestedId, $action);
                flash_set('success', $action === 'delete' ? 'Pagina verwijderd.' : 'Publicatie bijgewerkt.');
                header('Location: paginas.php', true, 303);
                exit;
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
$pages = page_overview_entries(get_pages());
$pageHeaderRows = header_menu_rows() ?? header_menu_legacy_rows();
include __DIR__ . '/includes/layout_top.php';
?>
<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
<div class="header-admin pages-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <p class="text-body-secondary mb-0">Beheer de inhoud en publicatie van je websitepagina’s.</p>
    <a href="pagina_form.php" class="btn d-inline-flex align-items-center gap-2"><?= header_menu_icon('plus') ?>Nieuwe pagina</a>
  </div>
  <div class="header-admin-list">
    <ul class="list-group list-group-flush" aria-label="Pagina’s">
      <?php foreach ($pages as $p):
          $record = $p['key'] === 'content' ? ['id' => $p['id'], 'slug' => $p['slug']] : [];
          $used = $p['can_toggle'] && page_header_used($p['key'], $record, $pageHeaderRows);
          $blocked = $p['actief'] && $used;
          $publicationDisabled = !$p['can_toggle'] || $blocked;
          $publicationHint = !$p['can_toggle']
              ? 'Home is de startpagina van je website en kan daarom niet worden uitgeschakeld.'
              : 'Deze pagina staat nog in het websitemenu. Verwijder de koppelingen via Systeem → Instellingen → Header. Daarna kun je de pagina hier uitschakelen.';
          $deleteDisabled = !$p['can_delete'] || $used;
          $deleteHint = !$p['can_delete']
              ? 'Deze pagina hoort bij het systeem en kan niet worden verwijderd.'
              : 'Deze pagina staat nog in het websitemenu. Verwijder eerst de koppelingen via Systeem → Instellingen → Header. Daarna kun je de pagina verwijderen.';
      ?>
      <li class="list-group-item p-0 header-admin-group">
        <div class="header-admin-row pages-admin-row<?= !$p['actief'] ? ' header-admin-row-inactive' : '' ?>">
          <div class="header-admin-item-content">
            <a class="header-admin-item-title" href="<?= e($p['edit_url']) ?>"><?= e($p['titel']) ?></a>
            <div class="header-admin-destination">
              <span><?= $p['can_delete'] ? 'Eigen pagina' : 'Standaardpagina' ?></span>
              <?php if ($p['actief']): ?><a href="<?= e($p['public_url']) ?>" target="_blank" rel="noopener">/<?= e($p['slug']) ?><?= fa_inline_icon('external-link') ?><span class="visually-hidden"> (opent in nieuw tabblad)</span></a>
              <?php else: ?><span>/<?= e($p['slug']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="header-admin-item-actions">
            <a class="btn header-admin-edit" href="<?= e($p['edit_url']) ?>" aria-label="<?= e('Bewerken: ' . $p['titel']) ?>"><?= header_menu_icon('edit') ?><span>Bewerken</span></a>
            <form method="post" class="pages-admin-publication"<?= $publicationDisabled ? ' tabindex="0" data-bs-toggle="tooltip" data-bs-custom-class="page-action-tooltip" data-bs-title="' . e($publicationHint) . '" aria-label="' . e('Publicatie van ' . $p['titel']) . '" aria-describedby="page-status-' . e($p['key']) . '-' . (int) $p['id'] . '"' : '' ?>>
              <?php if ($publicationDisabled): ?><span class="visually-hidden" id="page-status-<?= e($p['key']) ?>-<?= (int) $p['id'] ?>"><?= e($publicationHint) ?></span><?php endif; ?>
              <?= csrf_field() ?><input type="hidden" name="pagina" value="<?= e($p['key']) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle">
              <button class="header-admin-visibility" type="submit" role="switch" aria-checked="<?= $p['actief'] ? 'true' : 'false' ?>" aria-label="<?= e('Publicatie van ' . $p['titel']) ?>"<?= $publicationDisabled ? ' disabled aria-describedby="page-status-' . e($p['key']) . '-' . (int) $p['id'] . '"' : '' ?>>
                <span class="header-admin-switch" aria-hidden="true"></span><span><?= $p['actief'] ? 'Actief' : 'Uitgeschakeld' ?></span>
              </button>
            </form>
            <form method="post" class="pages-admin-delete"<?= $deleteDisabled ? ' tabindex="0" data-bs-toggle="tooltip" data-bs-custom-class="page-action-tooltip" data-bs-title="' . e($deleteHint) . '" aria-label="' . e('Verwijderen: ' . $p['titel']) . '"' : '' ?> data-confirm="<?= e('Pagina “' . $p['titel'] . '” verwijderen?') ?>">
              <?= csrf_field() ?><input type="hidden" name="pagina" value="<?= e($p['key']) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete">
              <button class="header-admin-icon-button header-admin-delete" type="submit" <?= !$deleteDisabled ? 'title="Verwijderen"' : '' ?> aria-label="<?= e('Verwijderen: ' . $p['titel']) ?>"<?= $deleteDisabled ? ' disabled' : '' ?>><?= header_menu_icon('trash') ?></button>
            </form>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

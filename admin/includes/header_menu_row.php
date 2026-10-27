<?php /** Eén bewerkbare rij, gedeeld door hoofdmenu en handmatige submenu's. */ ?>
<div class="header-admin-row<?= ($item['parent_id'] !== null ? ' header-admin-row-child' : '') . (!$item['visible'] ? ' header-admin-row-inactive' : '') ?>" id="menu-item-<?= (int) $item['id'] ?>">
  <div class="header-admin-order" aria-label="Volgorde">
    <span class="header-admin-position" title="Sorteerwaarde: <?= (int) $item['volgorde'] ?>"><?= (int) $item['position'] ?></span>
    <?php foreach (['up' => 'Omhoog', 'down' => 'Omlaag'] as $action => $label): ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="<?= e($action) ?>">
      <button class="header-admin-icon-button" type="submit" title="<?= e($label) ?>" aria-label="<?= e($label . ': ' . $item['label']) ?>"<?= !$item['can_' . $action] ? ' disabled' : '' ?>><?= header_menu_icon('arrow-' . $action) ?></button>
    </form>
    <?php endforeach; ?>
  </div>
  <div class="header-admin-item-content">
    <a class="header-admin-item-title" href="settings.php?tab=header&amp;edit=<?= (int) $item['id'] ?>"><?php if ($item['parent_id'] !== null): ?><?= header_menu_icon('submenu') ?><span class="visually-hidden">Submenu: </span><?php endif; ?><?= e($item['label']) ?></a>
    <div class="header-admin-destination">
      <span><?= e($item['destination']['description']) ?></span>
      <?php if ($item['destination']['url'] !== null): ?><a href="<?= e($item['destination']['url']) ?>" target="_blank" rel="noopener"><?= e($item['destination']['path']) ?><?= fa_inline_icon('external-link') ?><span class="visually-hidden"> (opent in nieuw tabblad)</span></a>
      <?php else: ?><span><?= e($item['destination']['path']) ?></span><?php endif; ?>
    </div>
    <?php if ($item['actief'] && !$item['visible']): ?><span class="header-admin-status header-admin-status-blocked" id="menu-status-<?= (int) $item['id'] ?>"><?= header_menu_icon('eye-off') ?><?= e($item['status']) ?></span><?php endif; ?>
  </div>
  <div class="header-admin-item-actions">
    <a class="btn header-admin-edit" href="settings.php?tab=header&amp;edit=<?= (int) $item['id'] ?>" aria-label="<?= e('Bewerken: ' . $item['label']) ?>"><?= header_menu_icon('edit') ?><span>Bewerken</span></a>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="toggle">
      <button class="header-admin-visibility" type="submit" role="switch" aria-checked="<?= $item['actief'] ? 'true' : 'false' ?>" aria-label="<?= e('Zichtbaarheid van ' . $item['label']) ?>"<?= $item['actief'] && !$item['visible'] ? ' aria-describedby="menu-status-' . (int) $item['id'] . '"' : '' ?> title="<?= $item['actief'] ? 'Verbergen in de header' : 'Tonen in de header' ?>">
        <span class="header-admin-switch" aria-hidden="true"></span><span><?= $item['visible'] ? 'Zichtbaar' : ($item['actief'] ? 'Niet zichtbaar' : 'Verborgen') ?></span>
      </button>
    </form>
    <form method="post" data-confirm="<?= e('Menu-item “' . $item['label'] . '” verwijderen? De gekoppelde pagina blijft bewaard.') ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="delete">
      <button class="header-admin-icon-button header-admin-delete" type="submit" title="Verwijderen" aria-label="<?= e('Verwijderen: ' . $item['label']) ?>"><?= header_menu_icon('trash') ?></button>
    </form>
  </div>
</div>

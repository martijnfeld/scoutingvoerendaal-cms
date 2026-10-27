<?php if (!$headerReady): ?>
<div class="admin-flash admin-flash-error">Het headerbeheer is beschikbaar na de databasemigratie via <a href="updates.php">Systeem → Updates</a>. De website toont tot die tijd de bestaande navigatie.</div>
<?php elseif ($isHeaderEditor): ?>
<?php include __DIR__ . '/header_menu_form.php'; ?>
<?php else: ?>
<div class="header-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h2 class="mb-2">Headernavigatie</h2><p class="text-body-secondary mb-0">Dit is de volgorde in het websitemenu. Submenu’s staan onder hun hoofditem.</p></div>
    <a class="btn d-inline-flex align-items-center gap-2" href="settings.php?tab=header&amp;new=1"><?= header_menu_icon('plus') ?>Menu-item toevoegen</a>
  </div>
  <div class="header-admin-list">
    <ol class="list-group list-group-flush" aria-label="Hoofdmenu">
      <?php foreach ($headerGroups as $group): $item = $group; ?>
      <li class="list-group-item p-0 header-admin-group<?= !$group['visible'] ? ' header-admin-group-inactive' : '' ?>">
        <?php include __DIR__ . '/header_menu_row.php'; ?>
        <?php if ($group['dropdown_type'] === 'manual'): ?>
        <div class="header-admin-submenu">
          <div class="header-admin-submenu-heading">Handmatig submenu</div>
          <?php if ($group['children']): ?>
          <ol class="list-unstyled mb-0" aria-label="<?= e('Submenu ' . $group['label']) ?>">
            <?php foreach ($group['children'] as $item): ?><li><?php include __DIR__ . '/header_menu_row.php'; ?></li><?php endforeach; ?>
          </ol>
          <?php else: ?><p class="text-body-secondary mb-2">Dit submenu is nog leeg.</p><?php endif; ?>
          <a class="header-admin-add-child" href="settings.php?tab=header&amp;new=1&amp;parent=<?= (int) $group['id'] ?>"><?= header_menu_icon('plus') ?>Submenu-item toevoegen</a>
        </div>
        <?php elseif (in_array($group['dropdown_type'], ['programme', 'speltakken'], true)): ?>
        <details class="header-admin-generated">
          <summary><?= header_menu_icon('chevron-down') ?><span>Automatisch submenu <span class="text-body-secondary">· <?= count($headerSpeltakken) ?> speltakken</span></span></summary>
          <div class="header-admin-generated-content">
            <p><?= $group['dropdown_type'] === 'programme' ? 'Elke speltak linkt naar het eigen programma op Home.' : 'Elke speltak linkt naar de eigen speltakpagina.' ?> Namen, zichtbaarheid en volgorde beheer je bij <a href="speltakken.php">Speltakken</a>.</p>
            <?php if ($headerSpeltakken): ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($headerSpeltakken as $speltak): $path = $group['dropdown_type'] === 'programme' ? '#sk-' . $speltak['slug'] : speltak_url($speltak['slug']); ?>
              <li><?= header_menu_icon('link') ?><a href="../<?= e($path) ?>" target="_blank" rel="noopener"><?= e($speltak['naam']) ?></a><span class="text-body-secondary">/<?= e($path) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?><p class="mb-0 text-body-secondary">Er zijn momenteel geen actieve speltakken.</p><?php endif; ?>
          </div>
        </details>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
      <?php if (!$headerGroups): ?>
      <li class="list-group-item p-4"><h3 class="h6">Je header heeft nog geen menu-items</h3><p class="mb-0 text-body-secondary">Voeg een eerste item toe, bijvoorbeeld een link naar Home of een bestaande pagina.</p></li>
      <?php endif; ?>
    </ol>
  </div>
</div>
<?php endif; ?>

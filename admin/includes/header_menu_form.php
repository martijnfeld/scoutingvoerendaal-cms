<?php
$hasMenuChildren = false;
foreach ($headerItems as $candidate) {
    if ($menuId && (int) $candidate['parent_id'] === $menuId) { $hasMenuChildren = true; }
}
?>
<div class="header-admin header-admin-editor">
  <a class="header-admin-back d-inline-flex align-items-center gap-2 mb-4" href="settings.php?tab=header"><?= fa_inline_icon('arrow-left') ?>Terug naar menuoverzicht</a>
  <h2 class="mb-2"><?= $isMenuChild ? ($menuId ? 'Submenu-item bewerken' : 'Submenu-item toevoegen') : ($menuId ? 'Menu-item bewerken' : 'Menu-item toevoegen') ?></h2>
  <p class="text-body-secondary mb-4"><?= $isMenuChild ? 'Dit item staat in het submenu van ' . e($menuParentLabel) . '.' : 'Dit item staat in het hoofdmenu.' ?> De volgorde pas je aan met de pijlen in het overzicht.</p>
  <form method="post" data-header-editor>
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $menuId ?>">
    <div class="admin-card">
      <h3 class="h6 mb-3">Menutekst en bestemming</h3>
      <div class="mb-3">
        <label for="menu-label" class="form-label">Menutekst</label>
        <input class="form-control" id="menu-label" name="label" maxlength="150" required value="<?= e($menuValues['label']) ?>" aria-describedby="menu-label-help">
        <div class="form-text" id="menu-label-help">De naam in de header. Deze mag afwijken van de paginatitel.</div>
      </div>
      <div class="mb-3">
        <label for="menu-type" class="form-label">Waar linkt dit item naartoe?</label>
        <select class="form-select" id="menu-type" name="link_type">
          <?php foreach (HEADER_LINK_TYPES as $key => $label): ?><option value="<?= e($key) ?>"<?= $menuValues['link_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div data-header-field="page" class="mb-3">
        <label for="menu-page" class="form-label">Bijbehorende pagina</label>
        <select class="form-select" id="menu-page" name="page_id" aria-describedby="menu-page-help"><option value="">Kies een pagina</option>
          <?php foreach ($headerPages as $page): ?><option value="<?= (int) $page['id'] ?>"<?= (string) $menuValues['page_id'] === (string) $page['id'] ? ' selected' : '' ?>><?= e($page['titel']) ?><?= !$page['actief'] ? ' (inactief)' : '' ?></option><?php endforeach; ?>
        </select>
        <div class="form-text" id="menu-page-help">De link volgt automatisch de pagina-URL. Een inactieve pagina verschijnt niet in de header.</div>
      </div>
      <div data-header-field="section" class="mb-3">
        <label for="menu-section" class="form-label">Homepage-sectie</label>
        <select class="form-select" id="menu-section" name="section_value">
          <?php foreach (HEADER_SECTIONS as $key => $label): ?><option value="<?= e($key) ?>"<?= $menuValues['link_value'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div data-header-field="programma" class="form-text mb-3">Dit item opent het programma met alle actieve speltakken.</div>
      <div data-header-field="speltakken" class="form-text mb-3">Dit item opent het overzicht met alle actieve speltakken.</div>
      <div data-header-field="url" class="mb-3">
        <label for="menu-url" class="form-label">Eigen URL</label>
        <input class="form-control" id="menu-url" name="url_value" maxlength="2048" value="<?= e($menuValues['link_type'] === 'url' ? $menuValues['link_value'] : '') ?>" placeholder="https://voorbeeld.nl of /leden" aria-describedby="menu-url-help">
        <div class="form-text" id="menu-url-help">Gebruik een websiteadres, mailto, tel of een pad binnen deze website.</div>
      </div>
      <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" role="switch" id="menu-visible" name="actief" value="1"<?= $menuValues['actief'] ? ' checked' : '' ?> aria-describedby="menu-visible-help">
        <label class="form-check-label" for="menu-visible">Tonen in de header</label>
        <div class="form-text" id="menu-visible-help">Uitzetten verbergt het item; de instellingen blijven bewaard.</div>
      </div>
    </div>
    <?php if (!$isMenuChild): ?>
    <div class="admin-card">
      <h3 class="h6 mb-2">Submenu</h3>
      <p class="text-body-secondary">De menutekst blijft altijd een klikbare link. Een apart pijltje opent het submenu.</p>
      <label for="menu-dropdown" class="form-label">Wat verschijnt onder dit item?</label>
      <select class="form-select" id="menu-dropdown" name="dropdown_type" aria-describedby="menu-dropdown-help" data-has-children="<?= $hasMenuChildren ? '1' : '0' ?>">
        <?php foreach (HEADER_DROPDOWN_TYPES as $key => $label): ?><option value="<?= e($key) ?>"<?= $menuValues['dropdown_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <p class="form-text mb-0" id="menu-dropdown-help">Handmatige submenu-items voeg je na het opslaan toe in het overzicht.</p>
      <?php if ($hasMenuChildren): ?><p class="form-text mb-0">Dit item bevat handmatige submenu-items. Verwijder die eerst voordat je een ander type kiest.</p><?php endif; ?>
      <div class="header-admin-dropdown-help mt-3" data-header-dropdown="none"><p class="mb-0">Dit item toont alleen de link.</p></div>
      <div class="header-admin-dropdown-help mt-3" data-header-dropdown="manual"><p class="mb-0">Sla dit hoofditem op. Voeg daarna via <strong>Submenu-item toevoegen</strong> in het overzicht de gewenste links toe.</p></div>
      <?php foreach (['programme', 'speltakken'] as $dropdownType): ?>
      <div class="header-admin-dropdown-help mt-3" data-header-dropdown="<?= e($dropdownType) ?>">
        <p><?= $dropdownType === 'programme' ? 'Actieve speltakken met links naar hun programma op de programmapagina:' : 'Actieve speltakken met links naar hun eigen pagina:' ?></p>
        <?php if ($headerSpeltakken): ?>
        <ul class="list-unstyled mb-3">
          <?php foreach ($headerSpeltakken as $speltak): $path = $dropdownType === 'programme' ? programma_url($speltak['slug']) : speltak_url($speltak['slug']); ?>
          <li><?= header_menu_icon('link') ?><span><?= e($speltak['naam']) ?></span><a href="../<?= e($path) ?>" target="_blank" rel="noopener">/<?= e($path) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?><p>Er zijn momenteel geen actieve speltakken.</p><?php endif; ?>
        <p class="mb-0 text-body-secondary">Deze lijst wordt automatisch bijgewerkt. Namen, zichtbaarheid en volgorde beheer je bij <a href="speltakken.php">Speltakken</a>.</p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <input type="hidden" name="dropdown_type" value="none">
    <?php endif; ?>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="submit" class="btn d-inline-flex align-items-center gap-2"><?= header_menu_icon('check') ?>Menu-item opslaan</button>
      <a href="settings.php?tab=header" class="btn header-admin-cancel">Annuleren</a>
    </div>
  </form>
</div>

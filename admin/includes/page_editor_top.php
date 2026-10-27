<?php
/**
 * Gedeelde shell voor pagina-editors. De aanroeper levert $editorNavigation:
 * public_url (null wanneer niet publiek), unavailable_text en optioneel
 * back_url, back_label, tabs (label/url per sleutel) en active_tab.
 * Velden en opslag blijven in de betreffende editor. Sluit af met page_editor_bottom.php.
 */
?>
<div class="page-editor">
  <div class="page-editor-navigation">
    <nav class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3 mb-3" aria-label="Paginanavigatie">
      <a class="btn page-editor-back d-inline-flex align-items-center justify-content-center gap-2" href="<?= e($editorNavigation['back_url'] ?? 'paginas.php') ?>">
        <?= fa_inline_icon('arrow-left') ?>
        <span><?= e($editorNavigation['back_label'] ?? 'Terug naar paginaoverzicht') ?></span>
      </a>
      <div class="d-flex flex-column gap-2">
        <?php if (!empty($editorNavigation['public_url'])): ?>
        <a class="btn d-inline-flex align-items-center justify-content-center gap-2" href="<?= e($editorNavigation['public_url']) ?>" target="_blank" rel="noopener">
          <?= fa_inline_icon('external-link') ?>
          <span>Bekijken op de website</span><span class="visually-hidden"> (opent in een nieuw tabblad)</span>
        </a>
        <?php else: ?>
        <button class="btn d-inline-flex align-items-center justify-content-center gap-2" type="button" disabled aria-describedby="page-editor-unavailable">
          <?= fa_inline_icon('external-link') ?>
          <span>Bekijken op de website</span>
        </button>
        <p class="page-editor-note mb-0" id="page-editor-unavailable"><?= e($editorNavigation['unavailable_text'] ?? 'Sla de pagina eerst op om deze te bekijken.') ?></p>
        <?php endif; ?>
      </div>
    </nav>
    <?php if (count($editorNavigation['tabs'] ?? []) > 1): ?>
    <nav class="tabs mb-0 pb-3" aria-label="Paginaonderdelen">
      <?php foreach ($editorNavigation['tabs'] as $editorTabKey => $editorTab): $editorTabActive = $editorTabKey === ($editorNavigation['active_tab'] ?? null); ?>
      <a class="<?= $editorTabActive ? 'active' : '' ?>" href="<?= e($editorTab['url']) ?>"<?= $editorTabActive ? ' aria-current="page"' : '' ?>><?= e($editorTab['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
  <div class="page-editor-content">

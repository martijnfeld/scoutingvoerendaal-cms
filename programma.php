<?php
/** Volledig programma met een eigen sectie per actieve speltak. */
require_once __DIR__ . '/includes/functions.php';

$speltakken = get_speltakken(true);
$intro = trim(get_setting('programma_intro'));
$pageTitle = 'Programma — ' . get_setting('org_naam');
$metaDescription = $intro !== '' ? $intro : get_setting('meta_description');
$canonicalUrl = programma_url('', true);
$activeNav = 'programma';
$useBaseHref = true;
$gaPage = ['page_type' => 'programma'];
$jsonLd = [breadcrumb_json_ld([['Home', absolute_url('')], ['Programma', $canonicalUrl]])];

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content" id="programma">
  <div class="container">
    <?= breadcrumbs_html([['Home', './'], ['Programma', programma_url()]]) ?>
    <h1>Programma</h1>
    <?php if ($intro !== ''): ?><p class="programma-intro"><?= e($intro) ?></p><?php endif; ?>

    <?php if ($speltakken): ?>
    <?php include __DIR__ . '/includes/programma_list.php'; ?>
    <?php else: ?>
    <p>Er zijn op dit moment geen speltakken.</p>
    <?php endif; ?>

    <p class="text-center text-body-secondary small"><?= e(get_setting('programma_footer_note')) ?></p>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

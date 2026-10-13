<?php
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$page = $slug !== '' ? get_page_by_slug($slug) : null;

if (!$page || !$page['actief']) {
    require __DIR__ . '/404.php';
    exit;
}

$orgNaam = get_setting('org_naam');
$pageTitle = $page['titel'] . ' — ' . $orgNaam;
$metaDescription = ($page['meta_omschrijving'] !== null && $page['meta_omschrijving'] !== '')
    ? $page['meta_omschrijving']
    : get_setting('meta_description');
$canonicalUrl = page_url($page['slug'], true);
$currentPageSlug = $page['slug'];
// Eigen deelafbeelding van de pagina; anders de algemene (zie site_layout_top.php).
if (!empty($page['og_afbeelding'])) {
    $ogImage = $page['og_afbeelding'];
}
$jsonLd = [breadcrumb_json_ld([
    ['Home', rtrim(get_setting('site_url'), '/') . '/'],
    [$page['titel'], $canonicalUrl],
])];

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content">
  <div class="container pagina-container">
    <h1><?= e($page['titel']) ?></h1>
    <div class="pagina-body"><?= add_lazy_loading($page['inhoud']) /* HTML toegestaan, beheerd via CMS (CKEditor) */ ?></div>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

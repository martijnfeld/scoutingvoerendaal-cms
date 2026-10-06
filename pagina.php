<?php
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$page = $slug !== '' ? get_page_by_slug($slug) : null;

if (!$page || !$page['actief']) {
    http_response_code(404);
    $page = null;
}

$orgNaam = get_setting('org_naam');
$pageTitle = ($page ? $page['titel'] : 'Pagina niet gevonden') . ' — ' . $orgNaam;
$metaDescription = ($page && $page['meta_omschrijving'] !== null && $page['meta_omschrijving'] !== '')
    ? $page['meta_omschrijving']
    : get_setting('meta_description');
$canonicalUrl = $page ? page_url($page['slug'], true) : rtrim(get_setting('site_url'), '/') . '/';
$currentPageSlug = $page['slug'] ?? null;

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content">
  <div class="container pagina-container">
    <?php if ($page): ?>
    <h1><?= e($page['titel']) ?></h1>
    <div class="pagina-body"><?= $page['inhoud'] /* HTML toegestaan, beheerd via CMS (CKEditor) */ ?></div>
    <?php else: ?>
    <h1>Pagina niet gevonden</h1>
    <p>Deze pagina bestaat niet (meer). <a href="index.php">Terug naar de homepage</a>.</p>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

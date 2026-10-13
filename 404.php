<?php
/**
 * Foutpagina "niet gevonden" (HTTP 404). Wordt gebruikt voor:
 * - onbekende URL's (catch-all rewrite-regel onderaan de rewrites in .htaccess);
 * - onbekende/inactieve losse pagina's en speltakken: pagina.php en
 *   speltak.php doen dan `require __DIR__ . '/404.php'; exit;`.
 * Optioneel vooraf te zetten: $notFoundTitle (kop, bv. "Speltak niet gevonden").
 *
 * Zet altijd een <base href>: een onbekende URL kan "diep" liggen
 * (/a/b/c), en dan moeten de relatieve links naar CSS/JS en ./ blijven werken.
 */
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$notFoundTitle = $notFoundTitle ?? 'Pagina niet gevonden';
$pageTitle = $notFoundTitle . ' — ' . get_setting('org_naam');
$metaDescription = get_setting('meta_description');
$canonicalUrl = absolute_url('');
$useBaseHref = true;
$currentPageSlug = null;
$activeNav = null;
$jsonLd = [];
$gaPage = ['page_type' => 'niet_gevonden'];

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content niet-gevonden">
  <div class="container pagina-container">
    <p class="niet-gevonden-code" aria-hidden="true">404</p>
    <h1><?= e($notFoundTitle) ?></h1>
    <p>Deze pagina bestaat niet (meer). Misschien is de link verouderd of zit er een typfout in het adres.</p>
    <div class="niet-gevonden-knoppen">
      <a href="./" class="btn btn-home"><i class="fa-solid fa-house" aria-hidden="true"></i> Terug naar Home</a>
      <a href="<?= e(speltakken_url()) ?>" class="btn btn-ghost">Bekijk alle speltakken</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

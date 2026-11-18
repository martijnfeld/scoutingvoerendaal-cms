<?php
/**
 * Overzichtspagina van alle actieve speltakken (/speltakken, via de
 * rewrite-regel in .htaccess), met per speltak een link naar de eigen
 * pagina (speltak.php).
 */
require_once __DIR__ . '/includes/functions.php';

if (!speltakken_page_active()) {
    require __DIR__ . '/404.php';
    exit;
}

$speltakken = get_speltakken(true);

$orgNaam = get_setting('org_naam');
$intro = trim(get_setting('speltakken_intro'));
$pageTitle = 'Speltakken — ' . $orgNaam;
$metaDescription = $intro !== '' ? $intro : get_setting('meta_description');
$canonicalUrl = speltakken_url(true);
$activeNav = 'speltakken';
$useBaseHref = true;
$gaPage = ['page_type' => 'speltakken'];

$crumbs = [['Home', './'], ['Speltakken', speltakken_url()]];
$listItems = [];
foreach ($speltakken as $i => $sp) {
    $listItems[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $sp['naam'], 'url' => speltak_url($sp['slug'], true)];
}
$jsonLd = [breadcrumb_json_ld([['Home', absolute_url('')], ['Speltakken', $canonicalUrl]])];
if ($listItems) {
    $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Speltakken', 'itemListElement' => $listItems];
}

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content">
  <div class="container">
    <?= breadcrumbs_html($crumbs) ?>
    <h1>Speltakken</h1>
    <?php if ($intro !== ''): ?><p class="speltakken-intro"><?= e($intro) ?></p><?php endif; ?>

    <?php if ($speltakken): ?>
    <ul class="speltak-cards">
      <?php foreach ($speltakken as $sp): ?>
      <li>
        <a class="speltak-card" href="<?= e(speltak_url($sp['slug'])) ?>" style="--speltak-kleur:<?= e($sp['kleur']) ?>">
          <span class="speltak-logo">
            <?php if (!empty($sp['logo'])): ?>
            <img src="<?= e($sp['logo']) ?>" alt="Logo <?= e($sp['naam']) ?>" width="96" height="96" loading="lazy">
            <?php else: ?>
            <span class="speltak-logo-fallback" aria-hidden="true"><?= e(preg_match('/^./us', $sp['naam'], $m) ? $m[0] : '') ?></span>
            <?php endif; ?>
          </span>
          <div class="speltak-card-body">
            <h2><?= e($sp['naam']) ?></h2>
            <?php if (!empty($sp['leeftijd'])): ?><span class="speltak-card-fact"><i class="fa-solid fa-child" aria-hidden="true"></i> <?= e($sp['leeftijd']) ?></span><?php endif; ?>
            <?php if (!empty($sp['dag_tijd'])): ?><span class="speltak-card-fact"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e($sp['dag_tijd']) ?></span><?php endif; ?>
            <span class="speltak-card-more">Meer over de <?= e($sp['naam']) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
          </div>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p>Er zijn op dit moment geen speltakken.</p>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

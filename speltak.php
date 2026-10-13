<?php
/**
 * Eigen pagina van één speltak (/speltakken/<slug>, via de rewrite-regel in
 * .htaccess): logo, leeftijd, opkomsttijd, leiding, de inhoud uit het
 * beheerpaneel (CKEditor) en het programma (Scoutdash-feed via
 * api/opkomsten.php, anders de toelichting).
 */
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$sp = $slug !== '' ? get_speltak_by_slug($slug) : null;

if (!$sp || !$sp['actief']) {
    $notFoundTitle = 'Speltak niet gevonden';
    require __DIR__ . '/404.php';
    exit;
}

$orgNaam = get_setting('org_naam');
$pageTitle = $sp['naam'] . ($sp['leeftijd'] ? ' (' . $sp['leeftijd'] . ')' : '') . ' — ' . $orgNaam;
$metaDescription = speltak_meta_description($sp);
$canonicalUrl = speltak_url($sp['slug'], true);
$activeNav = 'speltakken';
$useBaseHref = true;
$gaPage = ['page_type' => 'speltak', 'page_slug' => $sp['slug']];

$crumbs = [['Home', './'], ['Speltakken', speltakken_url()], [$sp['naam'], speltak_url($sp['slug'])]];
$jsonLd = [
    speltak_json_ld($sp),
    breadcrumb_json_ld([
        ['Home', absolute_url('')],
        ['Speltakken', speltakken_url(true)],
        [$sp['naam'], $canonicalUrl],
    ]),
];
$andere = array_values(array_filter(get_speltakken(true), fn($o) => (int) $o['id'] !== (int) $sp['id']));

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="pagina-content">
  <div class="container pagina-container">
    <?= breadcrumbs_html($crumbs) ?>
    <div class="speltak-titel" style="--speltak-kleur:<?= e($sp['kleur']) ?>">
      <?php if (!empty($sp['logo'])): ?>
      <span class="speltak-logo"><img src="<?= e($sp['logo']) ?>" alt="Logo <?= e($sp['naam']) ?>" width="96" height="96"></span>
      <?php endif; ?>
      <h1><?= e($sp['naam']) ?></h1>
    </div>

    <?php if (!empty($sp['leeftijd']) || !empty($sp['dag_tijd']) || !empty($sp['leiding'])): ?>
    <dl class="speltak-feiten" style="--speltak-kleur:<?= e($sp['kleur']) ?>">
      <?php if (!empty($sp['leeftijd'])): ?>
      <div>
        <dt><i class="fa-solid fa-child" aria-hidden="true"></i> Leeftijd</dt>
        <dd><?= e($sp['leeftijd']) ?></dd>
      </div>
      <?php endif; ?>
      <?php if (!empty($sp['dag_tijd'])): ?>
      <div>
        <dt><i class="fa-regular fa-clock" aria-hidden="true"></i> Wanneer</dt>
        <dd><?= e($sp['dag_tijd']) ?></dd>
      </div>
      <?php endif; ?>
      <?php if (!empty($sp['leiding'])): ?>
      <div>
        <dt><i class="fa-solid fa-users" aria-hidden="true"></i> Leiding</dt>
        <dd><?= e($sp['leiding']) ?></dd>
      </div>
      <?php endif; ?>
    </dl>
    <?php endif; ?>

    <?php if (trim((string) $sp['inhoud']) !== ''): ?>
    <div class="pagina-body"><?= add_lazy_loading($sp['inhoud']) /* HTML toegestaan, beheerd via CMS (CKEditor) */ ?></div>
    <?php endif; ?>

    <h2 class="speltak-programma-titel">Programma</h2>
    <?php if (!empty($sp['feed_url'])): ?>
      <?php if (!empty($sp['afmeld_url'])): ?>
      <p class="speltak-afmelden">Afmelden voor een opkomst? <a href="<?= e($sp['afmeld_url']) ?>" target="_blank" rel="noopener"<?= ga_event_attrs('afmeld_click', ['speltak' => $sp['slug']]) ?>>Klik hier</a>.</p>
      <?php endif; ?>
      <div class="prog-list" data-feed-slug="<?= e($sp['slug']) ?>" id="feed-<?= e($sp['slug']) ?>">
        <p class="prog-loading">Programma wordt geladen&hellip;</p>
      </div>
    <?php elseif (!empty($sp['toelichting'])): ?>
      <div class="pagina-body"><?= add_lazy_loading($sp['toelichting']) /* HTML toegestaan */ ?></div>
    <?php else: ?>
      <p class="prog-empty">Er is op dit moment geen programma beschikbaar.</p>
    <?php endif; ?>

    <?php if ($andere): ?>
    <nav class="speltak-andere" aria-label="Andere speltakken">
      <h2>Andere speltakken</h2>
      <ul>
        <?php foreach ($andere as $o): ?>
        <li><a href="<?= e(speltak_url($o['slug'])) ?>" style="background:<?= e($o['kleur']) ?>"><?= e($o['naam']) ?><?= $o['leeftijd'] ? ' <small>(' . e($o['leeftijd']) . ')</small>' : '' ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>

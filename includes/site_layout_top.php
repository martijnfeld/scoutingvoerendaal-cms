<?php
/**
 * Bovenkant van elke publieke pagina (index.php, pagina.php): <head>,
 * meldingsbalk, header/nav en de openende <main>. Verwacht (optioneel)
 * vooraf gedefinieerd: $pageTitle, $metaDescription, $canonicalUrl,
 * $ogImage, $isHome (bool), $currentPageSlug, $jsonLd (lijst van
 * structured-data-arrays, zie json_ld_script()), $gaPage (paginacontext
 * voor Google Analytics), $activeNav ('speltakken' markeert dat
 * menu-item) en $useBaseHref (bool, voor pagina's in een submap-URL).
 */
$pageTitle = $pageTitle ?? get_setting('site_title');
$metaDescription = $metaDescription ?? get_setting('meta_description');
$canonicalUrl = $canonicalUrl ?? get_setting('site_url');
$ogImage = $ogImage ?? get_setting('og_image');
$isHome = $isHome ?? false;
$currentPageSlug = $currentPageSlug ?? null;
$jsonLd = $jsonLd ?? [];
$activeNav = $activeNav ?? null;
// Pagina's met een URL in een "submap" (/speltakken/welpen) krijgen een
// <base> naar de websitemap, zodat alle relatieve links (CSS/JS, logo,
// geüploade afbeeldingen, api/opkomsten.php, ./#programma) blijven werken.
// Afgeleid van het pad van het PHP-script zelf, dus ook goed in een submap
// en onafhankelijk van site_url.
$baseHref = !empty($useBaseHref)
    ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/'
    : null;
if ($isHome) {
    array_unshift($jsonLd, organization_json_ld());
}
// Naar de homepage linken als "./" (de websitemap), niet als "index.php":
// zo is er maar één URL voor de homepage (ook bij installatie in een submap).
$homeHref = $isHome ? '' : './';
// Leeg als Google Analytics uit staat; dan ook geen cookiebanner.
$gaId = ga_measurement_id();
// Paginacontext die met elke GA-gebeurtenis meegaat (zie assets/js/gtag.js).
$gaPage = $gaPage ?? ($isHome ? ['page_type' => 'home']
    : ($currentPageSlug !== null ? ['page_type' => 'pagina', 'page_slug' => $currentPageSlug] : ['page_type' => 'niet_gevonden']));

// Content-Security-Policy: de browser voert alleen scripts uit van onze
// eigen site (en Google Analytics), nooit inline-scripts of event-handlers.
// De cookiebanner (cookieconsent) komt van jsDelivr; de cookieloze pings van
// Consent Mode vallen onder connect-src *.google-analytics.com.
// Mocht er via CMS-HTML of de Scoutdash-feed toch een script in de pagina
// belanden, dan wordt het dus niet uitgevoerd. Inline styles blijven
// toegestaan (CKEditor-opmaak); iframes alleen via https (bv. YouTube).
if (!headers_sent()) {
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "script-src 'self' https://www.googletagmanager.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "font-src 'self' https://cdnjs.cloudflare.com data:",
        "img-src 'self' https: data:",
        "media-src 'self' https:",
        "frame-src https:",
        // jsDelivr alleen voor de source maps van Bootstrap (opgevraagd door DevTools).
        "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://cdn.jsdelivr.net",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<?php if ($baseHref !== null): ?>
<base href="<?= e($baseHref) ?>">
<?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(get_setting('org_naam')) ?>">
<meta property="og:locale" content="nl_NL">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= e(absolute_url($ogImage)) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<?php if ($ogImage): ?>
<meta name="twitter:image" content="<?= e(absolute_url($ogImage)) ?>">
<?php endif; ?>
<?php if (google_site_verification() !== ''): ?>
<meta name="google-site-verification" content="<?= e(google_site_verification()) ?>">
<?php endif; ?>
<?php if (get_setting('logo_image')): ?>
<link rel="icon" type="image/png" href="<?= e(get_setting('logo_image')) ?>">
<?php endif; ?>
<?php foreach ($jsonLd as $block): ?>
<?= json_ld_script($block) ?>

<?php endforeach; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<?= font_awesome_head('assets/js/async-css.js') ?>
<?php if ($gaId): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@<?= COOKIECONSENT_VERSION ?>/dist/cookieconsent.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body>

<?php if (get_setting('notice_bar_enabled') === '1'): ?>
<div class="noticebar">
  <?= get_setting('notice_bar_text') /* HTML toegestaan, beheerd via CMS */ ?>
</div>
<?php endif; ?>

<header>
  <div class="navwrap">
    <a href="<?= $homeHref ?>#top" class="brand">
      <?php if (get_setting('logo_image')): ?>
      <img src="<?= e(get_setting('logo_image')) ?>" alt="Logo <?= e(get_setting('org_naam')) ?>">
      <?php endif; ?>
      <span><?= e(get_setting('org_naam')) ?></span>
    </a>
    <button class="navtoggle" id="navToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <nav id="mainNav">
      <ul>
        <li><a href="<?= $homeHref ?>#top">Home</a></li>
        <li><a href="<?= $homeHref ?>#programma">Programma</a></li>
        <li><a href="<?= e(speltakken_url()) ?>"<?= $activeNav === 'speltakken' ? ' class="active"' : '' ?>>Speltakken</a></li>
        <li><a href="<?= $homeHref ?>#lidworden">Lid worden</a></li>
        <li><a href="<?= $homeHref ?>#verhuur">Verhuur</a></li>
        <li><a href="<?= $homeHref ?>#contact">Contact</a></li>
        <?php foreach (get_pages_in_menu() as $mp): ?>
        <li><a href="<?= e(page_url($mp['slug'])) ?>"<?= $currentPageSlug === $mp['slug'] ? ' class="active"' : '' ?>><?= e($mp['titel']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>

<main>

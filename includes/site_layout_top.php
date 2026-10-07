<?php
/**
 * Bovenkant van elke publieke pagina (index.php, pagina.php): <head>,
 * meldingsbalk, header/nav en de openende <main>. Verwacht (optioneel)
 * vooraf gedefinieerd: $pageTitle, $metaDescription, $canonicalUrl,
 * $ogImage, $isHome (bool), $currentPageSlug.
 */
$pageTitle = $pageTitle ?? get_setting('site_title');
$metaDescription = $metaDescription ?? get_setting('meta_description');
$canonicalUrl = $canonicalUrl ?? get_setting('site_url');
$ogImage = $ogImage ?? get_setting('og_image');
$isHome = $isHome ?? false;
$currentPageSlug = $currentPageSlug ?? null;
$homeHref = $isHome ? '' : 'index.php';

// Content-Security-Policy: de browser voert alleen scripts uit van onze
// eigen site (en Google Analytics), nooit inline-scripts of event-handlers.
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
<meta property="og:image" content="<?= e(rtrim(get_setting('site_url'), '/') . '/' . ltrim($ogImage, '/')) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e(get_setting('org_naam')) ?>">
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<?php if ($ogImage): ?>
<meta name="twitter:image" content="<?= e(rtrim(get_setting('site_url'), '/') . '/' . ltrim($ogImage, '/')) ?>">
<?php endif; ?>
<?php if (get_setting('logo_image')): ?>
<link rel="icon" type="image/png" href="<?= e(get_setting('logo_image')) ?>">
<?php endif; ?>
<?php if ($isHome): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SportsOrganization",
  "name": "<?= addslashes(get_setting('org_naam')) ?>",
  "url": "<?= addslashes(get_setting('site_url')) ?>",
<?php if (get_setting('logo_image')): ?>
  "logo": "<?= addslashes(rtrim(get_setting('site_url'), '/') . '/' . ltrim(get_setting('logo_image'), '/')) ?>",
<?php endif; ?>
<?php if (get_setting('og_image')): ?>
  "image": "<?= addslashes(rtrim(get_setting('site_url'), '/') . '/' . ltrim(get_setting('og_image'), '/')) ?>",
<?php endif; ?>
  "email": "<?= addslashes(get_setting('org_email')) ?>",
  "telephone": "<?= addslashes(get_setting('org_telefoon')) ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "<?= addslashes(get_setting('org_straat')) ?>",
    "postalCode": "<?= addslashes(get_setting('org_postcode')) ?>",
    "addressLocality": "<?= addslashes(get_setting('org_plaats')) ?>",
    "addressCountry": "<?= addslashes(get_setting('org_land')) ?>"
  },
  "sameAs": [
    "<?= addslashes(get_setting('social_facebook')) ?>",
    "<?= addslashes(get_setting('social_instagram')) ?>",
    "<?= addslashes(get_setting('social_youtube')) ?>"
  ]
}
</script>
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="assets/css/style.css">
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

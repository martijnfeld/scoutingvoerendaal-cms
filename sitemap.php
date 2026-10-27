<?php
/**
 * Dynamische sitemap: de homepage, het speltakkenoverzicht en de pagina
 * van elke actieve speltak, plus alle actieve pagina's uit de tabel
 * `pages`. Wordt via .htaccess ook op /sitemap.xml geserveerd (als
 * mod_rewrite beschikbaar is); robots.txt verwijst rechtstreeks naar dit
 * bestand zodat het ook zonder mod_rewrite werkt.
 */
require_once __DIR__ . '/includes/functions.php';

$baseUrl = rtrim(get_setting('site_url'), '/');

$urls = [
    ['loc' => $baseUrl . '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => programma_url('', true), 'changefreq' => 'weekly', 'priority' => '0.9'],
];

try {
    $speltakken = get_speltakken(true);
    if (speltakken_page_active()) {
        $urls[] = ['loc' => speltakken_url(true), 'changefreq' => 'monthly', 'priority' => '0.9'];
    }
    foreach ($speltakken as $sp) {
        $urls[] = [
            'loc'        => speltak_url($sp['slug'], true),
            'lastmod'    => !empty($sp['bijgewerkt']) ? date('Y-m-d', strtotime($sp['bijgewerkt'])) : null,
            // Het programma (Scoutdash-feed) verandert wekelijks.
            'changefreq' => 'weekly',
            'priority'   => '0.8',
        ];
    }
    foreach (get_pages(true) as $page) {
        $urls[] = [
            'loc'        => page_url($page['slug'], true),
            'lastmod'    => $page['bijgewerkt'] ? date('Y-m-d', strtotime($page['bijgewerkt'])) : null,
            'changefreq' => 'monthly',
            'priority'   => '0.8',
        ];
    }
} catch (Throwable $e) {
    // Database niet bereikbaar: lever in elk geval de homepage.
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
<?php if (!empty($url['lastmod'])): ?>
    <lastmod><?= $url['lastmod'] ?></lastmod>
<?php endif; ?>
    <changefreq><?= $url['changefreq'] ?></changefreq>
    <priority><?= $url['priority'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>

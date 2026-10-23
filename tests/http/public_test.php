<?php
/** De publieke site zoals een bezoeker hem ziet (draaiende webserver, TEST_BASE_URL). */

test('homepage laadt zonder PHP-fouten', function () {
    $r = http_get('');
    assert_status(200, $r, 'homepage');
    assert_contains('<html lang="nl"', $r['body']);
    assert_no_php_errors($r, 'homepage');
});

test('homepage stuurt CSP- en beveiligingsheaders', function () {
    $r = http_get('');
    $csp = http_header($r, 'Content-Security-Policy');
    assert_contains("default-src 'self'", $csp);
    assert_contains("object-src 'none'", $csp);
    assert_matches("/script-src [^;]*'self'/", $csp);
    assert_false((bool) preg_match("/script-src [^;]*'unsafe-inline'/", $csp), 'geen unsafe-inline voor scripts');
    assert_same('nosniff', http_header($r, 'X-Content-Type-Options'));
    assert_same('SAMEORIGIN', http_header($r, 'X-Frame-Options'));
    assert_same('strict-origin-when-cross-origin', http_header($r, 'Referrer-Policy'));
    assert_contains('camera=()', http_header($r, 'Permissions-Policy'));
});

test('Google Analytics alleen samen met cookiebanner en Consent Mode', function () {
    $r = http_get('');
    if (strpos($r['body'], 'googletagmanager.com/gtag/js') === false) {
        // Standaard (verse installatie): GA uit, dus ook geen banner.
        assert_not_contains('cookieconsent', $r['body']);
        assert_not_contains('data-cc="show-preferencesModal"', $r['body']);
        assert_not_contains('ga-events.js', $r['body']);
        return;
    }
    assert_matches('#<script src="[^"]*assets/js/ga-events\.js[^"]*"></script>#', $r['body']);
    assert_contains('data-ga-page="{&quot;page_type&quot;:&quot;home&quot;}"', $r['body']);
    $events = http_get('assets/js/ga-events.js');
    assert_status(200, $events, 'ga-events.js');
    assert_contains('cookieconsent.umd.js', $r['body']);
    assert_contains('cookieconsent.css', $r['body']);
    assert_matches('#<script src="[^"]*assets/js/gtag\.js[^"]*" data-ga-id="G-[A-Z0-9]+" data-cc-texts="#', $r['body']);
    assert_contains('data-cc="show-preferencesModal"', $r['body']);
    $js = http_get('assets/js/gtag.js');
    assert_status(200, $js, 'gtag.js');
    assert_contains("'consent', 'default'", $js['body']);
});

test('sitemap.xml en robots.txt', function () {
    $r = http_get('sitemap.xml');
    assert_status(200, $r, 'sitemap.xml');
    assert_contains('xml', http_header($r, 'Content-Type'));
    assert_contains('<urlset', $r['body']);
    assert_no_php_errors($r, 'sitemap.xml');

    $r = http_get('robots.txt');
    assert_status(200, $r, 'robots.txt');
});

/** Alle JSON-LD-blokken uit een pagina, gedecodeerd (faalt bij ongeldige JSON). */
function http_json_ld_blocks(string $body): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $body, $m);
    $blocks = [];
    foreach ($m[1] as $json) {
        $data = json_decode($json, true);
        assert_true(is_array($data), 'geldige JSON-LD: ' . test_export($json));
        $blocks[] = $data;
    }
    return $blocks;
}

test('homepage heeft geldige JSON-LD van de organisatie', function () {
    $blocks = http_json_ld_blocks(http_get('')['body']);
    assert_same(['NGO'], array_column($blocks, '@type'));
    assert_same('https://schema.org', $blocks[0]['@context']);
    assert_true(($blocks[0]['name'] ?? '') !== '', 'naam ingevuld');
});

test('links naar de homepage gaan naar ./, niet naar index.php', function () {
    $r = http_get('deze-pagina-bestaat-echt-niet');
    assert_not_contains('href="index.php', $r['body']);
    assert_contains('href="./#programma"', $r['body']);
    assert_same([], http_json_ld_blocks($r['body']), 'geen structured data op de 404-pagina');
});

test('HTML, CSS en JS worden gecomprimeerd verstuurd', function () {
    foreach (['', 'assets/css/style.css', 'assets/js/main.js'] as $path) {
        $r = http_request('GET', $path, null, ['Accept-Encoding' => 'gzip']);
        assert_status(200, $r, '/' . $path);
        assert_same('gzip', http_header($r, 'Content-Encoding'), '/' . $path);
    }
    $r = http_request('GET', '', null);
    assert_same('', http_header($r, 'Content-Encoding'), 'zonder Accept-Encoding ongecomprimeerd');
});

test('api/opkomsten.php geeft JSON', function () {
    $r = http_get('api/opkomsten.php');
    assert_status(200, $r, 'api/opkomsten.php');
    assert_contains('application/json', http_header($r, 'Content-Type'));
    assert_same('no-store', http_header($r, 'Cache-Control'));
    assert_true(is_array(json_decode($r['body'], true)), 'geldige JSON: ' . test_export($r['body']));
});

test('onbekende pagina geeft 404', function () {
    $r = http_get('deze-pagina-bestaat-echt-niet');
    assert_status(404, $r, '/<onbekende slug>');
    assert_contains('Pagina niet gevonden', $r['body']);
    assert_no_php_errors($r, '404-pagina');
});

test('onbekende URL in een submap geeft de 404-pagina met Home-knop', function () {
    foreach (['bestaat/echt/niet', 'bestaat-niet.html', 'speltakken/bestaat-echt-niet'] as $path) {
        $r = http_get($path);
        assert_status(404, $r, '/' . $path);
        assert_contains('class="btn btn-home"', $r['body'], '/' . $path);
        assert_matches('#<base href="[^"]*/">#', $r['body'], '/' . $path);
        assert_no_php_errors($r, '/' . $path);
    }
});

test('ontbrekend bestand onder assets/ krijgt geen 404-pagina', function () {
    $r = http_get('assets/css/bestaat-niet.css');
    assert_status(404, $r, 'ontbrekend asset');
    assert_not_contains('btn-home', $r['body']);
});

test('oude pagina.php?slug=-links sturen permanent door', function () {
    $r = http_get('pagina.php?slug=ledeninfo');
    assert_status(301, $r, 'pagina.php?slug=');
    assert_matches('#/ledeninfo$#', http_header($r, 'Location'));
});

/** Slug van de eerste speltak op het overzicht (er is altijd minstens één, zie install.sql). */
function http_first_speltak_slug(string $overviewBody): string
{
    if (!preg_match('#<a class="speltak-card" href="speltakken/([a-z0-9-]+)"#', $overviewBody, $m)) {
        fail('Geen speltak gevonden op /speltakken');
    }
    return $m[1];
}

test('speltakkenoverzicht met kruimelpad, JSON-LD en links naar elke speltak', function () {
    $r = http_get('speltakken');
    assert_status(200, $r, '/speltakken');
    assert_no_php_errors($r, '/speltakken');
    assert_contains('<h1>Speltakken</h1>', $r['body']);
    assert_contains('<nav class="breadcrumbs" aria-label="Kruimelpad">', $r['body']);
    assert_matches('#<link rel="canonical" href="https?://[^"]+/speltakken">#', $r['body']);
    assert_matches('#<base href="/[^"]*">#', $r['body']);
    $types = array_column(http_json_ld_blocks($r['body']), '@type');
    assert_same(['BreadcrumbList', 'ItemList'], $types);
    http_first_speltak_slug($r['body']);
});

test('pagina van een speltak: gegevens, programma, kruimelpad en SEO', function () {
    $slug = http_first_speltak_slug(http_get('speltakken')['body']);
    $r = http_get('speltakken/' . $slug);
    assert_status(200, $r, '/speltakken/' . $slug);
    assert_no_php_errors($r, 'speltakpagina');
    assert_matches('#<base href="/[^"]*">#', $r['body'], 'base voor relatieve links');
    assert_matches('#<link rel="canonical" href="https?://[^"]+/speltakken/' . $slug . '">#', $r['body']);
    assert_matches('#<meta name="description" content="[^"]{20,}">#', $r['body']);
    assert_contains('<h2 class="speltak-programma-titel">Programma</h2>', $r['body']);
    assert_contains('<li class="breadcrumb-item"><a href="speltakken">Speltakken</a></li>', $r['body']);
    assert_contains('href="./#programma"', $r['body'], 'menu linkt naar de homepage');

    $blocks = http_json_ld_blocks($r['body']);
    assert_same(['Organization', 'BreadcrumbList'], array_column($blocks, '@type'));
    assert_matches('#/speltakken/' . $slug . '$#', $blocks[0]['url']);
    assert_same('NGO', $blocks[0]['parentOrganization']['@type']);
    $home = http_json_ld_blocks(http_get('')['body']);
    assert_same($home[0]['@id'], $blocks[0]['parentOrganization']['@id'], 'zelfde organisatie als op de homepage');
    assert_same(3, count($blocks[1]['itemListElement']));
    assert_matches('#/speltakken/' . $slug . '$#', $blocks[1]['itemListElement'][2]['item']);

    $sitemap = http_get('sitemap.xml')['body'];
    assert_contains('/speltakken</loc>', $sitemap);
    assert_contains('/speltakken/' . $slug . '</loc>', $sitemap);
});

test('onbekende speltak geeft 404', function () {
    $r = http_get('speltakken/deze-speltak-bestaat-echt-niet');
    assert_status(404, $r, '/speltakken/<onbekende slug>');
    assert_contains('Speltak niet gevonden', $r['body']);
    assert_no_php_errors($r, '404-speltak');
    assert_same([], http_json_ld_blocks($r['body']), 'geen structured data op de 404-pagina');
});

test('speltak.php?slug= en speltakken.php sturen permanent door', function () {
    $r = http_get('speltak.php?slug=welpen');
    assert_status(301, $r, 'speltak.php?slug=');
    assert_matches('#/speltakken/welpen$#', http_header($r, 'Location'));
    $r = http_get('speltakken.php');
    assert_status(301, $r, 'speltakken.php');
    assert_matches('#/speltakken$#', http_header($r, 'Location'));
});

test('beheerpaneel stuurt door naar de loginpagina', function () {
    $r = http_get('admin/');
    assert_status(302, $r, 'admin/');
    assert_contains('login.php', http_header($r, 'Location'));
});

test('loginpagina met CSRF-token, CSP en veilige sessiecookie', function () {
    $GLOBALS['__cookies'] = [];
    $r = http_get('admin/login.php');
    assert_status(200, $r, 'admin/login.php');
    assert_no_php_errors($r, 'admin/login.php');
    assert_contains("frame-ancestors 'self'", http_header($r, 'Content-Security-Policy'));
    http_csrf_token($r);

    $cookie = '';
    foreach ($r['headers']['set-cookie'] ?? [] as $header) {
        if (strpos($header, 'sv_session=') === 0) {
            $cookie = $header;
        }
    }
    assert_true($cookie !== '', 'sessiecookie heet sv_session');
    assert_matches('/;\s*HttpOnly/i', $cookie);
    assert_matches('/;\s*SameSite=Lax/i', $cookie);
});

test('inloggen zonder geldig CSRF-token lukt niet', function () {
    $GLOBALS['__cookies'] = [];
    http_get('admin/login.php');
    $r = http_post('admin/login.php', ['username' => 'x', 'password' => 'y', 'csrf_token' => 'fout']);
    assert_status(200, $r, 'login zonder token');
    assert_contains('Ongeldige aanvraag', $r['body']);
});

test('uitloggen via GET doet niets', function () {
    $r = http_get('admin/logout.php');
    assert_status(302, $r, 'GET admin/logout.php');
});

test('cronjob weigert zonder geldige sleutel', function () {
    foreach (['cron/cron.php', 'cron/backup_cron.php'] as $script) {
        $r = http_get($script);
        assert_status(403, $r, $script . ' zonder sleutel');
        $r = http_get($script . '?key=wijzig_deze_geheime_sleutel');
        assert_status(403, $r, $script . ' met de standaardsleutel');
    }
});

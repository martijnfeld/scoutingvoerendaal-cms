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

test('sitemap.xml en robots.txt', function () {
    $r = http_get('sitemap.xml');
    assert_status(200, $r, 'sitemap.xml');
    assert_contains('xml', http_header($r, 'Content-Type'));
    assert_contains('<urlset', $r['body']);
    assert_no_php_errors($r, 'sitemap.xml');

    $r = http_get('robots.txt');
    assert_status(200, $r, 'robots.txt');
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

test('oude pagina.php?slug=-links sturen permanent door', function () {
    $r = http_get('pagina.php?slug=ledeninfo');
    assert_status(301, $r, 'pagina.php?slug=');
    assert_matches('#/ledeninfo$#', http_header($r, 'Location'));
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

test('back-up-cronjob weigert zonder geldige sleutel', function () {
    $r = http_get('cron/backup_cron.php');
    assert_status(403, $r, 'zonder sleutel');
    $r = http_get('cron/backup_cron.php?key=wijzig_deze_geheime_sleutel');
    assert_status(403, $r, 'met de standaardsleutel');
});

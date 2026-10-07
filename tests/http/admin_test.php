<?php
/**
 * Beheerpaneel van begin tot eind: (eventueel) installeren via install.php,
 * inloggen, alle schermen openen, een pagina aanmaken en weer verwijderen,
 * uitloggen.
 *
 * Inloggegevens: TEST_ADMIN_USER/TEST_ADMIN_PASS. Met TEST_INSTALL_ADMIN=1
 * (CI, verse database) maakt de test die beheerder zelf aan via install.php;
 * zonder die variabele wordt een lokale database nooit aangepast door
 * install.php.
 */
require_once TEST_ROOT . '/includes/functions.php'; // slugify()

function admin_credentials(): array
{
    return [
        getenv('TEST_ADMIN_USER') ?: 'ci-beheerder',
        getenv('TEST_ADMIN_PASS') ?: 'Ci-test-wachtwoord-12!',
    ];
}

/** Logt in (één keer per proces); slaat de test over als er geen inloggegevens zijn. */
function admin_login(): void
{
    static $loggedIn = false;
    if ($loggedIn) {
        return;
    }
    [$user, $pass] = admin_credentials();
    $GLOBALS['__cookies'] = [];

    if (getenv('TEST_INSTALL_ADMIN') === '1') {
        $r = http_get('install.php');
        assert_no_php_errors($r, 'install.php');
        if (strpos($r['body'], 'value="install_schema"') !== false) {
            $r = http_post('install.php', ['step' => 'install_schema', 'csrf_token' => http_csrf_token($r)]);
            assert_status(302, $r, 'database installeren');
            $r = http_get('install.php');
        }
        if (strpos($r['body'], 'value="create_admin"') !== false) {
            $r = http_post('install.php', [
                'step' => 'create_admin', 'csrf_token' => http_csrf_token($r),
                'username' => $user, 'password' => $pass, 'confirm_password' => $pass,
            ]);
            assert_status(200, $r, 'beheerder aanmaken');
            assert_contains('Beheerdersaccount aangemaakt', $r['body']);
        }
    } elseif (!getenv('TEST_ADMIN_USER')) {
        skip('zet TEST_ADMIN_USER/TEST_ADMIN_PASS (of TEST_INSTALL_ADMIN=1 bij een verse database)');
    }

    $r = http_get('admin/login.php');
    assert_status(200, $r, 'loginpagina');
    $r = http_post('admin/login.php', ['username' => $user, 'password' => $pass, 'csrf_token' => http_csrf_token($r)]);
    if ($r['status'] !== 302) {
        fail('Inloggen als ' . $user . ' mislukt (HTTP ' . $r['status'] . '): '
            . test_export(trim(strip_tags((string) preg_replace('#.*<div class="admin-flash[^>]*>(.*?)</div>.*#s', '$1', $r['body'])))));
    }
    assert_same('index.php', http_header($r, 'Location'));
    $loggedIn = true;
}

test('install.php is dicht zodra er een beheerder is', function () {
    admin_login();
    $r = http_get('install.php');
    assert_status(200, $r, 'install.php');
    assert_contains('Al geïnstalleerd', $r['body']);
    assert_not_contains('name="password"', $r['body']);
});

test('alle beheerschermen laden zonder PHP-fouten', function () {
    admin_login();
    $pages = ['index.php', 'speltakken.php', 'speltak_form.php', 'info_cards.php', 'info_card_form.php',
              'documents.php', 'document_form.php', 'paginas.php', 'pagina_form.php', 'settings.php',
              'uploads.php', 'accounts.php', 'backups.php', 'updates.php', 'controle.php', 'change_password.php'];
    foreach ($pages as $page) {
        $r = http_get('admin/' . $page);
        assert_status(200, $r, 'admin/' . $page);
        assert_no_php_errors($r, 'admin/' . $page);
        assert_contains('name="csrf_token"', $r['body'], 'admin/' . $page . ' (uitlogformulier)');
    }
});

test('formulier opslaan zonder CSRF-token wordt geweigerd', function () {
    admin_login();
    $r = http_post('admin/pagina_form.php', ['titel' => 'Zonder token', 'csrf_token' => 'fout']);
    assert_status(200, $r, 'POST zonder token');
    assert_contains('Ongeldige aanvraag', $r['body']);
});

test('pagina aanmaken, publiek bekijken en verwijderen', function () {
    admin_login();
    $title = 'CI-testpagina ' . bin2hex(random_bytes(3));
    $slug = slugify($title);

    $r = http_get('admin/pagina_form.php');
    $r = http_post('admin/pagina_form.php', [
        'csrf_token' => http_csrf_token($r), 'titel' => $title, 'slug' => '',
        'inhoud' => '<p>Inhoud van de <strong>testpagina</strong></p>', 'meta_omschrijving' => 'Test',
        'actief' => '1', 'volgorde' => '0',
    ]);
    assert_status(302, $r, 'pagina opslaan');

    try {
        $r = http_get($slug);
        assert_status(200, $r, '/' . $slug);
        assert_contains('<h1>' . $title . '</h1>', $r['body']);
        assert_contains('<p>Inhoud van de <strong>testpagina</strong></p>', $r['body']);
        assert_no_php_errors($r, '/' . $slug);

        $r = http_get('sitemap.xml');
        assert_contains('/' . $slug . '</loc>', $r['body'], 'pagina staat in de sitemap');
    } finally {
        $r = http_get('admin/paginas.php');
        $pos = strpos($r['body'], '<strong>' . $title . '</strong>');
        if ($pos !== false && preg_match('/name="action" value="delete">\s*<input type="hidden" name="id" value="(\d+)"/', $r['body'], $m, 0, $pos)) {
            $r = http_post('admin/paginas.php', ['csrf_token' => http_csrf_token($r), 'action' => 'delete', 'id' => $m[1]]);
            assert_status(302, $r, 'pagina verwijderen');
        } else {
            fail('Testpagina niet gevonden in admin/paginas.php');
        }
    }
    assert_status(404, http_get($slug), 'verwijderde pagina');
});

test('uitloggen via POST beëindigt de sessie', function () {
    admin_login();
    $r = http_get('admin/index.php');
    $r = http_post('admin/logout.php', ['csrf_token' => http_csrf_token($r)]);
    assert_status(302, $r, 'uitloggen');
    assert_same('login.php', http_header($r, 'Location'));

    $r = http_get('admin/index.php');
    assert_status(302, $r, 'na uitloggen');
    assert_contains('login.php', http_header($r, 'Location'));
});

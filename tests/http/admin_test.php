<?php
/**
 * Beheerpaneel van begin tot eind: (eventueel) installeren via install.php,
 * inloggen, alle schermen openen, een pagina en een speltak aanmaken en
 * weer verwijderen, uitloggen.
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

/** Controleert de gedeelde shell en de opgeslagen publieke bestemming. */
function assert_page_editor_shell(array $response, ?string $publicUrl, string $backUrl = 'paginas.php'): void
{
    $dom = new DOMDocument();
    @$dom->loadHTML($response['body']);
    $xpath = new DOMXPath($dom);
    $shell = $xpath->query('//div[@class="page-editor"]');
    assert_same(1, $shell->length, 'één gedeelde editor-shell');
    $nav = $xpath->query('.//nav[@aria-label="Paginanavigatie"]', $shell->item(0));
    assert_same(1, $nav->length);
    assert_same(2, $xpath->query('.//svg[@width="18" and @height="18"]', $nav->item(0))->length, 'knopiconen staan direct in de HTML');
    assert_same(0, $xpath->query('.//i', $nav->item(0))->length, 'geen vertraagd geladen iconfont in de knoppen');
    $back = $xpath->query('.//a[contains(@class, "page-editor-back")]', $nav->item(0));
    assert_same($backUrl, $back->item(0)->getAttribute('href'));
    $links = $xpath->query('.//a[@target="_blank"]', $nav->item(0));
    if ($publicUrl !== null) {
        assert_same(1, $links->length);
        assert_same($publicUrl, $links->item(0)->getAttribute('href'));
        assert_same('noopener', $links->item(0)->getAttribute('rel'));
        assert_same(0, $xpath->query('.//button[@disabled]', $nav->item(0))->length);
    } else {
        assert_same(0, $links->length, 'geen publieke link voor nieuwe of ongepubliceerde pagina');
        assert_same(1, $xpath->query('.//button[@disabled and @aria-describedby="page-editor-unavailable"]', $nav->item(0))->length);
        assert_same(1, $xpath->query('.//*[@id="page-editor-unavailable"]', $nav->item(0))->length);
    }
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
    $pages = ['index.php', 'speltakken.php', 'speltak_form.php', 'pagina_form.php?pagina=home&tab=info_cards', 'pagina_form.php?pagina=home&tab=info_cards&actie=nieuw',
              'documents.php', 'document_form.php', 'paginas.php', 'pagina_form.php', 'settings.php', 'settings.php?tab=header',
              'uploads.php', 'accounts.php', 'backups.php', 'updates.php', 'controle.php', 'change_password.php', 'logs.php'];
    foreach ($pages as $page) {
        $r = http_get('admin/' . $page);
        assert_status(200, $r, 'admin/' . $page);
        assert_no_php_errors($r, 'admin/' . $page);
        assert_contains('name="csrf_token"', $r['body'], 'admin/' . $page . ' (uitlogformulier)');
        if (strpos($page, 'pagina_form.php?pagina=') === 0) {
            assert_page_editor_shell($r, '../');
        } elseif ($page === 'pagina_form.php') {
            assert_page_editor_shell($r, null);
        } elseif ($page === 'speltak_form.php') {
            assert_page_editor_shell($r, null, 'speltakken.php');
        }
    }
});

test('formulier opslaan zonder CSRF-token wordt geweigerd', function () {
    admin_login();
    $r = http_post('admin/pagina_form.php', ['titel' => 'Zonder token', 'csrf_token' => 'fout']);
    assert_status(200, $r, 'POST zonder token');
    assert_contains('Ongeldige aanvraag', $r['body']);
});

test('Statistieken & cookies: ongeldig measurement-ID wordt geweigerd', function () {
    admin_login();
    $r = http_get('admin/settings.php?tab=analytics');
    assert_status(200, $r, 'tab Statistieken & cookies');
    assert_no_php_errors($r, 'tab Statistieken & cookies');
    assert_contains('name="ga_measurement_id"', $r['body']);

    $r = http_post('admin/settings.php?tab=analytics', [
        'csrf_token' => http_csrf_token($r), 'tab' => 'analytics',
        'ga_measurement_id' => 'UA-12345678-1', 'cookie_banner_title' => 'Mag niet worden opgeslagen',
    ]);
    assert_status(200, $r, 'opslaan met ongeldig ID');
    assert_contains('Universal Analytics', $r['body']);
    assert_not_contains('value="UA-12345678-1"', $r['body']);
    assert_not_contains('Mag niet worden opgeslagen', $r['body'], 'bij een fout wordt niets opgeslagen');
});

test('Algemeen: ongeldige Search Console-code wordt geweigerd', function () {
    admin_login();
    $r = http_get('admin/settings.php?tab=algemeen');
    assert_contains('name="google_site_verification"', $r['body']);
    $r = http_post('admin/settings.php?tab=algemeen', [
        'csrf_token' => http_csrf_token($r), 'tab' => 'algemeen',
        'google_site_verification' => 'geen"geldige code', 'site_title' => 'Mag niet worden opgeslagen',
    ]);
    assert_status(200, $r, 'opslaan met ongeldige code');
    assert_contains('Ongeldige verificatiecode', $r['body']);
    assert_not_contains('Mag niet worden opgeslagen', $r['body'], 'bij een fout wordt niets opgeslagen');
});

test('pagina aanmaken, publiek bekijken en verwijderen', function () {
    admin_login();
    $title = 'CI-testpagina ' . bin2hex(random_bytes(3));
    $slug = slugify($title);

    $r = http_get('admin/pagina_form.php');
    $r = http_post('admin/pagina_form.php', [
        'csrf_token' => http_csrf_token($r), 'titel' => $title, 'slug' => '',
        'inhoud' => '<p>Inhoud van de <strong>testpagina</strong></p><p><img src="x.jpg" alt=""></p>', 'meta_omschrijving' => 'Test',
        'og_afbeelding' => 'assets/uploads/ci-deelafbeelding.jpg', 'actief' => '1', 'volgorde' => '0',
    ]);
    assert_status(302, $r, 'pagina opslaan');

    try {
        $list = http_get('admin/paginas.php');
        assert_contains($title, $list['body']);
        $savedEditorUrl = http_header($r, 'Location');
        assert_matches('/^pagina_form\.php\?id=\d+$/', $savedEditorUrl, 'opslaan keert terug naar de pagina-editor');
        $editorUrl = 'admin/' . $savedEditorUrl;
        $editor = http_get($editorUrl);
        assert_page_editor_shell($editor, '../' . $slug);
        assert_status(400, http_get($editorUrl . '&tab=hero'), 'paginarecord krijgt geen settings-tabblad');
        $denied = http_post($editorUrl, ['csrf_token' => http_csrf_token($editor), 'tab' => 'hero', 'titel' => 'Mag niet veranderen']);
        assert_contains('Ongeldig tabblad', $denied['body']);
        $denied = http_post($editorUrl, ['csrf_token' => http_csrf_token($editor), 'titel' => ['ongeldige waarde']]);
        assert_contains('Ongeldige veldwaarde', $denied['body']);
        assert_not_contains('Mag niet veranderen', http_get($editorUrl)['body']);
        $r = http_get($slug);
        assert_status(200, $r, '/' . $slug);
        assert_contains('<h1>' . $title . '</h1>', $r['body']);
        assert_contains('<p>Inhoud van de <strong>testpagina</strong></p>', $r['body']);
        assert_contains('<img loading="lazy" src="x.jpg" alt="">', $r['body'], 'afbeeldingen in de inhoud laden lazy');
        assert_matches('#<meta property="og:image" content="https?://[^"]+/assets/uploads/ci-deelafbeelding\.jpg">#', $r['body'], 'eigen deelafbeelding');
        assert_no_php_errors($r, '/' . $slug);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $m);
        $crumbs = array_values(array_filter(array_map(fn($json) => json_decode($json, true), $m[1]),
            fn($data) => ($data['@type'] ?? '') === 'BreadcrumbList'));
        assert_same(1, count($crumbs), 'één kruimelpad');
        $items = $crumbs[0]['itemListElement'];
        assert_same([1, 2], array_column($items, 'position'));
        assert_same($title, $items[1]['name']);
        assert_matches('#/' . preg_quote($slug, '#') . '$#', $items[1]['item']);

        $r = http_get('sitemap.xml');
        assert_contains('/' . $slug . '</loc>', $r['body'], 'pagina staat in de sitemap');

        $editor = http_get($editorUrl);
        assert_status(302, http_post($editorUrl, [
            'csrf_token' => http_csrf_token($editor), 'titel' => $title, 'slug' => $slug,
            'inhoud' => '<p>Testinhoud</p>', 'meta_omschrijving' => '', 'og_afbeelding' => '', 'volgorde' => 0,
        ]), 'pagina depubliceren');
        assert_page_editor_shell(http_get($editorUrl), null);
    } finally {
        $r = http_get('admin/paginas.php');
        $pos = strpos($r['body'], 'aria-label="Bewerken: ' . $title . '"');
        if ($pos !== false && preg_match('/name="id" value="(\d+)"><input type="hidden" name="action" value="delete">/', $r['body'], $m, 0, $pos)) {
            $r = http_post('admin/paginas.php', ['csrf_token' => http_csrf_token($r), 'action' => 'delete', 'id' => $m[1]]);
            assert_status(303, $r, 'pagina verwijderen');
        } else {
            fail('Testpagina niet gevonden in admin/paginas.php');
        }
    }
    assert_status(404, http_get($slug), 'verwijderde pagina');
});

test('speltak met logo en inhoud aanmaken, eigen pagina bekijken en verwijderen', function () {
    admin_login();
    $name = 'CI-speltak ' . bin2hex(random_bytes(3));
    $slug = slugify($name);

    $r = http_get('admin/speltak_form.php');
    assert_contains('enctype="multipart/form-data"', $r['body']);
    assert_contains('name="upload_logo"', $r['body']);
    $r = http_post('admin/speltak_form.php', [
        'csrf_token' => http_csrf_token($r), 'naam' => $name, 'kleur' => '#123456',
        'leeftijd' => '9–12 jaar', 'dag_tijd' => 'Woensdag 18:30 – 20:00 uur', 'leiding' => 'leiding: Test',
        'logo' => 'assets/uploads/ci-logo.png', 'inhoud' => '<p>Over de <strong>CI-speltak</strong></p>',
        'meta_omschrijving' => 'CI-omschrijving van de speltak', 'toelichting' => '<p>Nog geen programma</p>',
        'feed_url' => '', 'afmeld_url' => '', 'actief' => '1', 'volgorde' => '0',
    ]);
    assert_status(302, $r, 'speltak opslaan');

    try {
        $list = http_get('admin/speltakken.php');
        $position = strpos($list['body'], '<strong>' . $name . '</strong>');
        preg_match('/href="(speltak_form.php\?id=\d+)"/', $list['body'], $editorMatch, 0, $position);
        assert_page_editor_shell(http_get('admin/' . $editorMatch[1]), '../speltakken/' . $slug, 'speltakken.php');
        $r = http_get('speltakken/' . $slug);
        assert_status(200, $r, '/speltakken/' . $slug);
        assert_no_php_errors($r, '/speltakken/' . $slug);
        assert_contains('<h1>' . $name . '</h1>', $r['body']);
        assert_contains('<img src="assets/uploads/ci-logo.png" alt="Logo ' . $name . '"', $r['body'], 'logo');
        assert_contains('<dd>9–12 jaar</dd>', $r['body'], 'leeftijd');
        assert_contains('<dd>Woensdag 18:30 – 20:00 uur</dd>', $r['body'], 'tijd');
        assert_contains('<p>Over de <strong>CI-speltak</strong></p>', $r['body'], 'inhoud');
        assert_contains('<p>Nog geen programma</p>', $r['body'], 'toelichting als programma');
        assert_contains('<meta name="description" content="CI-omschrijving van de speltak">', $r['body']);
        assert_contains('<title>' . $name . ' (9–12 jaar) — ', $r['body']);

        $r = http_get('speltakken');
        assert_contains('href="speltakken/' . $slug . '"', $r['body'], 'op het overzicht');
    } finally {
        $r = http_get('admin/speltakken.php');
        $pos = strpos($r['body'], '<strong>' . $name . '</strong>');
        if ($pos !== false && preg_match('/name="action" value="delete">\s*<input type="hidden" name="id" value="(\d+)"/', $r['body'], $m, 0, $pos)) {
            $r = http_post('admin/speltakken.php', ['csrf_token' => http_csrf_token($r), 'action' => 'delete', 'id' => $m[1]]);
            assert_status(302, $r, 'speltak verwijderen');
        } else {
            fail('Test-speltak niet gevonden in admin/speltakken.php');
        }
    }
    assert_status(404, http_get('speltakken/' . $slug), 'verwijderde speltak');
});

/** Leest uitsluitend de settingsvelden uit het actieve formulier. */
function settings_form_values(array $response): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $response['body']);
    $xpath = new DOMXPath($dom);
    $values = [];
    foreach ($xpath->query('//input[starts-with(@id, "f_")] | //textarea[starts-with(@id, "f_")]') as $field) {
        if ($field->getAttribute('type') === 'checkbox' && !$field->hasAttribute('checked')) {
            continue;
        }
        $values[$field->getAttribute('name')] = $field->nodeName === 'textarea'
            ? $field->textContent : ($field->getAttribute('type') === 'checkbox' ? '1' : $field->getAttribute('value'));
    }
    return $values;
}

test('één pagina-editor, vijf systeemtabbladen en oude bookmarks', function () {
    admin_login();
    require_once TEST_ROOT . '/admin/includes/page_definitions.php';
    require_once TEST_ROOT . '/admin/includes/settings_schema.php';
    $r = http_get('admin/paginas.php');
    $button = strpos($r['body'], '+ Nieuwe pagina');
    assert_not_contains("<h2>Vaste pagina's</h2>", $r['body']);
    assert_not_contains("<h2>Zelf aangemaakte pagina's</h2>", $r['body']);
    assert_same(1, substr_count($r['body'], '<table'));
    $dom = new DOMDocument();
    @$dom->loadHTML($r['body']);
    $xpath = new DOMXPath($dom);
    foreach (['Home', 'Speltakken'] as $title) {
        $rows = $xpath->query('//tr[td/strong[text()="' . $title . '"]]');
        assert_same(1, $rows->length);
        assert_same(0, $xpath->query('.//form', $rows->item(0))->length);
        assert_same(1, $xpath->query('.//i[contains(@class, "fa-lock")]', $rows->item(0))->length);
    }
    $previous = 0;
    foreach (page_overview_entries([]) as $page) {
        $key = $page['key'];
        $position = strpos($r['body'], e(page_editor_url($key)) . '"');
        assert_true($position !== false && $position > $previous && $position > $button);
        $previous = $position;
        foreach ($page['tabs'] as $tab => $label) {
            $editor = http_get('admin/' . page_editor_url($key, $tab));
            assert_status(200, $editor, 'paginabeheer');
            assert_no_php_errors($editor, 'paginabeheer');
            assert_contains('Terug naar paginaoverzicht', $editor['body']);
            assert_page_editor_shell($editor, $page['public_url']);
        }
    }
    $r = http_get('admin/settings.php');
    preg_match_all('/href="settings.php\?tab=([^"]+)"/', $r['body'], $matches);
    assert_same(['algemeen', 'analytics', 'organisatie', 'footer', 'header'], $matches[1]);
    foreach (['hero', 'kennismaken', 'programma', 'lidworden', 'verhuur', 'contact'] as $tab) {
        $r = http_get('admin/settings.php?tab=' . $tab);
        assert_status(303, $r, 'paginabeheer');
        assert_same(legacy_settings_url($tab), http_header($r, 'Location'));
    }
    foreach (['programma', 'lidworden', 'verhuur', 'contact'] as $section) {
        assert_not_contains('pagina=' . $section . '"', http_get('admin/paginas.php')['body']);
        foreach (['', '&tab=' . $section] as $query) {
            $redirect = http_get('admin/vaste_pagina.php?pagina=' . $section . $query);
            assert_status(303, $redirect, 'oude sectie-editor');
            assert_same(page_editor_url('home', $section), http_header($redirect, 'Location'));
        }
    }
    foreach (['home', 'speltakken'] as $key) {
        $redirect = http_get('admin/vaste_pagina.php?pagina=' . $key);
        assert_status(303, $redirect, 'oud editoradres');
        assert_same(page_editor_url($key), http_header($redirect, 'Location'));
    }
    $redirect = http_get('admin/vaste_pagina.php?pagina=home&tab=info_cards&actie=bewerken&id=123');
    assert_status(303, $redirect, 'oude kaartbewerker');
    assert_same(page_editor_url('home', 'info_cards') . '&actie=bewerken&id=123', http_header($redirect, 'Location'));
    foreach (['info_cards.php' => '', 'info_card_form.php' => '&actie=nieuw', 'info_card_form.php?id=123' => '&actie=bewerken&id=123'] as $old => $suffix) {
        $r = http_get('admin/' . $old);
        assert_status(303, $r, 'paginabeheer');
        assert_same(page_editor_url('home', 'info_cards') . $suffix, http_header($r, 'Location'));
    }
    assert_status(404, http_get('admin/pagina_form.php?pagina=onbekend'), 'paginabeheer');
    assert_status(404, http_get('admin/pagina_form.php?pagina[]=home'), 'ongeldige paginaselectie');
    assert_status(400, http_get('admin/pagina_form.php?id[]=1'), 'ongeldig pagina-ID');
    assert_status(400, http_get('admin/pagina_form.php?pagina=home&tab=inhoud'), 'Home krijgt geen paginarecordvelden');
    assert_status(400, http_get('admin/pagina_form.php?pagina=home&tab=organisatie'), 'paginabeheer');
    assert_status(400, http_get('admin/pagina_form.php?pagina=home&tab[]=hero'), 'paginabeheer');
});

test('iedere settingsgroep bewaart eigen velden en weigert een ander tabblad', function () {
    admin_login();
    require_once TEST_ROOT . '/admin/includes/page_definitions.php';
    require_once TEST_ROOT . '/admin/includes/settings_schema.php';
    $urls = [];
    foreach (system_settings_schema() as $tab => $group) {
        $urls[$tab] = 'settings.php?tab=' . $tab;
    }
    foreach (page_overview_entries([]) as $page) {
        $key = $page['key'];
        foreach ($page['tabs'] as $tab => $config) {
            if ($config['handler'] === 'settings') {
                $urls[$tab] = page_editor_url($key, $tab);
            }
        }
    }
    foreach ($urls as $tab => $url) {
        $r = http_get('admin/' . $url);
        $values = settings_form_values($r);
        assert_true(count($values) > 0, $tab);
        $post = $values + ['csrf_token' => http_csrf_token($r), 'tab' => $tab];
        // Velden van een andere groep mogen nooit worden opgeslagen.
        $foreign = $tab === 'hero' ? 'contact_intro' : 'hero_title';
        $post[$foreign] = 'ONBEVOEGD VELD';
        $saved = http_post('admin/' . $url, $post);
        assert_status(302, $saved, $tab);
        assert_same($url, http_header($saved, 'Location'));
        $r = http_get('admin/' . $url);
        assert_same($values, settings_form_values($r), $tab . ' behoudt alle veldwaarden');
        $post['tab'] = 'onbekend';
        $denied = http_post('admin/' . $url, $post);
        assert_contains('Ongeldig tabblad', $denied['body']);
        $post['tab'] = $tab;
        $post['csrf_token'] = 'ongeldig';
        assert_contains('Ongeldige aanvraag', http_post('admin/' . $url, $post)['body']);
    }
    foreach (['home&tab=hero', 'home&tab=contact'] as $query) {
        assert_not_contains('ONBEVOEGD VELD', http_get('admin/pagina_form.php?pagina=' . $query)['body']);
    }
});

test('Home info-vakjes: toevoegen, bewerken, sectie behouden, sorteren en verwijderen', function () {
    admin_login();
    $url = 'admin/pagina_form.php?pagina=home&tab=info_cards';
    $title = 'CI-kaart ' . bin2hex(random_bytes(4));
    $r = http_get($url . '&actie=nieuw');
    assert_contains('class="rich-text"', $r['body']);
    assert_contains('name="upload_afbeelding"', $r['body']);
    assert_contains('class="file-picker"', $r['body']);
    preg_match('/<option value="(\d+)"/', $r['body'], $documentMatch);
    assert_true(isset($documentMatch[1]), 'bestaand PDF-document beschikbaar');
    $post = ['csrf_token' => http_csrf_token($r), 'titel' => $title, 'sectie' => 'ci_andere_sectie',
        'tekst' => '<p>Kaartinhoud</p>', 'afbeelding' => 'assets/uploads/test.jpg', 'document_id' => $documentMatch[1],
        'knop_tekst' => 'Download', 'anchor' => 'ci-kaart', 'volgorde' => '0'];
    assert_status(302, http_post($url . '&actie=nieuw', $post), 'paginabeheer');
    $r = http_get($url);
    $position = strpos($r['body'], '<strong>' . $title . '</strong>');
    assert_true($position !== false);
    preg_match('/actie=bewerken&amp;id=(\d+)/', $r['body'], $match, 0, $position);
    $id = $match[1];
    try {
        $edit = $url . '&actie=bewerken&id=' . $id;
        $r = http_get($edit);
        assert_contains('value="ci_andere_sectie"', $r['body']);
        assert_contains('value="assets/uploads/test.jpg"', $r['body']);
        assert_contains('value="' . $documentMatch[1] . '" selected', $r['body']);
        assert_status(303, http_get('admin/info_card_form.php?id=' . $id), 'paginabeheer');
        $post['titel'] = $title . ' gewijzigd';
        $post['csrf_token'] = http_csrf_token($r);
        assert_status(302, http_post($edit, $post), 'paginabeheer');
        assert_contains($title . ' gewijzigd', http_get($url)['body']);
        $uploaded = null;
        try {
            $upload = http_request('POST', $edit, $post, [], ['upload_afbeelding' => [
                'name' => 'test.png', 'type' => 'image/png',
                'content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZuoAAAAASUVORK5CYII='),
            ]]);
            assert_status(302, $upload, 'afbeelding uploaden');
            $r = http_get($edit);
            preg_match('/id="afbeelding"[^>]+value="([^"]+)"/', $r['body'], $imageMatch);
            $uploaded = basename($imageMatch[1]);
            assert_matches('/^[a-f0-9]+\.png$/', $uploaded);
            assert_contains(e($imageMatch[1]), $r['body'], 'bestaand bestand selecteerbaar');
            // Koppel de upload los voordat de bestandsbeheerder hem verwijdert.
            assert_status(302, http_post($edit, $post), 'afbeelding kiezen');
        } finally {
            if ($uploaded) {
                $files = http_get('admin/uploads.php');
                assert_status(302, http_post('admin/uploads.php', ['csrf_token' => http_csrf_token($files),
                    'action' => 'delete', 'filename' => $uploaded]), 'testupload opruimen');
            }
        }
        foreach (['up', 'down'] as $direction) {
            assert_status(302, http_post($url, ['csrf_token' => $post['csrf_token'], 'action' => 'move', 'id' => $id, 'dir' => $direction]), 'paginabeheer');
        }
        assert_contains('Ongeldige aanvraag', http_post($url, ['csrf_token' => 'fout', 'action' => 'delete', 'id' => $id])['body']);
        assert_contains($title, http_get($url)['body']);
    } finally {
        $r = http_get($url);
        assert_status(302, http_post($url, ['csrf_token' => http_csrf_token($r), 'action' => 'delete', 'id' => $id]), 'paginabeheer');
    }
    assert_not_contains($title, http_get($url)['body']);
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

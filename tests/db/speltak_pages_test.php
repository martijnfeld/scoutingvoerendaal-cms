<?php
/**
 * Eigen pagina per speltak en het speltakkenoverzicht: URL's, de
 * automatische meta-omschrijving, gereserveerde slug en migratie 0007.
 * Eigen bestand (eigen proces), omdat get_all_settings() per request
 * cachet: de instellingen moeten gezet zijn vóór de eerste get_setting().
 */
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/includes/updater.php';

test('speltak-URL\'s: relatief en absoluut op basis van site_url', function () {
    // Vóór de eerste get_setting(): get_all_settings() cachet per request.
    set_setting('site_url', 'https://www.example.org/');
    set_setting('org_naam', 'Scouting Dorp');

    assert_same('speltakken', speltakken_url());
    assert_same('https://www.example.org/speltakken', speltakken_url(true));
    assert_same('speltakken/welpen', speltak_url('welpen'));
    assert_same('https://www.example.org/speltakken/welpen', speltak_url('welpen', true));
});

test('get_speltak_by_slug()', function () {
    $sp = get_speltak(1);
    assert_same((int) $sp['id'], (int) get_speltak_by_slug($sp['slug'])['id']);
    assert_same(null, get_speltak_by_slug('bestaat-niet'));
});

test('speltak_meta_description(): ingevuld, anders samengesteld', function () {
    assert_same('Eigen tekst', speltak_meta_description(['naam' => 'Welpen', 'meta_omschrijving' => '  Eigen tekst ']));
    assert_same(
        'Welpen bij Scouting Dorp, voor jeugd van 7–11 jaar. Opkomst: Zaterdag 10:00 uur. Lees meer over de speltak en bekijk het actuele programma.',
        speltak_meta_description(['naam' => 'Welpen', 'leeftijd' => '7–11 jaar', 'dag_tijd' => 'Zaterdag 10:00 uur', 'meta_omschrijving' => null])
    );
    assert_same(
        'Stam bij Scouting Dorp. Lees meer over de speltak en bekijk het actuele programma.',
        speltak_meta_description(['naam' => 'Stam', 'leeftijd' => null, 'dag_tijd' => '', 'meta_omschrijving' => ''])
    );
});

test('"speltakken" is gereserveerd voor losse pagina\'s', function () {
    assert_same('speltakken-2', unique_page_slug('Speltakken'));
});

test('breadcrumbs_html(): laatste item is de huidige pagina, alles ge-escaped', function () {
    $html = breadcrumbs_html([['Home', './'], ['Speltakken', 'speltakken'], ['<Welpen>', 'speltakken/welpen']]);
    assert_contains('<nav class="breadcrumbs" aria-label="Kruimelpad">', $html);
    assert_contains('<li class="breadcrumb-item"><a href="speltakken">Speltakken</a></li>', $html);
    assert_contains('<li class="breadcrumb-item active" aria-current="page">&lt;Welpen&gt;</li>', $html);
    assert_not_contains('speltakken/welpen', $html, 'huidige pagina is geen link');
});

test('find_upload_usage() ziet logo en inhoud van een speltak', function () {
    db()->prepare('UPDATE speltakken SET logo = :l, inhoud = :i WHERE id = 1')->execute([
        'l' => rtrim(UPLOAD_URL, '/') . '/aaaabbbbccccdddd.png',
        'i' => '<p><img src="https://www.example.org/assets/uploads/1111222233334444.jpg"></p>',
    ]);
    $naam = get_speltak(1)['naam'];
    assert_true(in_array('Speltak: ' . $naam, find_upload_usage('aaaabbbbccccdddd.png'), true), 'logo');
    assert_true(in_array('Speltak: ' . $naam, find_upload_usage('1111222233334444.jpg'), true), 'inhoud');
});

test('install.sql en migratie 0007 geven dezelfde speltakken-tabel, instelling en slugs', function () {
    $columns = fn() => db()->query('SHOW COLUMNS FROM speltakken')->fetchAll();
    $fromInstall = $columns();
    $intro = function () {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'speltakken_intro'");
        $stmt->execute();
        return $stmt->fetchColumn();
    };
    $introFromInstall = $intro();
    assert_true($introFromInstall !== false && $introFromInstall !== '', 'instelling na install.sql');

    db()->exec('ALTER TABLE speltakken DROP COLUMN logo, DROP COLUMN inhoud, DROP COLUMN meta_omschrijving, DROP COLUMN bijgewerkt');
    db()->exec("DELETE FROM settings WHERE setting_key = 'speltakken_intro'");
    db()->exec("INSERT INTO pages (titel, slug, inhoud) VALUES ('Oud overzicht', 'speltakken', '')");
    $pageId = (int) db()->lastInsertId();

    $migration = TEST_ROOT . '/sql/migrations/0007_speltak_pages.php';
    (function () use ($migration) { require $migration; })();
    assert_same($fromInstall, $columns(), 'kolommen na migratie 0007');
    assert_same($introFromInstall, $intro(), 'instelling na migratie 0007');
    $stmt = db()->prepare('SELECT slug FROM pages WHERE id = :id');
    $stmt->execute(['id' => $pageId]);
    assert_same('speltakken-pagina-' . $pageId, $stmt->fetchColumn(), 'botsende pagina krijgt een andere slug');

    // Nogmaals draaien (half toegepaste migratie) mag geen fout geven en
    // overschrijft een aangepaste introtekst niet.
    set_setting('speltakken_intro', 'Eigen intro');
    (function () use ($migration) { require $migration; })();
    assert_same($fromInstall, $columns(), 'kolommen na tweede keer');
    assert_same('Eigen intro', $intro(), 'migratie overschrijft niets');
});

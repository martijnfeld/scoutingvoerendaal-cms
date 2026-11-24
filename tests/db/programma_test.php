<?php
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/includes/header_menu.php';

test('programma-intromigratie vervangt de standaardtekst en behoudt eigen teksten', function () {
    $update = db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key='programma_intro'");
    $read = function () {
        return db()->query("SELECT setting_value FROM settings WHERE setting_key='programma_intro'")->fetchColumn();
    };
    $migration = file_get_contents(TEST_ROOT . '/sql/migrations/0012_programma_intro.sql');
    $update->execute(['Het actuele programma per speltak, rechtstreeks opgehaald uit onze planning.']);
    test_db_import($migration);
    assert_same('Bekijk het actuele programma per speltak.', $read());
    test_db_import($migration);
    assert_same('Bekijk het actuele programma per speltak.', $read());
    $update->execute(['Onze eigen introductie.']);
    test_db_import($migration);
    assert_same('Onze eigen introductie.', $read());
    $update->execute(['Bekijk het actuele programma per speltak.']);
});

test('programmamigratie behoudt menukeuzes en kan herhaald worden', function () {
    db()->exec("UPDATE header_menu_items SET link_type='section', link_value='programma', label='Onze opkomsten', actief=0, volgorde=42 WHERE seed_key='programma'");
    $before = header_menu_rows();
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0011_programma_page.sql'));
    $expected = $before;
    foreach ($expected as &$row) {
        if ($row['link_type'] === 'section' && $row['link_value'] === 'programma') {
            $row['link_type'] = 'programma';
            $row['link_value'] = '';
        }
    }
    unset($row);
    assert_same($expected, header_menu_rows());
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0011_programma_page.sql'));
    assert_same($expected, header_menu_rows());
});

test('programmalijst toont actieve speltakken met feeds, toelichting en lege toestand', function () {
    $rows = get_speltakken(true);
    db()->prepare('UPDATE speltakken SET actief=0 WHERE id=?')->execute([$rows[0]['id']]);
    db()->prepare("UPDATE speltakken SET feed_url='https://example.org/feed', afmeld_url='https://example.org/afmelden' WHERE id=?")->execute([$rows[1]['id']]);
    db()->prepare("UPDATE speltakken SET feed_url='', toelichting='<p>Eigen programma</p>' WHERE id=?")->execute([$rows[2]['id']]);
    db()->prepare("UPDATE speltakken SET feed_url='', toelichting='' WHERE id=?")->execute([$rows[3]['id']]);
    $speltakken = get_speltakken(true);
    $isHome = false;
    ob_start();
    include TEST_ROOT . '/includes/programma_list.php';
    $html = ob_get_clean();
    assert_not_contains('id="sk-' . $rows[0]['slug'] . '"', $html);
    assert_contains('data-feed-slug="' . $rows[1]['slug'] . '"', $html);
    assert_contains('href="https://example.org/afmelden"', $html);
    assert_contains('<p>Eigen programma</p>', $html);
    assert_contains('Er is op dit moment geen programma beschikbaar.', $html);
    assert_same(count($speltakken), substr_count($html, '<section class="speltak-block'));
    assert_same(count($speltakken), substr_count($html, '<h2>'));
    assert_contains('href="programma#sk-' . $rows[1]['slug'] . '"', $html);
    db()->exec("UPDATE header_menu_items SET actief=1 WHERE seed_key='programma'");
    $tree = header_menu_tree('./', '', 'programma');
    $programme = array_values(array_filter($tree, function ($row) { return $row['link_type'] === 'programma'; }))[0];
    assert_true($programme['active']);
    assert_same(array_column($speltakken, 'naam'), array_column($programme['children'], 'label'));
});

test('linkmigratie 0013 zet oude #programma-ankers om en behoudt overige inhoud', function () {
    $setSetting = db()->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
    $setting = function (string $key) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    };
    $setSetting->execute(['#programma', 'hero_btn2_link']);
    $setSetting->execute(['#sk-welpen', 'hero_btn1_link']);
    $setSetting->execute(['#lidworden', 'declaratie_link']);
    $setSetting->execute(['Zie <a href="#programma">programma</a> en <a href="./#programma">hier</a> of <a href="#contact">contact</a>.', 'feature3_text']);
    db()->exec("UPDATE info_cards SET tekst='<p><a href=\"#programma\">Programma</a></p>' WHERE id=1");
    db()->exec("INSERT INTO pages (titel, slug, inhoud, actief) VALUES ('Info', 'info-0013', '<a href=\"#programma\">x</a>', 1)");
    db()->exec("UPDATE header_menu_items SET link_type='url', link_value='/#programma' WHERE seed_key='contact'");

    $run = function () { require TEST_ROOT . '/sql/migrations/0013_programma_links.php'; };
    $run();
    $run();

    assert_same('programma', $setting('hero_btn2_link'));
    assert_same('programma#sk-welpen', $setting('hero_btn1_link'));
    assert_same('#lidworden', $setting('declaratie_link'));
    assert_same('Zie <a href="programma">programma</a> en <a href="programma">hier</a> of <a href="#contact">contact</a>.', $setting('feature3_text'));
    assert_same('<p><a href="programma">Programma</a></p>', db()->query('SELECT tekst FROM info_cards WHERE id=1')->fetchColumn());
    assert_same('<a href="programma">x</a>', db()->query("SELECT inhoud FROM pages WHERE slug='info-0013'")->fetchColumn());
    assert_same(['programma', ''], array_values(db()->query("SELECT link_type, link_value FROM header_menu_items WHERE seed_key='contact'")->fetch(PDO::FETCH_NUM)));
});

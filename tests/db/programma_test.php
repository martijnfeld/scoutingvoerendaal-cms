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

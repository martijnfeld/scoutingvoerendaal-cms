<?php
/** Het paginamodel leest de publicatiestatus uit de testdatabase. */
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/admin/includes/page_definitions.php';

test('overzicht en editor delen hetzelfde paginamodel en dezelfde mogelijkheden', function () {
    $record = ['id' => 23, 'titel' => 'Leden', 'slug' => 'leden', 'in_menu' => 0, 'actief' => 0, 'volgorde' => 4];
    $entries = page_overview_entries([$record]);
    assert_same(['Home', 'Programma', 'Speltakken', 'Leden'], array_column($entries, 'titel'));
    assert_same([false, false, false, true], array_column($entries, 'can_delete'));
    assert_same([false, false, true, true], array_column($entries, 'can_toggle'));
    assert_same([null, null, null, 23], array_column($entries, 'id'));
    assert_same(['../', '../programma', '../speltakken', '../leden'], array_column($entries, 'public_url'));
    assert_same(page_editor_definition('content', $record), $entries[3]);
    foreach ($entries as $entry) {
        assert_contains('pagina_form.php', $entry['edit_url']);
        $tab = array_key_first($entry['tabs']);
        $navigation = page_editor_navigation($entry, $tab);
        assert_same($entry['actief'] ? $entry['public_url'] : null, $navigation['public_url']);
        assert_same($tab, $navigation['active_tab']);
    }
    $new = page_editor_definition('content');
    assert_same(null, page_editor_navigation($new, 'inhoud')['public_url']);
    assert_same('pagina_form.php', $new['edit_url']);
    // Ook een record met gelijknamige velden kan de serverconfiguratie niet wijzigen.
    $record['tabs'] = ['organisatie' => ['handler' => 'settings']];
    $record['can_delete'] = false;
    $definition = page_editor_definition('content', $record);
    assert_same(['inhoud'], array_keys($definition['tabs']));
    assert_true($definition['can_delete']);
});


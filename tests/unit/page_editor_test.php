<?php
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/admin/includes/settings_schema.php';
require_once TEST_ROOT . '/admin/includes/page_definitions.php';
require_once TEST_ROOT . '/admin/includes/settings_editor.php';

test('paginaschema verdeelt instellingen uniek en selecteert alleen bekende opslaghandlers', function () {
    assert_same(['algemeen', 'analytics', 'organisatie', 'footer'], array_keys(system_settings_schema()));
    $definitions = page_editor_definitions();
    assert_same(['home', 'speltakken', 'content'], array_keys($definitions));
    assert_same(['hero', 'kennismaken', 'programma', 'lidworden', 'info_cards', 'verhuur', 'contact'], array_keys($definitions['home']['tabs']));
    $schema = settings_schema();
    $groups = array_keys(system_settings_schema());
    foreach ($definitions as $definition) {
        foreach ($definition['tabs'] as $tab => $config) {
            assert_true(in_array($config['handler'], ['settings', 'page_record', 'info_cards'], true));
            if ($config['handler'] === 'settings') {
                assert_true(isset($schema[$tab]));
                $groups[] = $tab;
            }
        }
    }
    $allGroups = array_keys($schema);
    sort($groups);
    sort($allGroups);
    assert_same($allGroups, $groups, 'iedere groep precies één keer bereikbaar');
    $keys = [];
    foreach ($schema as $group) {
        $keys = array_merge($keys, array_keys($group['fields']));
    }
    assert_same(count($keys), count(array_unique($keys)), 'ieder veld heeft één eigenaar');
    assert_same(['programma_intro', 'programma_footer_note'], array_keys($schema['programma']['fields']));
    assert_same(['speltakken_intro'], array_keys($schema['speltakken']['fields']));
});

test('overzicht en editor delen hetzelfde paginamodel en dezelfde mogelijkheden', function () {
    $record = ['id' => 23, 'titel' => 'Leden', 'slug' => 'leden', 'in_menu' => 0, 'actief' => 0, 'volgorde' => 4];
    $entries = page_overview_entries([$record]);
    assert_same(['Home', 'Speltakken', 'Leden'], array_column($entries, 'titel'));
    assert_same([false, false, true], array_column($entries, 'can_delete'));
    assert_same([false, true, true], array_column($entries, 'can_toggle'));
    assert_same([null, null, 23], array_column($entries, 'id'));
    assert_same(['../', '../speltakken', '../leden'], array_column($entries, 'public_url'));
    assert_same(page_editor_definition('content', $record), $entries[2]);
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

test('oude instellingen en sectie-editors verwijzen naar de centrale editor', function () {
    foreach (['hero', 'kennismaken', 'programma', 'lidworden', 'verhuur', 'contact'] as $tab) {
        assert_same(page_editor_url('home', $tab), legacy_settings_url($tab));
    }
    assert_same(null, legacy_settings_url('algemeen'));
    assert_same(null, legacy_settings_url('inhoud'));
    assert_same(null, legacy_settings_url('../onbekend'));
    foreach (['programma', 'lidworden', 'verhuur', 'contact'] as $section) {
        assert_same(page_editor_url('home', $section), legacy_page_editor_url($section));
        assert_same(page_editor_url('home', $section), legacy_page_editor_url($section, $section));
        assert_same(null, legacy_page_editor_url($section, 'organisatie'));
        assert_same(null, legacy_page_editor_url($section, ['hero']));
    }
    assert_same(page_editor_url('home'), legacy_page_editor_url('home'));
    assert_same(page_editor_url('speltakken'), legacy_page_editor_url('speltakken'));
    assert_same(null, legacy_page_editor_url('content'));
    assert_same(null, legacy_page_editor_url('onbekend'));
});

test('opslag weigert CSRF en afwijkend POST-tabblad vóór databasegebruik', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token' => 'ongeldig', 'tab' => 'hero'];
    assert_contains('Ongeldige aanvraag', save_settings_group(settings_schema()['hero'], 'hero', ''));
    $_POST['csrf_token'] = csrf_token();
    $_POST['tab'] = 'organisatie';
    assert_contains('Ongeldig tabblad', save_settings_group(settings_schema()['hero'], 'hero', ''));
});

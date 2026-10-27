<?php
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/includes/header_menu.php';

function run_header_migration(): void
{
    require TEST_ROOT . '/sql/migrations/0009_header_menu.php';
}
function menu_input(array $extra = []): array
{
    return array_merge(['label' => 'Testmenu', 'link_type' => 'section', 'link_value' => 'contact',
        'page_id' => '', 'parent_id' => '', 'dropdown_type' => 'none', 'volgorde' => '20', 'actief' => '1'], $extra);
}
function menu_rejected(callable $fn): void
{
    try { $fn(); } catch (RuntimeException $exception) { return; }
    fail('Ongeldige configuratie werd geaccepteerd.');
}
function menu_by_label(string $label): array
{
    foreach (header_menu_rows() as $row) { if ($row['label'] === $label) { return $row; } }
    fail('Menu-item niet gevonden: ' . $label);
}

test('verse installatie heeft zes hoofditems en actuele automatische dropdowns', function () {
    $tree = header_menu_tree('');
    assert_same(['Home', 'Programma', 'Speltakken', 'Lid worden', 'Verhuur', 'Contact'], array_column($tree, 'label'));
    assert_same(['#top', '#programma', 'speltakken', '#lidworden', '#verhuur', '#contact'], array_column($tree, 'url'));
    assert_same(array_column(get_speltakken(true), 'naam'), array_column($tree[1]['children'], 'label'));
    assert_same(array_map(function ($sp) { return '#sk-' . $sp['slug']; }, get_speltakken(true)), array_column($tree[1]['children'], 'url'));
    assert_same(array_map(function ($sp) { return speltak_url($sp['slug']); }, get_speltakken(true)), array_column($tree[2]['children'], 'url'));
    assert_same(6, count(header_menu_rows()));
});

test('speltakken deactiveren, heractiveren, hernoemen en sorteren werken direct', function () {
    $sp = get_speltakken(true)[0];
    db()->prepare('UPDATE speltakken SET actief=0 WHERE id=?')->execute([$sp['id']]);
    assert_false(in_array($sp['naam'], array_column(header_menu_tree('')[1]['children'], 'label'), true));
    db()->prepare("UPDATE speltakken SET actief=1, naam='Nieuwe naam', slug='nieuwe-slug', volgorde=-10 WHERE id=?")->execute([$sp['id']]);
    $tree = header_menu_tree('./', '', 'speltakken');
    assert_same('Nieuwe naam', $tree[1]['children'][0]['label']);
    assert_same('./#sk-nieuwe-slug', $tree[1]['children'][0]['url']);
    assert_same('speltakken/nieuwe-slug', $tree[2]['children'][0]['url']);
    assert_true($tree[2]['active']);
});

test('ontbrekende tabel geeft uitsluitend lezende legacy-fallback', function () {
    db()->exec('DROP TABLE header_menu_items');
    db()->exec("INSERT INTO pages (titel,slug,in_menu,actief,volgorde) VALUES ('Oude pagina','oud',1,1,8),('Verborgen','verborgen',1,0,2)");
    assert_same(null, header_menu_rows());
    assert_same(['Home', 'Programma', 'Speltakken', 'Lid worden', 'Verhuur', 'Contact', 'Oude pagina'], array_column(header_menu_tree('./'), 'label'));
    assert_same(null, header_menu_rows(), 'fallback maakt geen tabel aan');
});

test('dataoverdracht rolt bij een fout volledig terug en kan opnieuw starten', function () {
    // Eerst alleen DDL: dit simuleert een afgebroken upload na tabelaanmaak.
    $migration = file_get_contents(TEST_ROOT . '/sql/migrations/0009_header_menu.php');
    preg_match("/db\(\)->exec\(<<<'SQL'\n(.*?)\nSQL/s", $migration, $match);
    db()->exec($match[1]);
    db()->exec("DELETE FROM settings WHERE setting_key='header_menu_initialized'");
    db()->exec("CREATE TRIGGER header_test_fail BEFORE INSERT ON header_menu_items FOR EACH ROW
        BEGIN IF NEW.link_type = 'page' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Testonderbreking'; END IF; END");
    try {
        menu_rejected(function () { run_header_migration(); });
        assert_same(0, (int) db()->query('SELECT COUNT(*) FROM header_menu_items')->fetchColumn(), 'ook de zes standaarditems zijn teruggedraaid');
        assert_same(null, header_menu_rows(), 'onvolledige migratie blijft de legacy-fallback gebruiken');
        assert_same(false, db()->query("SELECT setting_value FROM settings WHERE setting_key='header_menu_initialized'")->fetchColumn());
    } finally {
        db()->exec('DROP TRIGGER header_test_fail');
    }
});

test('productiemigratie respecteert alle legacy-koppelingen en relatieve volgorde', function () {
    db()->exec("DELETE FROM settings WHERE setting_key='header_menu_initialized'");
    db()->exec("INSERT INTO pages (titel,slug,in_menu,actief,volgorde) VALUES ('A','a',1,1,8),('B','b',1,1,8),('Geen menu','geen',0,1,0)");
    run_header_migration();
    $rows = header_menu_rows();
    assert_same(['Home', 'Programma', 'Speltakken', 'Lid worden', 'Verhuur', 'Contact', 'Verborgen', 'A', 'B', 'Oude pagina'], array_column($rows, 'label'));
    assert_same(range(1, 10), array_map('intval', array_column($rows, 'volgorde')));
    assert_same(4, (int) db()->query('SELECT COUNT(*) FROM pages WHERE in_menu=1')->fetchColumn());
    assert_same('verborgen', $rows[6]['page_slug']);
    assert_false(in_array('Verborgen', array_column(header_menu_tree(''), 'label'), true));
    run_header_migration();
    assert_same($rows, header_menu_rows(), 'herhalen verandert geen gegevens');
    db()->exec("UPDATE header_menu_items SET label='Aangepast', volgorde=80 WHERE seed_key='home'");
    db()->exec("DELETE FROM header_menu_items WHERE seed_key='contact'");
    $modified = header_menu_rows();
    run_header_migration();
    assert_same($modified, header_menu_rows(), 'aangepaste en verwijderde items blijven behouden');
});

test('paginaslug en publicatie zijn dynamisch, pagina verwijderen wordt geblokkeerd', function () {
    $page = get_page_by_slug('oud');
    assert_true(header_menu_page_used((int) $page['id']));
    db()->prepare("UPDATE pages SET slug='nieuw', titel='Andere titel' WHERE id=?")->execute([$page['id']]);
    $tree = header_menu_tree('./', 'nieuw');
    $item = array_values(array_filter($tree, function ($row) { return $row['label'] === 'Oude pagina'; }))[0];
    assert_same('nieuw', $item['url']);
    assert_true($item['active']);
    menu_rejected(function () use ($page) { db()->prepare('DELETE FROM pages WHERE id=?')->execute([$page['id']]); });
    db()->prepare('UPDATE pages SET actief=0 WHERE id=?')->execute([$page['id']]);
    assert_false(in_array('Oude pagina', array_column(header_menu_tree(''), 'label'), true));
    assert_true(header_menu_page_used((int) $page['id']));
});

test('een nieuwe gewone pagina wijzigt de header niet', function () {
    $before = header_menu_rows();
    db()->exec("INSERT INTO pages (titel,slug) VALUES ('Nieuwe pagina','nieuw-gewoon')");
    assert_same($before, header_menu_rows());
    $controller = file_get_contents(TEST_ROOT . '/admin/includes/page_record_controller.php');
    assert_not_contains('in_menu', $controller);
});

test('handmatige dropdowns kunnen worden aangemaakt, gewijzigd en gesorteerd', function () {
    header_menu_mutate('save', 0, menu_input(['label' => 'Handmatig', 'dropdown_type' => 'manual']));
    $root = (int) menu_by_label('Handmatig')['id'];
    header_menu_mutate('save', 0, menu_input(['label' => 'Kind A', 'parent_id' => (string) $root]));
    header_menu_mutate('save', 0, menu_input(['label' => 'Kind B', 'parent_id' => (string) $root]));
    $a = (int) menu_by_label('Kind A')['id'];
    $b = (int) menu_by_label('Kind B')['id'];
    header_menu_mutate('up', $b, []);
    $tree = header_menu_tree('');
    $manual = array_values(array_filter($tree, function ($row) { return $row['label'] === 'Handmatig'; }))[0];
    assert_same(['Kind B', 'Kind A'], array_column($manual['children'], 'label'));
    header_menu_mutate('save', $a, menu_input(['label' => 'Gewijzigd', 'parent_id' => (string) $root, 'volgorde' => '1']));
    assert_same('Gewijzigd', menu_by_label('Gewijzigd')['label']);
    header_menu_mutate('toggle', $a, []);
    assert_same(0, (int) menu_by_label('Gewijzigd')['actief']);
    menu_rejected(function () use ($root) { header_menu_mutate('delete', $root, []); });
    menu_rejected(function () use ($root) { header_menu_mutate('save', $root, menu_input(['dropdown_type' => 'none'])); });
    header_menu_mutate('delete', $a, []);
    header_menu_mutate('delete', $b, []);
    header_menu_mutate('delete', $root, []);
    assert_true((int) db()->query("SELECT COUNT(*) FROM audit_log WHERE entiteit='header_menu'")->fetchColumn() >= 8);
});

test('server weigert ongeldige bestemmingen, acties, veldtypen en menuhiërarchieën', function () {
    $rows = header_menu_rows();
    foreach ([['link_type' => 'vreemd'], ['page_id' => '999999', 'link_type' => 'page'], ['link_value' => 'javascript:alert(1)', 'link_type' => 'url'],
        ['parent_id' => '999999'], ['parent_id' => (string) $rows[0]['id']], ['label' => []], ['volgorde' => '-1']] as $input) {
        menu_rejected(function () use ($input) { header_menu_mutate('save', 0, menu_input($input)); });
    }
    header_menu_mutate('save', 0, menu_input(['label' => 'Ouder', 'dropdown_type' => 'manual']));
    $root = (int) menu_by_label('Ouder')['id'];
    menu_rejected(function () use ($root) { header_menu_mutate('save', $root, menu_input(['parent_id' => (string) $root])); });
    header_menu_mutate('save', 0, menu_input(['label' => 'Kind', 'parent_id' => (string) $root]));
    $child = (int) menu_by_label('Kind')['id'];
    menu_rejected(function () use ($child) { header_menu_mutate('save', 0, menu_input(['parent_id' => (string) $child])); });
    menu_rejected(function () use ($root, $child) { header_menu_mutate('save', $root, menu_input(['parent_id' => (string) $child])); });
    menu_rejected(function () { header_menu_mutate('drop', 1, []); });
});

test('herhalen na bewust leeggemaakt menu herstelt geen standaarditems', function () {
    db()->exec('DELETE FROM header_menu_items WHERE parent_id IS NOT NULL');
    db()->exec('DELETE FROM header_menu_items');
    run_header_migration();
    assert_same([], header_menu_rows());
});

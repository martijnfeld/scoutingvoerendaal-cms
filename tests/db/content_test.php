<?php
require_once TEST_ROOT . '/includes/functions.php';

test('set_setting() maakt aan en werkt bij', function () {
    set_setting('test_sleutel', 'eerste');
    set_setting('test_sleutel', 'tweede');
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
    $stmt->execute(['k' => 'test_sleutel']);
    assert_same(['tweede'], $stmt->fetchAll(PDO::FETCH_COLUMN));
});

test('get_setting() met standaardwaarde en {jaar}', function () {
    // Vóór de eerste get_setting(): get_all_settings() cachet per request.
    set_setting('test_jaar', '© {jaar} Scouting');
    assert_same('© ' . date('Y') . ' Scouting', get_setting_with_year('test_jaar'));
    assert_same('tweede', get_setting('test_sleutel'));
    assert_same('standaard', get_setting('bestaat_niet', 'standaard'));
    assert_same('', get_setting('bestaat_niet'));
});

test('get_speltakken() filtert op actief en sorteert op volgorde', function () {
    db()->exec('UPDATE speltakken SET actief = 1');
    db()->exec('UPDATE speltakken SET actief = 0 WHERE id = 1');
    $all = get_speltakken();
    $active = get_speltakken(true);
    assert_same(count($all) - 1, count($active));
    foreach ($active as $sp) {
        assert_same(1, (int) $sp['actief']);
    }
    $orders = array_map('intval', array_column($all, 'volgorde'));
    $sorted = $orders;
    sort($sorted);
    assert_same($sorted, $orders);
    assert_same('1', (string) get_speltak(1)['id']);
    assert_same(null, get_speltak(999999));
});

test('unique_speltak_slug() voorkomt dubbele slugs', function () {
    $existing = get_speltak(1);
    assert_same($existing['slug'] . '-2', unique_speltak_slug($existing['slug']));
    assert_same($existing['slug'], unique_speltak_slug($existing['slug'], 1), 'eigen slug bij bewerken');
    assert_same('gloednieuw', unique_speltak_slug('Gloednieuw'));
    assert_same('speltak', unique_speltak_slug('!!!'));
});

test('unique_page_slug() slaat gereserveerde en bestaande slugs over', function () {
    assert_same('admin-2', unique_page_slug('admin'));
    assert_same('tests-2', unique_page_slug('Tests'));
    db()->exec("INSERT INTO pages (titel, slug, inhoud) VALUES ('Info', 'info', '<p>x</p>'), ('Info 2', 'info-2', '')");
    assert_same('info-3', unique_page_slug('Info'));
    $id = (int) db()->query("SELECT id FROM pages WHERE slug = 'info'")->fetchColumn();
    assert_same('info', unique_page_slug('info', $id));
    assert_same('pagina', unique_page_slug(''));
});

test('pagina-functies filteren op actief en menu', function () {
    db()->exec('DELETE FROM pages');
    db()->exec("INSERT INTO pages (titel, slug, in_menu, actief, volgorde) VALUES
        ('B zichtbaar', 'b', 1, 1, 2), ('A zichtbaar', 'a', 1, 1, 1), ('Niet in menu', 'c', 0, 1, 3), ('Inactief', 'd', 1, 0, 4)");
    assert_same(['a', 'b', 'c', 'd'], array_column(get_pages(), 'slug'));
    assert_same(['a', 'b', 'c'], array_column(get_pages(true), 'slug'));
    assert_same(['a', 'b'], array_column(get_pages_in_menu(), 'slug'));
    assert_same('Inactief', get_page_by_slug('d')['titel']);
    assert_same(null, get_page_by_slug('bestaat-niet'));
    assert_same('A zichtbaar', get_page((int) get_page_by_slug('a')['id'])['titel']);
});

test('documenten en info-vakjes', function () {
    $docs = get_documents();
    assert_true(count($docs) > 0);
    $names = array_column($docs, 'naam');
    $sorted = $names;
    sort($sorted);
    assert_same($sorted, $names, 'op naam gesorteerd');
    assert_same(null, get_document(null));
    assert_same(null, get_document(0));
    assert_same($docs[0]['naam'], get_document((int) $docs[0]['id'])['naam']);

    $sectie = db()->query('SELECT sectie FROM info_cards LIMIT 1')->fetchColumn();
    $cards = get_info_cards($sectie);
    assert_true(count($cards) > 0);
    assert_same([], get_info_cards('bestaat-niet'));
    assert_same($cards[0]['titel'], get_info_card((int) $cards[0]['id'])['titel']);
});

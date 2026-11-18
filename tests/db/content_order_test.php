<?php
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/admin/includes/content_order.php';

test('kaartvolgorde werkt bij gelijke nummers en blijft binnen eigen sectie', function () {
    db()->exec("INSERT INTO info_cards (titel, sectie, volgorde) VALUES ('A', 'test_home', 0), ('B', 'test_home', 0), ('C', 'test_anders', 42)");
    $cards = get_info_cards('test_home');
    $a = (int) $cards[0]['id'];
    $b = (int) $cards[1]['id'];
    $other = get_info_cards('test_anders');
    move_content_record('info_cards', $b, 'up');
    assert_same(['B', 'A'], array_column(get_info_cards('test_home'), 'titel'));
    move_content_record('info_cards', $b, 'down');
    assert_same(['A', 'B'], array_column(get_info_cards('test_home'), 'titel'));
    move_content_record('info_cards', $a, 'up');
    move_content_record('info_cards', $a, 'ongeldig');
    assert_same(['A', 'B'], array_column(get_info_cards('test_home'), 'titel'));
    assert_same($other, get_info_cards('test_anders'));
    assert_true((int) db()->query("SELECT COUNT(*) FROM audit_log WHERE actie = 'move' AND entiteit = 'info_card'")->fetchColumn() >= 2);
});

test('oude sorteeraanvragen voor paginas wijzigen geen gegevens', function () {
    db()->exec("INSERT INTO pages (titel, slug, inhoud, volgorde) VALUES ('A', 'sort-a', '', 0), ('B', 'sort-b', '', 0)");
    $a = (int) db()->query("SELECT id FROM pages WHERE slug = 'sort-a'")->fetchColumn();
    $b = (int) db()->query("SELECT id FROM pages WHERE slug = 'sort-b'")->fetchColumn();
    $cards = db()->query('SELECT * FROM info_cards ORDER BY id')->fetchAll();
    $settings = db()->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll();
    $titles = function () { return array_column(db()->query("SELECT titel FROM pages WHERE slug IN ('sort-a', 'sort-b') ORDER BY volgorde, id")->fetchAll(), 'titel'); };
    move_content_record('pages', $b, 'up');
    assert_same(['A', 'B'], $titles());
    move_content_record('pages', $a, 'up');
    assert_same(['A', 'B'], $titles());
    move_content_record('settings', $a, 'up');
    move_content_record('pages', $a, 'ongeldig');
    assert_same(['A', 'B'], $titles());
    assert_same($cards, db()->query('SELECT * FROM info_cards ORDER BY id')->fetchAll());
    assert_same($settings, db()->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll());
});

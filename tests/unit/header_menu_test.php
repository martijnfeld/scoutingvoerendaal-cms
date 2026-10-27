<?php
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/includes/header_menu.php';

test('header-URLs weigeren onveilige schema’s en browsernormalisatie', function () {
    foreach (['javascript:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.test', '/\\evil.test', '%2f%2fevil.test', "https://example.org/\n", 'https://user:pass@example.org', 'ftp://example.org', '../admin', '', 'java%0ascript:alert(1)', 'https://example.org/%0d%0aX'] as $url) {
        assert_false(header_menu_safe_url($url), $url);
    }
    foreach (['https://example.org/pad?q=1#x', 'http://example.org', 'mailto:info@example.org', 'tel:+31451234567', 'leden', '/leden', './leden', '#contact'] as $url) {
        assert_true(header_menu_safe_url($url), $url);
    }
});

test('centrale URL-resolutie respecteert homepage, onderliggende routes en subdirectory', function () {
    $item = ['link_type' => 'section', 'link_value' => 'programma'];
    assert_same('programma', header_menu_url($item, ''));
    assert_same('programma', header_menu_url($item, './'));
    assert_same('leden-nieuw', header_menu_url(['link_type' => 'page', 'page_slug' => 'leden-nieuw', 'page_active' => 1], './'));
    assert_same(null, header_menu_url(['link_type' => 'page', 'page_slug' => 'leden', 'page_active' => 0], './'));
    assert_same('./leden', header_menu_url(['link_type' => 'url', 'link_value' => '/leden'], './'));
    // Een <base href> op geneste routes maakt deze URL's relatief aan de installatiemap.
    $layout = file_get_contents(TEST_ROOT . '/includes/site_layout_top.php');
    assert_contains('<base href=', $layout);
    assert_contains('header_menu_tree($homeHref', $layout);
});

test('programmalinks verwijzen naar de pagina en behouden het speltakanker', function () {
    assert_same('programma', header_menu_url(['link_type' => 'programma'], './'));
    assert_same('programma#sk-welpen', programma_url('welpen'));
    assert_true(in_array('programma', PAGE_RESERVED_SLUGS, true));
    $item = header_menu_validate(['label' => 'Programma', 'link_type' => 'programma', 'dropdown_type' => 'programme', 'link_value' => 'oud'], [], 0);
    assert_same('', $item['link_value']);
    assert_same(null, $item['page_id']);
});

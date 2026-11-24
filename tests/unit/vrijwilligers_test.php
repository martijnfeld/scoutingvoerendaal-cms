<?php
require_once TEST_ROOT . '/includes/functions.php';

test('shortcode_parse_attributes() leest gewone en typografische aanhalingstekens', function () {
    assert_same(['groep' => 'bestuur'], shortcode_parse_attributes(' groep="bestuur"'));
    assert_same(['groep' => 'bestuur'], shortcode_parse_attributes(' groep=&quot;bestuur&quot;'));
    assert_same(['speltak' => 'welpen'], shortcode_parse_attributes(" speltak='welpen'"));
    assert_same(['groep' => 'bestuur'], shortcode_parse_attributes(' groep=“bestuur”'));
    assert_same(['groep' => 'bestuur'], shortcode_parse_attributes(' groep=„bestuur”'));
    assert_same(['groep' => 'bestuur'], shortcode_parse_attributes(' groep=‘bestuur’'));
    assert_same(['groep' => 'a', 'speltak' => 'b'], shortcode_parse_attributes("\xC2\xA0GROEP=\"a\"&nbsp;speltak=\"b\""));
    assert_same([], shortcode_parse_attributes(''));
});

test('smoelenboek_email_attr() is omgekeerd base64 en terug te zetten', function () {
    $attr = smoelenboek_email_attr('jan@example.org');
    assert_not_contains('jan@example.org', $attr);
    assert_same('jan@example.org', strrev(base64_decode($attr)));
});

test('smoelenboek_html() escapet, verbergt het e-mailadres en slaat lege secties over', function () {
    $html = smoelenboek_html([
        ['titel' => 'Lege sectie', 'kleur' => null, 'leden' => []],
        ['titel' => 'Bestuur <b>', 'kleur' => '#123456', 'leden' => [
            ['naam' => 'Jan <script>', 'foto' => 'assets/uploads/a.jpg', 'functie' => 'Voorzitter', 'email' => 'jan@example.org'],
            ['naam' => 'Piet', 'foto' => '', 'functie' => '', 'email' => ''],
        ]],
    ]);
    assert_not_contains('Lege sectie', $html);
    assert_contains('Bestuur &lt;b&gt;', $html);
    assert_contains('Jan &lt;script&gt;', $html);
    assert_not_contains('<script>', $html);
    assert_not_contains('jan@example.org', $html);
    assert_not_contains('mailto:', $html);
    assert_contains('data-m="' . smoelenboek_email_attr('jan@example.org') . '"', $html);
    assert_contains('src="assets/uploads/a.jpg"', $html);
    assert_contains('smoel-foto-leeg', $html, 'zonder foto een icoon');
    assert_same(1, substr_count($html, 'class="smoel-mail"') - substr_count($html, '<span class="smoel-mail">'), 'alleen Jan heeft een e-maillink');
});

test('render_shortcodes() laat inhoud zonder shortcode ongemoeid', function () {
    assert_same('', render_shortcodes(null));
    assert_same('<p>Hallo [iets]</p>', render_shortcodes('<p>Hallo [iets]</p>'));
});

<?php
/**
 * sanitize_feed_html(): de omschrijving uit Scoutdash is onvertrouwde HTML
 * die main.js als HTML in de pagina zet. Zie includes/scoutdash.php.
 */
require_once TEST_ROOT . '/includes/scoutdash.php';

/** Faalt als de opgeschoonde HTML nog iets bevat waarmee script kan draaien. */
function assert_feed_html_safe(string $input): string
{
    $out = sanitize_feed_html($input);
    foreach (['<script', '<svg', '<math', '<object', '<embed', '<iframe srcdoc', 'srcdoc=', 'javascript:', 'vbscript:', 'expression(', 'data:text'] as $needle) {
        assert_not_contains($needle, $out, 'invoer ' . test_export($input));
    }
    if (preg_match('/<[^>]+\son[a-z]+\s*=/i', $out)) {
        fail('event-handler blijft staan in ' . test_export($out));
    }
    return $out;
}

test('lege invoer blijft leeg', function () {
    assert_same('', sanitize_feed_html(''));
    assert_same('', sanitize_feed_html("  \n "));
});

test('gewone opmaak blijft staan', function () {
    $out = assert_feed_html_safe('<p style="color: red">Hallo <strong>allemaal</strong><br>tot <a href="https://example.org/x?a=1&b=2">zaterdag</a></p>');
    assert_same('<p style="color: red">Hallo <strong>allemaal</strong><br>tot <a href="https://example.org/x?a=1&amp;b=2">zaterdag</a></p>', $out);
});

test('tekst en attributen worden volledig ge-escaped', function () {
    assert_same('<p>1 &lt; 2 &amp; &quot;x&quot;</p>', sanitize_feed_html('<p>1 &lt; 2 &amp; "x"</p>'));
    assert_same('<p title="&quot;&gt;&lt;b&gt;">x</p>', sanitize_feed_html('<p title="&quot;&gt;&lt;b&gt;">x</p>'));
});

test('UTF-8 blijft intact', function () {
    assert_same('<p>Opkomst — €5 · café</p>', sanitize_feed_html('<p>Opkomst — €5 · café</p>'));
});

test('script-, style- en svg-elementen verdwijnen met inhoud', function () {
    assert_same('<p>a</p><p>b</p>', assert_feed_html_safe('<p>a</p><script>alert(1)</script><style>p{}</style><svg onload="alert(1)"><circle/></svg><p>b</p>'));
    assert_feed_html_safe('<math><mtext><img src=x onerror=alert(1)></mtext></math>');
    assert_feed_html_safe('<object data="javascript:alert(1)"></object><embed src="x.swf">');
});

test('event-handlers worden verwijderd', function () {
    assert_same('<img src="x.png">', assert_feed_html_safe('<img src="x.png" onerror="alert(1)" ONLOAD=alert(2)>'));
    assert_feed_html_safe('<div onmouseover="alert(1)">x</div>');
    assert_feed_html_safe('<a href="#" onclick="alert(1)">x</a>');
});

test('javascript:-URLs worden verwijderd, ook verstopt', function () {
    assert_same('<a>x</a>', assert_feed_html_safe('<a href="javascript:alert(1)">x</a>'));
    assert_same('<a>x</a>', assert_feed_html_safe("<a href=\"java\tscript:alert(1)\">x</a>"));
    assert_same('<a>x</a>', assert_feed_html_safe('<a href="&#106;avascript:alert(1)">x</a>'));
    assert_same('<a>x</a>', assert_feed_html_safe('<a href=" JaVaScRiPt:alert(1)">x</a>'));
    assert_feed_html_safe('<form action="javascript:alert(1)"><button formaction="javascript:alert(1)">x</button></form>');
    assert_feed_html_safe('<img srcset="ok.png 1x, javascript:alert(1) 2x">');
});

test('mailto, tel en relatieve links mogen', function () {
    assert_same('<a href="mailto:info@example.org">m</a>', sanitize_feed_html('<a href="mailto:info@example.org">m</a>'));
    assert_same('<a href="tel:+31123">t</a>', sanitize_feed_html('<a href="tel:+31123">t</a>'));
    assert_same('<a href="/pagina">p</a>', sanitize_feed_html('<a href="/pagina">p</a>'));
});

test('data:-URL alleen als afbeelding in img src', function () {
    assert_same('<img src="data:image/png;base64,AAAA">', sanitize_feed_html('<img src="data:image/png;base64,AAAA">'));
    assert_same('<img>', assert_feed_html_safe('<img src="data:image/svg+xml;base64,AAAA">'));
    assert_same('<a>x</a>', assert_feed_html_safe('<a href="data:text/html,<script>alert(1)</script>">x</a>'));
});

test('iframes houden geen srcdoc of inhoud', function () {
    $out = assert_feed_html_safe('<iframe src="https://www.youtube.com/embed/x" srcdoc="<script>alert(1)</script>">fallback</iframe>');
    assert_same('<iframe src="https://www.youtube.com/embed/x"></iframe>', $out);
    assert_feed_html_safe('<iframe src="javascript:alert(1)"></iframe>');
});

test('gevaarlijke CSS in style wordt verwijderd', function () {
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="width: expression(alert(1))">x</p>'));
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="background: url(javascript:alert(1))">x</p>'));
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="background: url(\6a avascript:alert(1))">x</p>'));
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="behavior: url(x.htc)">x</p>'));
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="-moz-binding: url(x)">x</p>'));
    assert_same('<p>x</p>', assert_feed_html_safe('<p style="e/**/xpression(alert(1))">x</p>'));
});

test('commentaar en mutation-XSS-trucs leveren geen tags op', function () {
    assert_same('<p>a</p>', sanitize_feed_html('<p>a<!-- <script>alert(1)</script> --></p>'));
    assert_feed_html_safe('<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>');
    assert_feed_html_safe('<template><img src=x onerror=alert(1)></template>');
    assert_feed_html_safe('<xmp><img src=x onerror=alert(1)></xmp>');
    assert_feed_html_safe('<p>"><img src=x onerror=alert(1)></p>');
    assert_feed_html_safe('<base href="javascript:alert(1)//"><a href="x">x</a>');
});

test('opkomsten_sanitize_items() schoont alleen omschrijving op', function () {
    $items = opkomsten_sanitize_items([
        ['titel' => '<b>blijft</b>', 'omschrijving' => '<p onclick="x()">Hoi</p>'],
        ['titel' => 'geen omschrijving'],
        ['omschrijving' => ['geen', 'string']],
        'geen array',
    ]);
    assert_same('<b>blijft</b>', $items[0]['titel'], 'andere velden escapet main.js zelf');
    assert_same('<p>Hoi</p>', $items[0]['omschrijving']);
    assert_same('', $items[1]['omschrijving']);
    assert_same('', $items[2]['omschrijving']);
    assert_same('geen array', $items[3]);
});

<?php
require_once TEST_ROOT . '/includes/scoutdash.php';

test('url_has_allowed_scheme() voor feed-URLs (alleen https)', function () {
    assert_true(url_has_allowed_scheme('https://scoutdash.nl/feed/abc', ['https']));
    assert_true(url_has_allowed_scheme('HTTPS://scoutdash.nl/feed', ['https']), 'schema hoofdletterongevoelig');
    assert_false(url_has_allowed_scheme('http://scoutdash.nl/feed', ['https']));
    assert_false(url_has_allowed_scheme('', ['https']));
    assert_false(url_has_allowed_scheme('file:///etc/passwd', ['https']));
    assert_false(url_has_allowed_scheme('gopher://example.org/', ['https']));
    assert_false(url_has_allowed_scheme('https:///zonder-host', ['https']));
    assert_false(url_has_allowed_scheme('scoutdash.nl/feed', ['https']), 'relatief');
    assert_false(url_has_allowed_scheme('javascript:alert(1)', ['http', 'https']));
});

test('url_has_allowed_scheme() voor afmeldlinks (http of https)', function () {
    assert_true(url_has_allowed_scheme('http://example.org/afmelden', ['http', 'https']));
    assert_true(url_has_allowed_scheme('https://example.org/afmelden?x=1', ['http', 'https']));
    assert_false(url_has_allowed_scheme('mailto:info@example.org', ['http', 'https']));
});

test('scoutdash_http_get() weigert niet-https zonder iets op te halen', function () {
    assert_same(null, scoutdash_http_get('http://example.org/'));
    assert_same(null, scoutdash_http_get('file:///etc/passwd'));
});

test('scoutdash_resolve_redirect() maakt Location-headers absoluut', function () {
    $base = 'https://scoutdash.nl:8443/api/feed/abc?x=1';
    assert_same('https://elders.nl/x', scoutdash_resolve_redirect($base, 'https://elders.nl/x'));
    assert_same('http://elders.nl/x', scoutdash_resolve_redirect($base, 'http://elders.nl/x'), 'schema controleert de aanroeper');
    assert_same('https://cdn.nl/y', scoutdash_resolve_redirect($base, '//cdn.nl/y'));
    assert_same('https://scoutdash.nl:8443/nieuw', scoutdash_resolve_redirect($base, '/nieuw'));
    assert_same('https://scoutdash.nl:8443/api/feed/def', scoutdash_resolve_redirect($base, 'def'));
    assert_same('https://scoutdash.nl/x', scoutdash_resolve_redirect('https://scoutdash.nl', 'x'));
    assert_same(null, scoutdash_resolve_redirect('geen-url', 'x'));
});

test('scoutdash_parse_response_headers() kijkt naar het laatste antwoord', function () {
    $parsed = scoutdash_parse_response_headers([
        'HTTP/1.1 301 Moved Permanently',
        'Location: https://a.nl/1',
        'HTTP/1.1 200 OK',
        'Content-Type: application/json',
    ]);
    assert_same(['status' => 200, 'location' => null], $parsed);

    $parsed = scoutdash_parse_response_headers(['HTTP/2 302', 'location:  https://b.nl/2 ']);
    assert_same(['status' => 302, 'location' => 'https://b.nl/2'], $parsed);

    assert_same(['status' => 0, 'location' => null], scoutdash_parse_response_headers([]));
});

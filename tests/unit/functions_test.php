<?php
require_once TEST_ROOT . '/includes/functions.php';

test('e() escapet HTML en accepteert null', function () {
    assert_same('&lt;a href=&quot;x&quot;&gt;&#039;&amp;', e('<a href="x">\'&'));
    assert_same('', e(null));
});

test('slugify() maakt nette slugs met fallback', function () {
    assert_same('welpen-scouts', slugify('  Welpen & Scouts!  '));
    assert_same('caf-2025', slugify('Café 2025'), 'niet-ASCII valt weg');
    assert_same('a-b', slugify('--a---b--'));
    assert_same('item', slugify('!!!'));
    assert_same('speltak', slugify('', 'speltak'));
});

test('asset_url() voegt de versie toe voor cachebusting', function () {
    assert_same('assets/css/style.css?v=' . rawurlencode(APP_VERSION), asset_url('assets/css/style.css'));
    assert_same('../assets/js/admin.js?v=' . rawurlencode(APP_VERSION), asset_url('../assets/js/admin.js'));
});

test('page_url() geeft een relatieve, ge-encodede URL', function () {
    assert_same('ledeninfo', page_url('ledeninfo'));
    assert_same('a%20b', page_url('a b'));
});

test('PAGE_RESERVED_SLUGS bevat alleen geldige slugs', function () {
    foreach (PAGE_RESERVED_SLUGS as $slug) {
        assert_same($slug, slugify($slug), 'gereserveerde slug');
    }
});

test('password_policy_error() accepteert een sterk wachtwoord', function () {
    assert_same(null, password_policy_error('Lang-genoeg-12!'));
    assert_same(null, password_policy_error('zomer kamp 2025 #1'));
});

test('password_policy_error() weigert te korte of te simpele wachtwoorden', function () {
    assert_true(is_string(password_policy_error('Kort-12!')), 'te kort');
    assert_true(is_string(password_policy_error('heellangwachtwoord12')), 'geen speciale tekens');
    assert_true(is_string(password_policy_error('heel-lang-wachtwoord!1')), 'maar één cijfer');
    assert_true(is_string(password_policy_error('')), 'leeg');
});

test('password_policy_error() telt tekens, geen bytes', function () {
    // 10 tekens maar >12 bytes: moet geweigerd worden.
    assert_true(is_string(password_policy_error('éééééé12!!')));
    // 12 tekens met multibyte-letters: voldoet.
    assert_same(null, password_policy_error('éééééééé12!!'));
});

test('password_policy_error() weigert ongeldige UTF-8', function () {
    assert_same('Wachtwoord bevat ongeldige tekens.', password_policy_error("abc\xff\xfedef12!!xyz"));
});

test('request_is_https() herkent HTTPS, poort 443 en X-Forwarded-Proto', function () {
    $backup = $_SERVER;
    try {
        $_SERVER = [];
        assert_false(request_is_https(), 'geen indicatie');
        $_SERVER = ['HTTPS' => 'off'];
        assert_false(request_is_https(), 'HTTPS=off');
        $_SERVER = ['HTTPS' => 'on'];
        assert_true(request_is_https(), 'HTTPS=on');
        $_SERVER = ['SERVER_PORT' => 443];
        assert_true(request_is_https(), 'poort 443');
        $_SERVER = ['HTTP_X_FORWARDED_PROTO' => 'HTTPS'];
        assert_true(request_is_https(), 'proxy');
    } finally {
        $_SERVER = $backup;
    }
});

test('session_cookie_params() is HttpOnly en SameSite=Lax', function () {
    $params = session_cookie_params();
    assert_true($params['httponly']);
    assert_same('Lax', $params['samesite']);
    assert_same('/', $params['path']);
});

test('csrf_verify() klopt alleen met het juiste token', function () {
    $token = csrf_token();
    assert_same(64, strlen($token));
    assert_same($token, csrf_token(), 'token blijft gelijk binnen de sessie');
    assert_contains('value="' . $token . '"', csrf_field());

    $_POST = [];
    assert_false(csrf_verify(), 'geen token');
    $_POST = ['csrf_token' => str_repeat('0', 64)];
    assert_false(csrf_verify(), 'verkeerd token');
    $_POST = ['csrf_token' => $token];
    assert_true(csrf_verify(), 'juist token');
    $_POST = [];
});

test('flash_get() geeft een melding één keer terug', function () {
    flash_set('success', 'Opgeslagen.');
    assert_same(['type' => 'success', 'message' => 'Opgeslagen.'], flash_get());
    assert_same(null, flash_get());
});

test('APP_VERSION is een geldige versie', function () {
    assert_matches('/^\d+\.\d+\.\d+$/', APP_VERSION);
});

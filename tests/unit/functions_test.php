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

test('ga_measurement_id_normalize() accepteert GA4-ID\'s en leeg', function () {
    assert_same('G-AB12CD34EF', ga_measurement_id_normalize('G-AB12CD34EF'));
    assert_same('G-AB12CD34EF', ga_measurement_id_normalize('  g-ab12cd34ef '), 'spaties en kleine letters');
    assert_same('', ga_measurement_id_normalize('   '), 'leeg = uit');
});

test('ga_measurement_id_normalize() weigert UA-codes en onzin', function () {
    foreach (['UA-12345678-1', 'G-', 'AB12CD34', 'G-AB12"><script>', 'GTM-ABC123', 'G-ABCDEFGHIJKLMNOPQ'] as $value) {
        $error = null;
        try {
            ga_measurement_id_normalize($value);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
        assert_true(is_string($error), 'geweigerd: ' . $value);
    }
});

test('cookie_privacy_link_normalize() accepteert URL\'s en paden', function () {
    foreach (['', 'privacy', '/privacy', 'privacy#cookies', 'https://example.org/privacy', 'http://example.org/a?b=c'] as $value) {
        assert_same($value, cookie_privacy_link_normalize($value));
    }
});

test('cookie_privacy_link_normalize() weigert andere schema\'s en HTML', function () {
    foreach (['javascript:alert(1)', 'data:text/html,x', 'privacy"><b>', 'https://', 'ftp://example.org/'] as $value) {
        $error = null;
        try {
            cookie_privacy_link_normalize($value);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
        assert_true(is_string($error), 'geweigerd: ' . $value);
    }
});

test('google_site_verification_normalize() accepteert de code, de hele meta-tag en leeg', function () {
    $code = 'AbC-123_xyzABCdefGHIjklMNOpqrSTUvwx0123456';
    assert_same($code, google_site_verification_normalize($code));
    assert_same($code, google_site_verification_normalize('  ' . $code . ' '), 'spaties');
    assert_same($code, google_site_verification_normalize('<meta name="google-site-verification" content="' . $code . '" />'), 'hele tag');
    assert_same($code, google_site_verification_normalize("<meta name='google-site-verification' content='{$code}'>"), 'enkele aanhalingstekens');
    assert_same('', google_site_verification_normalize('  '), 'leeg = geen tag');
});

test('google_site_verification_normalize() weigert onzin', function () {
    foreach (['kort', 'code met spaties erin', 'abc"><script>alert(1)</script>', 'https://example.org/'] as $value) {
        $error = null;
        try {
            google_site_verification_normalize($value);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
        assert_true(is_string($error), 'geweigerd: ' . $value);
    }
});

test('json_ld_script() geeft geldige JSON die het script-blok niet kan afsluiten', function () {
    $name = "Scouting 't \"Dorp\" </script><script>alert(1)</script> \u{e9}\n";
    $html = json_ld_script(['@type' => 'NGO', 'name' => $name, 'url' => 'https://example.org/a']);
    assert_true(strpos($html, '<script type="application/ld+json">') === 0, 'begint met het JSON-LD-blok');
    assert_same(1, substr_count($html, '</script>'), 'alleen de eigen afsluitende tag');
    assert_contains('https://example.org/a', $html, 'slashes niet ge-escapet');
    $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
    $decoded = json_decode($json, true);
    assert_true(is_array($decoded), 'geldige JSON: ' . $json);
    assert_same($name, $decoded['name'], 'waarde komt ongeschonden terug');
});

test('json_ld_script() overleeft ongeldige UTF-8', function () {
    $html = json_ld_script(['name' => "Fout \xC3\x28 teken"]);
    $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
    assert_true(is_array(json_decode($json, true)), 'geldige JSON: ' . $json);
});

test('breadcrumb_json_ld() nummert de onderdelen vanaf 1', function () {
    $data = breadcrumb_json_ld([['Home', 'https://example.org/'], ['Ledeninfo', 'https://example.org/ledeninfo']]);
    assert_same('BreadcrumbList', $data['@type']);
    assert_same([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.org/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Ledeninfo', 'item' => 'https://example.org/ledeninfo'],
    ], $data['itemListElement']);
});

test('add_lazy_loading() voegt loading="lazy" toe, behalve waar al een loading staat', function () {
    assert_same('<p><img loading="lazy" src="a.jpg" alt=""></p>', add_lazy_loading('<p><img src="a.jpg" alt=""></p>'));
    assert_same('<IMG loading="lazy" src="a.jpg"><img loading="lazy" src="b.jpg">', add_lazy_loading('<IMG src="a.jpg"><img src="b.jpg">'));
    assert_same('<img src="a.jpg" loading="eager">', add_lazy_loading('<img src="a.jpg" loading="eager">'), 'bestaand loading blijft');
    assert_same('<imgx src="a"><p>geen img</p>', add_lazy_loading('<imgx src="a"><p>geen img</p>'));
    assert_same('', add_lazy_loading(null), 'null (lege kolom) mag');
});

test('ga_event_attrs() escapet en geeft geldige JSON-parameters', function () {
    assert_same(' data-ga-event="section_view"', ga_event_attrs('section_view'));
    $attrs = ga_event_attrs('cta_click', ['cta_text' => 'Kom "kijken" <nu> & café', 'n' => 2]);
    assert_true((bool) preg_match('/^ data-ga-event="cta_click" data-ga-params="([^"]*)"$/', $attrs, $m), 'geen losse aanhalingstekens');
    assert_not_contains('<', $attrs);
    assert_same(['cta_text' => 'Kom "kijken" <nu> & café', 'n' => 2],
        json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true));
});

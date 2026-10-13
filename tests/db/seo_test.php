<?php
/**
 * SEO: structured data van de organisatie, Search Console-code, de
 * deelafbeelding per pagina en migraties 0005/0006. Eigen bestand (eigen
 * proces), omdat get_all_settings() per request cachet: de instellingen
 * moeten gezet zijn vóór de eerste get_setting().
 */
require_once TEST_ROOT . '/includes/functions.php';

test('organization_json_ld(): type NGO, absolute URL\'s, lege velden en placeholders weggelaten', function () {
    // Vóór de eerste get_setting(): get_all_settings() cachet per request.
    set_setting('site_url', 'https://www.example.org/');
    set_setting('org_naam', "Scouting 't \"Dorp\"");
    set_setting('logo_image', 'assets/uploads/logo.png');
    set_setting('og_image', 'https://cdn.example.org/deel.jpg');
    set_setting('org_email', '');
    set_setting('org_straat', 'Kerkstraat 1');
    set_setting('org_postcode', '');
    set_setting('social_facebook', 'https://facebook.com/');
    set_setting('social_instagram', 'https://www.instagram.com/scoutingdorp');
    set_setting('social_youtube', 'javascript:alert(1)//x');
    set_setting('google_site_verification', 'AbC-123_xyzABCdefGHIjklMNOpqrSTUvwx0123456');

    $data = organization_json_ld();
    assert_same('https://schema.org', $data['@context']);
    assert_same('NGO', $data['@type']);
    assert_same("Scouting 't \"Dorp\"", $data['name']);
    assert_same('https://www.example.org/assets/uploads/logo.png', $data['logo'], 'relatief pad → absolute URL');
    assert_same('https://cdn.example.org/deel.jpg', $data['image'], 'volledige URL blijft ongewijzigd');
    assert_false(array_key_exists('email', $data), 'leeg e-mailadres weggelaten');
    assert_same('PostalAddress', $data['address']['@type']);
    assert_same('Kerkstraat 1', $data['address']['streetAddress']);
    assert_false(array_key_exists('postalCode', $data['address']), 'lege postcode weggelaten');
    assert_same(['https://www.instagram.com/scoutingdorp'], $data['sameAs'], 'alleen echte profiel-URL\'s');

    $json = json_decode(substr(json_ld_script($data), strlen('<script type="application/ld+json">'), -strlen('</script>')), true);
    assert_same($data, $json, 'als JSON-LD te decoderen');

    assert_same('AbC-123_xyzABCdefGHIjklMNOpqrSTUvwx0123456', google_site_verification());
});

test('install.sql en migratie 0005 geven dezelfde kolom og_afbeelding', function () {
    $column = fn() => db()->query("SHOW COLUMNS FROM pages LIKE 'og_afbeelding'")->fetch();
    $fromInstall = $column();
    assert_true(is_array($fromInstall), 'kolom na install.sql');

    db()->exec('ALTER TABLE pages DROP COLUMN og_afbeelding');
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0005_page_og_image.sql'));
    assert_same($fromInstall, $column(), 'na migratie 0005');

    $order = db()->query('SHOW COLUMNS FROM pages')->fetchAll(PDO::FETCH_COLUMN);
    assert_same(array_search('meta_omschrijving', $order, true) + 1, array_search('og_afbeelding', $order, true), 'na meta_omschrijving');
});

test('install.sql en migratie 0006 geven de instelling google_site_verification', function () {
    $value = function () {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'google_site_verification'");
        $stmt->execute();
        return $stmt->fetchColumn();
    };
    db()->exec("DELETE FROM settings WHERE setting_key = 'google_site_verification'");
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0006_google_site_verification.sql'));
    assert_same('', $value(), 'na migratie 0006');

    set_setting('google_site_verification', 'eigen-code-1234567890');
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0006_google_site_verification.sql'));
    assert_same('eigen-code-1234567890', $value(), 'migratie overschrijft niets');
});

test('find_upload_usage() ziet de deelafbeelding van een pagina', function () {
    $file = 'fedcba9876543210.jpg';
    db()->prepare("INSERT INTO pages (titel, slug, inhoud, og_afbeelding) VALUES ('Met deelfoto', 'met-deelfoto', '', :f)")
        ->execute(['f' => rtrim(UPLOAD_URL, '/') . '/' . $file]);
    assert_true(in_array('Pagina: Met deelfoto', find_upload_usage($file), true));
});

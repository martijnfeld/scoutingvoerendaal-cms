<?php
/**
 * Google Analytics-instellingen en de standaardteksten van de cookiebanner.
 * Eigen bestand (eigen proces), omdat get_all_settings() per request cachet:
 * de instellingen moeten gezet zijn vóór de eerste get_setting().
 */
require_once TEST_ROOT . '/includes/functions.php';

function analytics_settings_from_db(): array
{
    $keys = array_keys(COOKIE_BANNER_DEFAULTS);
    $stmt = db()->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('
        . implode(',', array_fill(0, count($keys), '?')) . ')');
    $stmt->execute($keys);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

test('install.sql en migratie 0004 geven dezelfde standaardteksten als COOKIE_BANNER_DEFAULTS', function () {
    $expected = COOKIE_BANNER_DEFAULTS;
    ksort($expected);
    $fromInstall = analytics_settings_from_db();
    ksort($fromInstall);
    assert_same($expected, $fromInstall, 'na install.sql');

    db()->exec("DELETE FROM settings WHERE setting_key LIKE 'cookie\\_%'");
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0004_analytics_settings.sql'));
    $fromMigration = analytics_settings_from_db();
    ksort($fromMigration);
    assert_same($expected, $fromMigration, 'na migratie 0004');

    set_setting('cookie_banner_title', 'Eigen titel');
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0004_analytics_settings.sql'));
    assert_same('Eigen titel', analytics_settings_from_db()['cookie_banner_title'], 'migratie overschrijft niets');
});

test('ga_measurement_id() en cookie_banner_texts() lezen de instellingen', function () {
    // Vóór de eerste get_setting(): get_all_settings() cachet per request.
    set_setting('ga_measurement_id', ' g-ab12cd34 ');
    set_setting('cookie_banner_title', '');
    set_setting('cookie_banner_accept', 'Prima!');
    assert_same('G-AB12CD34', ga_measurement_id());
    $texts = cookie_banner_texts();
    assert_same(COOKIE_BANNER_DEFAULTS['cookie_banner_title'], $texts['cookie_banner_title'], 'leeg → standaardtekst');
    assert_same('Prima!', $texts['cookie_banner_accept']);
});

<?php
// Eigen proces (zie tests/run.php), dus deze constanten gelden alleen hier.
define('ADMIN_LOGIN_COUNTRIES', 'be, de');
require_once TEST_ROOT . '/config.php';
require_once TEST_ROOT . '/includes/geoip.php';

test('ADMIN_LOGIN_COUNTRIES perkt de landenlijst in (hoofdletterongevoelig)', function () {
    assert_false(admin_login_allowed_from_ip('193.0.0.1'), 'NL staat niet in de lijst');
    assert_false(admin_login_allowed_from_ip('8.8.8.8'), 'buiten Europa');
    assert_true(admin_login_allowed_from_ip('127.0.0.1'), 'privé-adressen blijven toegestaan');
});

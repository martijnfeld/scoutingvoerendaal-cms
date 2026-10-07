<?php
/** Brute-force-blokkade en sessiecontrole van het beheerpaneel. */
require_once TEST_ROOT . '/admin/includes/auth.php';

function login_attempts_reset(): void
{
    db()->exec('DELETE FROM login_attempts');
}

test('geen blokkade zonder mislukte pogingen', function () {
    login_attempts_reset();
    assert_same(['locked' => false, 'retry_after' => 0], login_lockout_status('beheer', '203.0.113.1'));
});

test('gebruikersnaam wordt geblokkeerd na LOGIN_MAX_ATTEMPTS pogingen', function () {
    login_attempts_reset();
    for ($i = 1; $i < LOGIN_MAX_ATTEMPTS; $i++) {
        record_failed_login('beheer', '203.0.113.' . $i);
    }
    assert_false(login_lockout_status('beheer', '198.51.100.1')['locked'], 'nog net niet');

    record_failed_login('beheer', '203.0.113.99');
    $status = login_lockout_status('beheer', '198.51.100.1');
    assert_true($status['locked'], 'vanaf elk IP-adres');
    assert_true($status['retry_after'] > LOGIN_LOCKOUT_MINUTES * 60 - 60 && $status['retry_after'] <= LOGIN_LOCKOUT_MINUTES * 60,
        'retry_after ' . $status['retry_after']);
    assert_false(login_lockout_status('iemand-anders', '198.51.100.1')['locked'], 'andere gebruiker');

    clear_login_attempts('beheer');
    assert_false(login_lockout_status('beheer', '198.51.100.1')['locked'], 'na geslaagde login');
});

test('IP-adres wordt geblokkeerd, ook zonder gebruikersnaam', function () {
    login_attempts_reset();
    for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) {
        record_failed_login('gok' . $i, '203.0.113.50');
    }
    assert_true(login_lockout_status('', '203.0.113.50')['locked']);
    assert_true(login_lockout_status('onbekend', '203.0.113.50')['locked']);
    assert_false(login_lockout_status('', '203.0.113.51')['locked']);
});

test('oude pogingen tellen niet en worden opgeruimd', function () {
    login_attempts_reset();
    $stmt = db()->prepare("INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES ('oud', '203.0.113.7', DATE_SUB(NOW(), INTERVAL :m MINUTE))");
    for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) {
        $stmt->execute(['m' => LOGIN_LOCKOUT_MINUTES + 1]);
    }
    assert_false(login_lockout_status('oud', '203.0.113.7')['locked'], 'buiten het blokkadevenster');

    db()->exec("INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES ('heel-oud', '203.0.113.8', DATE_SUB(NOW(), INTERVAL " . (LOGIN_ATTEMPT_RETENTION_MONTHS + 1) . " MONTH))");
    purge_old_login_attempts();
    $usernames = db()->query('SELECT DISTINCT username FROM login_attempts')->fetchAll(PDO::FETCH_COLUMN);
    assert_same(['oud'], $usernames);
});

/** Maakt een beheerder aan en logt deze sessie in; geeft de gebruikersrij terug. */
function login_test_user(string $password = 'Test-wachtwoord-12!'): array
{
    db()->exec('DELETE FROM admin_users');
    $stmt = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
    $stmt->execute(['u' => 'beheer', 'p' => password_hash($password, PASSWORD_DEFAULT)]);
    $user = db()->query("SELECT * FROM admin_users WHERE username = 'beheer'")->fetch();
    admin_session_login($user);
    return $user;
}

test('ingelogde sessie blijft geldig en houdt activiteit bij', function () {
    $user = login_test_user();
    assert_same((int) $user['id'], $_SESSION['admin_id']);
    assert_same('beheer', current_admin_username());
    $_SESSION['admin_last_activity'] = time() - 60;
    assert_false(admin_session_check_expired());
    assert_true(time() - $_SESSION['admin_last_activity'] < 5, 'activiteit bijgewerkt');
});

test('sessie verloopt na inactiviteit', function () {
    login_test_user();
    $_SESSION['admin_last_activity'] = time() - ADMIN_SESSION_IDLE_TIMEOUT - 1;
    assert_true(admin_session_check_expired());
    assert_same([], $_SESSION, 'sessie leeggemaakt');
});

test('sessie verloopt na de maximale duur, ook bij activiteit', function () {
    login_test_user();
    $_SESSION['admin_login_at'] = time() - ADMIN_SESSION_MAX_LIFETIME - 1;
    assert_true(admin_session_check_expired());
});

test('wachtwoordwijziging logt andere sessies uit', function () {
    $user = login_test_user();
    $stmt = db()->prepare('UPDATE admin_users SET password_hash = :p WHERE id = :id');
    $stmt->execute(['p' => password_hash('Nieuw-wachtwoord-34!', PASSWORD_DEFAULT), 'id' => $user['id']]);
    assert_true(admin_session_check_expired());
});

test('verwijderd account logt uit', function () {
    login_test_user();
    db()->exec('DELETE FROM admin_users');
    assert_true(admin_session_check_expired());
});

test('niet-ingelogde sessie is niet "verlopen"', function () {
    $_SESSION = [];
    assert_false(admin_session_check_expired());
});

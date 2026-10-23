<?php
/** Auditlog: vastleggen, lijst met paging/filters, opruimen en ontbrekende tabel. */
require_once TEST_ROOT . '/admin/includes/auth.php';

test('audit_log legt gebruiker, actie en IP vast', function () {
    db()->exec('DELETE FROM audit_log');
    $_SESSION['admin_id'] = 7;
    $_SESSION['admin_username'] = 'beheer';
    audit_log('create', 'pagina', 12, 'Pagina "Test" toegevoegd');

    $row = db()->query('SELECT * FROM audit_log')->fetch();
    assert_same(7, (int) $row['user_id']);
    assert_same('beheer', $row['username']);
    assert_same('create', $row['actie']);
    assert_same('pagina', $row['entiteit']);
    assert_same(12, (int) $row['entiteit_id']);
    assert_same('Pagina "Test" toegevoegd', $row['omschrijving']);
    assert_true($row['aangemaakt_op'] !== '', 'tijdstip');
});

test('audit_log zonder sessie gebruikt de opgegeven actor', function () {
    db()->exec('DELETE FROM audit_log');
    $_SESSION = [];
    audit_log('backup', 'backup', null, 'cron', ['id' => null, 'username' => 'systeem']);
    $row = db()->query('SELECT * FROM audit_log')->fetch();
    assert_same('systeem', $row['username']);
    assert_same(null, $row['user_id']);
    assert_same(null, $row['entiteit_id']);
});

test('audit_log_list pagineert en filtert, nieuwste eerst', function () {
    db()->exec('DELETE FROM audit_log');
    $_SESSION = [];
    for ($i = 1; $i <= 5; $i++) {
        audit_log('update', $i % 2 ? 'speltak' : 'pagina', $i, "actie $i", ['id' => 1, 'username' => $i < 4 ? 'anna' : 'bert']);
    }
    $all = audit_log_list(2, 0);
    assert_same(5, $all['total']);
    assert_same(2, count($all['rows']));
    assert_same('actie 5', $all['rows'][0]['omschrijving']);
    assert_same('actie 3', audit_log_list(2, 2)['rows'][0]['omschrijving']);

    assert_same(3, audit_log_list(10, 0, ['username' => 'anna'])['total']);
    assert_same(3, audit_log_list(10, 0, ['entiteit' => 'speltak'])['total']);
    assert_same(1, audit_log_list(10, 0, ['username' => 'bert', 'entiteit' => 'speltak'])['total']);
    assert_same(['anna', 'bert'], audit_log_filter_options()['users']);
});

test('oude auditlogregels worden opgeruimd', function () {
    db()->exec('DELETE FROM audit_log');
    $_SESSION = [];
    audit_log('update', 'pagina', 1, 'nieuw', ['id' => 1, 'username' => 'anna']);
    db()->exec("INSERT INTO audit_log (aangemaakt_op, username, actie, entiteit, omschrijving)
                VALUES (DATE_SUB(NOW(), INTERVAL " . (AUDIT_LOG_RETENTION_MONTHS + 1) . " MONTH), 'anna', 'update', 'pagina', 'oud')");
    purge_old_audit_log();
    assert_same(['nieuw'], db()->query('SELECT omschrijving FROM audit_log')->fetchAll(PDO::FETCH_COLUMN));
});

test('ontbrekende audit_log-tabel blokkeert niets', function () {
    db()->exec('DROP TABLE audit_log');
    $_SESSION = [];
    audit_log('update', 'pagina', 1, 'x', ['id' => 1, 'username' => 'anna']);
    purge_old_audit_log();
    assert_same(['total' => 0, 'rows' => []], audit_log_list(10));
    assert_same(['users' => [], 'entiteiten' => []], audit_log_filter_options());
    // Migratie herstelt de tabel.
    db()->exec(file_get_contents(TEST_ROOT . '/sql/migrations/0008_audit_log.sql'));
    audit_log('update', 'pagina', 1, 'y', ['id' => 1, 'username' => 'anna']);
    assert_same(1, audit_log_list(10)['total']);
});

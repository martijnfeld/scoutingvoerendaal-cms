<?php
/** Geplande taken: beurtcontrole, opruimtaak en terugval. */
require_once TEST_ROOT . '/includes/cron.php';

function cron_test_last_run_row(string $name): ?string
{
    // Direct uit de database: get_setting() cachet per request.
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
    $stmt->execute(['k' => 'cron_last_run_' . $name]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

test('back-upinterval is standaard wekelijks', function () {
    assert_same(7, backup_interval_days());
});

test('cron_job_is_due(): nooit gedraaid, te vroeg, op tijd en uitgeschakeld', function () {
    $now = 1_000_000_000;
    $weekly = ['label' => 'x', 'interval' => 7 * 86400, 'run' => 'strlen'];
    assert_true(cron_job_is_due($weekly, null, $now), 'nooit gedraaid');
    assert_false(cron_job_is_due($weekly, $now - 3 * 86400, $now), 'drie dagen geleden');
    assert_true(cron_job_is_due($weekly, $now - 7 * 86400, $now), 'precies een week');
    assert_true(cron_job_is_due($weekly, $now - 7 * 86400 + 600, $now), 'binnen de marge (dagelijkse cron die iets eerder komt)');
    assert_false(cron_job_is_due($weekly, $now - 6 * 86400 - CRON_DUE_TOLERANCE + 60, $now), 'net buiten de marge');
    assert_false(cron_job_is_due(['label' => 'x', 'interval' => 0, 'run' => 'strlen'], null, $now), 'uitgeschakeld');
});

test('opruimtaak verwijdert verlopen rijen en laat recente staan', function () {
    db()->exec('DELETE FROM login_attempts');
    $stmt = db()->prepare('INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (:u, :ip, DATE_SUB(NOW(), INTERVAL :d DAY))');
    $stmt->execute(['u' => 'oud', 'ip' => '203.0.113.7', 'd' => LOGIN_ATTEMPT_RETENTION_MONTHS * 31 + 1]);
    $stmt->execute(['u' => 'recent', 'ip' => '203.0.113.7', 'd' => 1]);

    $summary = run_cleanup();
    assert_contains('login-pogingen: 1', $summary);
    assert_same(['recent'], db()->query('SELECT username FROM login_attempts')->fetchAll(PDO::FETCH_COLUMN));
});

test('run_cron() draait de opruimtaak, legt het vast en slaat een tweede keer over', function () {
    db()->exec("DELETE FROM settings WHERE setting_key LIKE 'cron\\_%'");

    $results = run_cron(['cleanup']);
    assert_same(1, count($results));
    assert_same('ok', $results[0]['status'], $results[0]['message']);
    assert_true(ctype_digit((string) cron_test_last_run_row('cleanup')), 'laatste run is vastgelegd');

    // Zelfde request: get_setting() is gecachet, dus lees de beurt opnieuw uit de database.
    $job = cron_jobs()['cleanup'];
    assert_false(cron_job_is_due($job, (int) cron_test_last_run_row('cleanup'), time()), 'net gedraaid, dus niet aan de beurt');
    $forced = run_cron(['cleanup'], true);
    assert_same('ok', $forced[0]['status'], 'force slaat de beurtcontrole over');
});

test('run_cron() negeert onbekende taaknamen', function () {
    assert_same([], run_cron(['bestaat-niet']));
});

test('terugval vanaf de inlogpagina gooit nooit een fout', function () {
    cron_run_cleanup_fallback();
    assert_true(true);
});

<?php
/**
 * sql/install.sql en sql/migrations/. De runner heeft de testdatabase net
 * leeggemaakt en install.sql geïmporteerd (zie test_db_reset()).
 */
require_once TEST_ROOT . '/includes/updater.php';
require_once TEST_ROOT . '/includes/checks.php';

test('install.sql maakt alle verwachte tabellen aan', function () {
    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (CHECK_EXPECTED_TABLES as $table) {
        assert_true(in_array($table, $tables, true), "tabel {$table}");
    }
});

test('alle tabellen zijn InnoDB met utf8mb4', function () {
    $stmt = db()->prepare('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db');
    $stmt->execute(['db' => DB_NAME]);
    foreach ($stmt->fetchAll() as $row) {
        assert_same('InnoDB', $row['ENGINE'], $row['TABLE_NAME']);
        assert_same(0, strpos($row['TABLE_COLLATION'], 'utf8mb4'), $row['TABLE_NAME'] . ' collation ' . $row['TABLE_COLLATION']);
    }
});

test('install.sql vult de startgegevens', function () {
    $counts = test_db_table_counts(db());
    foreach (['settings', 'speltakken', 'documents', 'info_cards'] as $table) {
        assert_true($counts[$table] > 0, "{$table} heeft startgegevens");
    }
    assert_same(0, $counts['admin_users'], 'geen beheerders: die maakt install.php aan');
    foreach (['site_url', 'org_naam', 'meta_description'] as $key) {
        assert_true(get_setting($key) !== '', "instelling {$key}");
    }
});

test('slugs van de startgegevens zijn geldig en uniek', function () {
    $slugs = db()->query('SELECT slug FROM speltakken')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($slugs as $slug) {
        assert_same($slug, slugify($slug), 'speltak-slug');
    }
    assert_same(count($slugs), count(array_unique($slugs)));
});

test('verse installatie: alle migraties gelden als toegepast', function () {
    mark_all_migrations_applied();
    $files = array_map('basename', list_migration_files());
    $applied = list_applied_migrations();
    sort($applied);
    assert_same($files, $applied);
    assert_same(['applied' => [], 'error' => null], run_pending_migrations());
});

test('run_pending_migrations() draait ontbrekende migraties één keer', function () {
    mark_all_migrations_applied();
    db()->exec("DELETE FROM schema_migrations WHERE filename = '0002_feed_url_https.sql'");
    $result = run_pending_migrations();
    assert_same(['applied' => ['0002_feed_url_https.sql'], 'error' => null], $result);
    assert_same(['applied' => [], 'error' => null], run_pending_migrations(), 'tweede keer niets meer');
});

test('migratie 0002 zet http-feeds om naar https', function () {
    db()->exec("UPDATE speltakken SET feed_url = NULL");
    db()->exec("UPDATE speltakken SET feed_url = ' HTTP://scoutdash.nl/feed/a' WHERE id = 1");
    db()->exec("UPDATE speltakken SET feed_url = 'https://scoutdash.nl/feed/b' WHERE id = 2");
    test_db_import(file_get_contents(TEST_ROOT . '/sql/migrations/0002_feed_url_https.sql'));
    $feeds = db()->query('SELECT id, feed_url FROM speltakken WHERE id IN (1, 2) ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    assert_same('https://scoutdash.nl/feed/a', $feeds[1]);
    assert_same('https://scoutdash.nl/feed/b', $feeds[2]);
});

test('run_pending_migrations() stopt bij de eerste fout', function () {
    $dir = migrations_dir();
    $bad = $dir . '/9998_test_kapot.sql';
    $after = $dir . '/9999_test_daarna.sql';
    file_put_contents($bad, 'SELECT * FROM tabel_die_niet_bestaat;');
    file_put_contents($after, 'SELECT 1;');
    try {
        mark_all_migrations_applied();
        db()->exec("DELETE FROM schema_migrations WHERE filename LIKE '999%'");
        $result = run_pending_migrations();
        assert_same([], $result['applied']);
        assert_contains('9998_test_kapot.sql', (string) $result['error']);
        assert_false(in_array('9999_test_daarna.sql', list_applied_migrations(), true), 'latere migratie niet uitgevoerd');
    } finally {
        @unlink($bad);
        @unlink($after);
    }
});

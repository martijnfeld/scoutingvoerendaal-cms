<?php
require_once TEST_ROOT . '/includes/backup.php';
require_once TEST_ROOT . '/includes/updater.php';

/** Verwijdert de back-ups uit de testmap (de map zelf ruimt test_bootstrap() op). */
function backup_test_cleanup(): void
{
    foreach (glob(BACKUP_DIR . '/*.zip') ?: [] as $file) {
        unlink($file);
    }
}

test('databasedump is terug te zetten met dezelfde inhoud', function () {
    // Lastige waarden: quotes, backslashes, NULL, UTF-8 en HTML.
    set_setting('test_lastig', "O'Brien \\ \"quote\" — café <p>&amp;</p>\nregel 2");
    db()->exec("INSERT INTO pages (titel, slug, inhoud, meta_omschrijving) VALUES ('Dump', 'dump', NULL, NULL)");

    $before = test_db_table_counts(db());
    $settingsBefore = db()->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll(PDO::FETCH_KEY_PAIR);

    $file = sys_get_temp_dir() . '/sv-test-dump-' . getmypid() . '.sql';
    try {
        dump_database_to_file($file);
        $sql = file_get_contents($file);
        assert_contains('DROP TABLE IF EXISTS `settings`;', $sql);

        test_db_recreate_empty();
        test_db_import($sql);
    } finally {
        @unlink($file);
    }

    $pdo = test_pdo();
    assert_same($before, test_db_table_counts($pdo), 'aantal rijen per tabel');
    $settingsAfter = $pdo->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll(PDO::FETCH_KEY_PAIR);
    assert_same($settingsBefore, $settingsAfter, 'instellingen ongewijzigd');
    assert_same(null, $pdo->query("SELECT inhoud FROM pages WHERE slug = 'dump'")->fetchColumn() ?: null, 'NULL blijft NULL');
});

test('create_backup() maakt een zip met database en bestanden, zonder uitzonderingen', function () {
    if (!class_exists('ZipArchive')) {
        skip('PHP-extensie zip ontbreekt');
    }
    backup_test_cleanup();
    try {
        $result = run_backup();
        assert_matches('/^backup-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-[0-9a-f]{8}\.zip$/', $result['created']);
        assert_same(0, $result['deleted']);

        $path = find_backup_path($result['created']);
        assert_same(BACKUP_DIR . DIRECTORY_SEPARATOR . $result['created'], $path);

        $zip = new ZipArchive();
        assert_true($zip->open($path) === true, 'zip te openen');
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $dump = $zip->getFromName('database.sql');
        $zip->close();

        assert_contains('CREATE TABLE `settings`', (string) $dump);
        foreach (['files/index.php', 'files/includes/functions.php', 'files/sql/install.sql'] as $expected) {
            assert_true(in_array($expected, $names, true), "{$expected} in de back-up");
        }
        foreach ($names as $name) {
            foreach (BACKUP_EXCLUDES as $excluded) {
                if (strpos($name, 'files/' . $excluded . '/') === 0 || $name === 'files/' . $excluded) {
                    fail("{$name} hoort niet in de back-up");
                }
            }
            assert_false(strpos($name, '.tmp-') !== false, "tijdelijk dumpbestand {$name} opgeruimd");
        }

        $listed = array_column(list_backups(), 'name');
        assert_true(in_array($result['created'], $listed, true), 'list_backups() toont de back-up');
    } finally {
        backup_test_cleanup();
    }
});

test('purge_old_backups() verwijdert alleen back-ups ouder dan de bewaartermijn', function () {
    backup_test_cleanup();
    try {
        $dir = backup_dir();
        $old = $dir . '/backup-2000-01-01_00-00-00-aaaaaaaa.zip';
        $new = $dir . '/backup-2099-01-01_00-00-00-bbbbbbbb.zip';
        file_put_contents($old, 'x');
        file_put_contents($new, 'x');
        touch($old, strtotime('-' . (BACKUP_RETENTION_MONTHS + 1) . ' months'));

        assert_same(1, purge_old_backups());
        assert_false(is_file($old));
        assert_true(is_file($new));
    } finally {
        backup_test_cleanup();
    }
});

<?php
require_once TEST_ROOT . '/includes/backup.php';

test('format_bytes() geeft Nederlandse notatie', function () {
    assert_same('0 B', format_bytes(0));
    assert_same('1023 B', format_bytes(1023));
    assert_same('1,5 KB', format_bytes(1536));
    assert_same('4,3 MB', format_bytes((int) (4.3 * 1024 * 1024)));
    assert_same('2048 GB', format_bytes(2048 * 1024 ** 3));
});

test('backup_path_is_inside()', function () {
    assert_true(backup_path_is_inside('/a/b/c', '/a/b'));
    assert_true(backup_path_is_inside('/a/b', '/a/b/'));
    assert_true(backup_path_is_inside('C:\\site\\backups', 'C:/site'));
    assert_false(backup_path_is_inside('/a/bc', '/a/b'), 'alleen hele mapnamen');
    assert_false(backup_path_is_inside('/a', '/a/b'));
    assert_false(backup_path_is_inside('/a', ''));
});

test('backup_path_allowed_by_open_basedir() zonder open_basedir', function () {
    if ((string) ini_get('open_basedir') !== '') {
        skip('open_basedir staat aan in deze PHP-omgeving');
    }
    assert_true(backup_path_allowed_by_open_basedir('/waar/dan/ook'));
});

test('find_backup_path() accepteert alleen kale .zip-namen', function () {
    assert_same(null, find_backup_path(''));
    assert_same(null, find_backup_path('backup.sql'));
    assert_same(null, find_backup_path('bestaat-niet.zip'));
    assert_same(null, find_backup_path('../../config.php'));
});

test('back-ups gaan naar de testmap, nooit naar de echte BACKUP_DIR', function () {
    assert_same(test_backup_dir(), BACKUP_DIR);
});

test('backup_dir_status() maakt de map aan en schermt hem af', function () {
    try {
        $status = backup_dir_status();
        assert_false($status['fallback']);
        assert_same(rtrim(BACKUP_DIR, '/\\'), $status['dir']);
        assert_true(is_dir($status['dir']));
        assert_same(BACKUP_HTACCESS, file_get_contents($status['dir'] . '/.htaccess'));
    } finally {
        @unlink(BACKUP_DIR . '/.htaccess');
        @rmdir(BACKUP_DIR);
    }
});

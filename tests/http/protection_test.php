<?php
/**
 * Afscherming via .htaccess, getest tegen de echte webserver. Dezelfde
 * lijsten als de browsertests in Beheerpaneel → Systeemcontrole.
 */
require_once TEST_ROOT . '/includes/checks.php';

function assert_blocked(string $path): void
{
    $r = http_get($path);
    if (!in_array($r['status'], [403, 404], true)) {
        fail("{$path} is bereikbaar (HTTP {$r['status']}), verwacht 403 of 404");
    }
}

test('gevoelige bestanden zijn niet bereikbaar', function () {
    $checked = 0;
    foreach (CHECK_BLOCKED_FILES as $file) {
        if (is_file(TEST_ROOT . '/' . $file)) {
            assert_blocked($file);
            $checked++;
        }
    }
    assert_true($checked > 5, 'er zijn bestanden getest');
});

test('afgeschermde mappen zijn niet bereikbaar', function () {
    foreach (['includes/', 'includes/db.php', 'admin/includes/', 'admin/includes/layout_top.php', 'sql/', 'sql/migrations/',
              'tools/', 'docker/', 'backups/', 'tests/', 'tests/run.php', 'tests/lib.php'] as $path) {
        assert_blocked($path);
    }
});

test('verborgen bestanden en mappen zijn niet bereikbaar', function () {
    foreach (['.htaccess', '.gitignore', '.gitattributes', 'assets/uploads/.htaccess', '.git/config', '.git/HEAD', '.github/workflows/tests.yml'] as $path) {
        assert_blocked($path);
    }
});

test('geen mappenoverzicht', function () {
    foreach (['assets/', 'assets/uploads/', 'assets/css/'] as $path) {
        $r = http_get($path);
        assert_not_contains('Index of', $r['body'], $path);
        assert_true($r['status'] !== 200, "{$path} geeft HTTP {$r['status']}");
    }
});

test('PHP in assets/uploads/ wordt nooit uitgevoerd', function () {
    $name = 'svtest' . bin2hex(random_bytes(4));
    $files = [
        $name . '.php' => '<?php echo "UITGEVOERD-" . (40 + 2);',
        $name . '.phtml' => '<?php echo "UITGEVOERD-" . (40 + 2);',
        $name . '.php.png' => '<?php echo "UITGEVOERD-" . (40 + 2);',
    ];
    $dir = TEST_ROOT . '/assets/uploads/';
    try {
        foreach ($files as $file => $content) {
            if (@file_put_contents($dir . $file, $content) === false) {
                skip('kan niet schrijven in assets/uploads/ (draaien de tests op dezelfde machine als de webserver?)');
            }
        }
        foreach (array_keys($files) as $file) {
            $r = http_get('assets/uploads/' . $file);
            assert_not_contains('UITGEVOERD-42', $r['body'], $file);
            assert_true(in_array($r['status'], [403, 404], true), "{$file} geeft HTTP {$r['status']}");
        }
    } finally {
        foreach (array_keys($files) as $file) {
            @unlink($dir . $file);
        }
    }
});

test('toegestane uploads worden wel geserveerd, met nosniff', function () {
    $file = 'svtest' . bin2hex(random_bytes(4)) . '.png';
    $path = TEST_ROOT . '/assets/uploads/' . $file;
    // 1x1 transparante PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    if (@file_put_contents($path, $png) === false) {
        skip('kan niet schrijven in assets/uploads/');
    }
    try {
        $r = http_get('assets/uploads/' . $file);
        assert_status(200, $r, $file);
        assert_same('nosniff', http_header($r, 'X-Content-Type-Options'));
        assert_same($png, $r['body']);
    } finally {
        @unlink($path);
    }
});

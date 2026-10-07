<?php
require_once TEST_ROOT . '/includes/updater.php';

test('render_release_notes() escapet HTML uit de release', function () {
    $html = render_release_notes("<script>alert(1)</script>\n<img src=x onerror=alert(1)>");
    assert_not_contains('<script', $html);
    assert_not_contains('<img', $html);
    assert_contains('&lt;script&gt;', $html);
});

test('render_release_notes() ondersteunt koppen, lijsten en inline opmaak', function () {
    $html = render_release_notes("## Nieuw\n- **Vet** punt\n- `code`\n\nTekst met [link](https://github.com/x)");
    assert_contains("<h4>Nieuw</h4>\n<ul>\n<li><strong>Vet</strong> punt</li>\n<li><code>code</code></li>\n</ul>", $html);
    assert_contains('<p>Tekst met <a href="https://github.com/x" target="_blank" rel="noopener noreferrer">link</a></p>', $html);
});

test('render_release_notes() maakt alleen http(s)-links', function () {
    $html = render_release_notes('[klik](javascript:alert(1)) en [x](https://a.nl/"onmouseover="alert(1))');
    assert_not_contains('href="javascript', $html);
    assert_not_contains('"onmouseover', $html);
});

test('render_release_notes() zonder tekst', function () {
    assert_contains('Geen releasenotes', render_release_notes(''));
});

test('update_is_newer() vergelijkt versies', function () {
    assert_true(update_is_newer('999.0.0'));
    assert_false(update_is_newer(APP_VERSION));
    assert_false(update_is_newer('0.0.1'));
});

test('updater_url_is_https()', function () {
    assert_true(updater_url_is_https('https://api.github.com/x'));
    assert_true(updater_url_is_https('HTTPS://codeload.github.com/x'));
    assert_false(updater_url_is_https('http://api.github.com/x'));
    assert_false(updater_url_is_https('file:///etc/passwd'));
    assert_false(updater_url_is_https('//api.github.com'));
});

test('updater_download() weigert niet-https', function () {
    assert_false(updater_download('http://example.org/x.zip', sys_get_temp_dir() . '/sv-test-niet-aanmaken.zip'));
    assert_false(is_file(sys_get_temp_dir() . '/sv-test-niet-aanmaken.zip'));
});

test('update_should_preserve() beschermt site-specifieke paden', function () {
    foreach (['config.local.php', 'assets/uploads', 'assets/uploads/foto.jpg', 'assets\\uploads\\a\\b.pdf',
              'backups/x.zip', 'includes/cache/opkomsten.json', 'install.php', '.git/config', '.claude/settings.json'] as $path) {
        assert_true(update_should_preserve($path), $path);
    }
    foreach (['config.php', 'index.php', 'assets/uploadsx/a', 'assets/css/style.css', 'includes/functions.php', 'sql/install.sql'] as $path) {
        assert_false(update_should_preserve($path), $path);
    }
});

test('copy_update_files() en find_extracted_root() slaan UPDATE_PRESERVE over', function () {
    $tmp = sys_get_temp_dir() . '/sv-test-update-' . getmypid();
    remove_directory($tmp);
    try {
        $src = $tmp . '/extracted/owner-repo-abc123';
        foreach (['index.php' => 'nieuw', 'config.local.php' => 'NIET', 'assets/uploads/x.jpg' => 'NIET',
                  'includes/functions.php' => 'nieuw', 'includes/cache/a.json' => 'NIET'] as $file => $content) {
            @mkdir(dirname($src . '/' . $file), 0755, true);
            file_put_contents($src . '/' . $file, $content);
        }
        mkdir($tmp . '/site');
        file_put_contents($tmp . '/site/config.local.php', 'eigen');

        $root = find_extracted_root($tmp . '/extracted');
        assert_same($src, $root);

        $copied = [];
        copy_update_files($root, $tmp . '/site', '', $copied);
        sort($copied);
        assert_same(['includes/functions.php', 'index.php'], $copied);
        assert_same('eigen', file_get_contents($tmp . '/site/config.local.php'));
        assert_false(is_dir($tmp . '/site/assets/uploads'));
        assert_false(is_dir($tmp . '/site/includes/cache'));
    } finally {
        remove_directory($tmp);
    }
    assert_false(is_dir($tmp), 'remove_directory() ruimt alles op');
});

test('migratiebestanden hebben een uniek, oplopend nummer', function () {
    $numbers = [];
    foreach (list_migration_files() as $path) {
        $name = basename($path);
        assert_matches('/^\d{4}_[a-z0-9_]+\.(sql|php)$/', $name, 'bestandsnaam');
        $number = substr($name, 0, 4);
        if (isset($numbers[$number])) {
            fail("Migratienummer {$number} komt twee keer voor ({$numbers[$number]} en {$name})");
        }
        $numbers[$number] = $name;
    }
    assert_true(count($numbers) > 0, 'er zijn migraties');
});

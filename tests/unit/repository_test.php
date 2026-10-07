<?php
/**
 * Controles op de regels uit CLAUDE.md die je anders makkelijk vergeet:
 * PHP-syntax (ook op de oudste ondersteunde PHP-versie), afscherming via
 * .htaccess, de Content-Security-Policy en lijsten die gelijk moeten lopen.
 */
require_once TEST_ROOT . '/includes/checks.php';

/** Alle PHP-bestanden in de repository (relatief), zonder verborgen mappen. */
function repo_php_files(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(TEST_ROOT, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $item): bool {
            return $item->getFilename()[0] !== '.' && !($item->isDir() && $item->getFilename() === 'uploads');
        }
    ));
    foreach ($iterator as $item) {
        if ($item->isFile() && $item->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', substr($item->getPathname(), strlen(TEST_ROOT) + 1));
        }
    }
    sort($files);
    return $files;
}

function repo_read(string $relative): string
{
    $content = file_get_contents(TEST_ROOT . '/' . $relative);
    if ($content === false) {
        fail("Kan {$relative} niet lezen");
    }
    return $content;
}

test('alle PHP-bestanden zijn syntactisch geldig op PHP ' . PHP_VERSION, function () {
    $errors = [];
    foreach (repo_php_files() as $file) {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(TEST_ROOT . '/' . $file) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $errors[] = $file . ': ' . implode(' ', $output);
        }
    }
    if ($errors) {
        fail(implode("\n        ", $errors));
    }
});

test('geen inline scripts of event-handlers (CSP)', function () {
    $problems = [];
    foreach (repo_php_files() as $file) {
        if (strpos($file, 'tests/') === 0) {
            continue;
        }
        $content = repo_read($file);
        // JSON-LD is data, geen script, en mag wel inline.
        if (preg_match_all('#<script\b(?![^>]*\bsrc=)(?![^>]*type="application/ld\+json")[^>]*>#i', $content, $m)) {
            $problems[] = "{$file}: inline <script>";
        }
        if (preg_match_all('#<[a-z][^>]*\son[a-z]+\s*=\s*["\']#i', $content, $m)) {
            $problems[] = "{$file}: event-handler " . implode(', ', $m[0]);
        }
    }
    if ($problems) {
        fail(implode("\n        ", $problems));
    }
});

test('sessies alleen via start_secure_session()', function () {
    foreach (repo_php_files() as $file) {
        if ($file === 'includes/functions.php' || strpos($file, 'tests/') === 0) {
            continue;
        }
        if (preg_match('/(?<![\w>])session_start\s*\(/', preg_replace('#//.*|/\*.*?\*/#s', '', repo_read($file)))) {
            fail("{$file} roept session_start() rechtstreeks aan");
        }
    }
});

/** Mappen uit de rewrite-regel in de root-.htaccess (^(includes|...)). */
function repo_rewrite_blocked_dirs(): array
{
    if (!preg_match('/RewriteRule \^\(([a-z\/|]+)\)\(\/\|\$\) - \[F\]/', repo_read('.htaccess'), $m)) {
        fail('Rewrite-regel voor afgeschermde mappen niet gevonden in .htaccess');
    }
    return explode('|', $m[1]);
}

test('elke afgeschermde map heeft een .htaccess met het dubbele weigerblok', function () {
    $dirs = repo_rewrite_blocked_dirs();
    foreach ($dirs as $dir) {
        $htaccess = repo_read($dir . '/.htaccess');
        assert_matches('#<IfModule mod_authz_core\.c>\s*Require all denied\s*</IfModule>#', $htaccess, "{$dir}/.htaccess (Apache 2.4)");
        assert_matches('#<IfModule !mod_authz_core\.c>\s*Order allow,deny\s*Deny from all\s*</IfModule>#', $htaccess, "{$dir}/.htaccess (Apache 2.2)");
        assert_true(in_array($dir . '/.htaccess', CHECK_HTACCESS_FILES, true), "{$dir}/.htaccess staat in CHECK_HTACCESS_FILES");
    }
});

test('CHECK_HTACCESS_FILES bestaan allemaal in de repository', function () {
    foreach (CHECK_HTACCESS_FILES as $file) {
        assert_true(is_file(TEST_ROOT . '/' . $file), $file);
    }
});

test('nginx-voorbeeld in INSTALL.md loopt gelijk met .htaccess', function () {
    $install = repo_read('INSTALL.md');
    if (!preg_match('#location ~ \^/\(([a-z/|]+)\)\(/\|\$\) \{ return 404; \}#', $install, $m)) {
        fail('nginx-regel voor afgeschermde mappen niet gevonden in INSTALL.md');
    }
    assert_same(repo_rewrite_blocked_dirs(), explode('|', $m[1]), 'afgeschermde mappen');

    if (!preg_match('/<FilesMatch "\(\?i\)\(\\\\\.\(([a-z0-9|]+)\)\|~\)\$">/', repo_read('.htaccess'), $apache)
        || !preg_match('/location ~\* \(\\\\\.\(([a-z0-9|]+)\)\|~\)\$ \{ return 404; \}/', $install, $nginx)) {
        fail('Lijst met afgeschermde extensies niet gevonden in .htaccess of INSTALL.md');
    }
    assert_same($apache[1], $nginx[1], 'afgeschermde extensies');
});

test('PAGE_RESERVED_SLUGS bevat elke map in de projectroot', function () {
    foreach (scandir(TEST_ROOT) as $entry) {
        if ($entry[0] === '.' || !is_dir(TEST_ROOT . '/' . $entry)) {
            continue;
        }
        if (slugify($entry) === $entry) {
            assert_true(in_array($entry, PAGE_RESERVED_SLUGS, true), "map {$entry}/ ontbreekt in PAGE_RESERVED_SLUGS");
        }
    }
});

test('uploads-allowlist in .htaccess en de upload-extensies in de code', function () {
    if (!preg_match('/<FilesMatch "(\^\[\^\.\]\+[^"]+)">/', repo_read('assets/uploads/.htaccess'), $allow)) {
        fail('Allowlist (FilesMatch) niet gevonden in assets/uploads/.htaccess');
    }
    $allowlist = '/' . str_replace('/', '\/', $allow[1]) . '/';
    $found = 0;
    foreach (repo_php_files() as $file) {
        if (preg_match_all('/handle_upload\([^,]+,\s*\[([^\]]*)\]/', repo_read($file), $m)) {
            foreach ($m[1] as $list) {
                foreach (array_map(function ($s) { return trim($s, " '\""); }, explode(',', $list)) as $ext) {
                    $found++;
                    assert_matches($allowlist, 'abc123.' . $ext, "{$file}: .{$ext} wordt niet geserveerd door assets/uploads/.htaccess");
                }
            }
        }
    }
    assert_true($found > 0, 'handle_upload()-aanroepen gevonden');
});

test('install.sql maakt alle CHECK_EXPECTED_TABLES aan', function () {
    $sql = repo_read('sql/install.sql');
    foreach (CHECK_EXPECTED_TABLES as $table) {
        assert_matches('/CREATE TABLE IF NOT EXISTS `?' . $table . '`?\s*\(/', $sql, "tabel {$table}");
    }
});

test('testbestanden zijn niet via de webserver bereikbaar en gaan niet mee in een release', function () {
    assert_true(in_array('tests', repo_rewrite_blocked_dirs(), true), 'tests/ in de rewrite-regel');
    assert_matches('#^/?tests/?\s+export-ignore#m', repo_read('.gitattributes'), 'tests/ in .gitattributes');
});

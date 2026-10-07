<?php
/**
 * Minimale testbibliotheek (geen PHPUnit/Composer nodig, werkt op PHP 7.4+).
 *
 * Een testbestand registreert tests met test('naam', function () { ... }) en
 * gebruikt de assert_*()-functies hieronder. tests/run.php draait elk
 * testbestand in een eigen PHP-proces, zodat constanten, statische caches
 * (bv. get_all_settings()) en sessies per bestand opnieuw beginnen.
 */

class TestFailure extends Exception {}
class TestSkipped extends Exception {}

const TEST_ROOT = __DIR__ . '/..';

/** @var array<int, array{0: string, 1: callable}> */
$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function skip(string $reason): void
{
    throw new TestSkipped($reason);
}

function fail(string $message): void
{
    throw new TestFailure($message);
}

function test_export($value): string
{
    if (is_string($value)) {
        $value = strlen($value) > 300 ? substr($value, 0, 300) . '…' : $value;
        return '"' . addcslashes($value, "\0..\37") . '"';
    }
    return str_replace("\n", ' ', var_export($value, true));
}

function assert_true($value, string $message = ''): void
{
    if ($value !== true) {
        fail(($message !== '' ? $message . ': ' : '') . 'verwacht true, kreeg ' . test_export($value));
    }
}

function assert_false($value, string $message = ''): void
{
    if ($value !== false) {
        fail(($message !== '' ? $message . ': ' : '') . 'verwacht false, kreeg ' . test_export($value));
    }
}

function assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fail(($message !== '' ? $message . ': ' : '') . 'verwacht ' . test_export($expected) . ', kreeg ' . test_export($actual));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (strpos($haystack, $needle) === false) {
        fail(($message !== '' ? $message . ': ' : '') . test_export($needle) . ' niet gevonden in ' . test_export($haystack));
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (stripos($haystack, $needle) !== false) {
        fail(($message !== '' ? $message . ': ' : '') . test_export($needle) . ' onverwacht gevonden in ' . test_export($haystack));
    }
}

function assert_matches(string $pattern, string $subject, string $message = ''): void
{
    if (!preg_match($pattern, $subject)) {
        fail(($message !== '' ? $message . ': ' : '') . test_export($subject) . ' voldoet niet aan ' . $pattern);
    }
}

/* ------------------------------------------------------------------ *
 * Testomgeving: constanten vóór config.php vastleggen
 * ------------------------------------------------------------------ */

/** Databasegegevens voor de db-suite (en eventueel http). Altijd een aparte testdatabase. */
function test_db_config(): array
{
    $pass = getenv('TEST_DB_PASS');
    $config = [
        'host' => getenv('TEST_DB_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'),
        'port' => getenv('TEST_DB_PORT') ?: (getenv('DB_PORT') ?: '3306'),
        'user' => getenv('TEST_DB_USER') ?: 'root',
        'pass' => $pass !== false ? $pass : 'root',
        'name' => getenv('TEST_DB_NAME') ?: 'scouting_test',
    ];
    // Deze database wordt bij elk testbestand gewist: nooit per ongeluk de echte.
    if (!preg_match('/^[A-Za-z0-9_]+_test$/', $config['name'])) {
        throw new RuntimeException('TEST_DB_NAME moet eindigen op "_test" (kreeg "' . $config['name'] . '").');
    }
    return $config;
}

function test_backup_dir(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sv-tests-backups-' . getmypid();
}

/**
 * Wordt in elk testproces vóór het testbestand aangeroepen: zet de
 * constanten die config.php anders zelf zou bepalen, zodat tests nooit de
 * echte database of back-upmap aanraken.
 */
function test_bootstrap(string $suite): void
{
    $db = test_db_config();
    define('DB_HOST', $db['host']);
    define('DB_PORT', $db['port']);
    define('DB_NAME', $db['name']);
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('APP_DEBUG', true);
    define('BACKUP_DIR', test_backup_dir());
    register_shutdown_function('test_remove_tree', test_backup_dir());

    // PHP-waarschuwingen/notices/deprecations laten een test falen.
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // onderdrukt met @
        }
        // Een lokale config.local.php definieert dezelfde constanten nog eens;
        // de testwaarden hierboven gaan dan bewust voor.
        if (basename($file) === 'config.local.php' && strpos($message, 'already defined') !== false) {
            return true;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    if ($suite === 'db') {
        test_db_reset();
    }
}

function test_remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/* ------------------------------------------------------------------ *
 * Database-hulpfuncties (db-suite)
 * ------------------------------------------------------------------ */

function test_pdo(bool $withDatabase = true): PDO
{
    $c = test_db_config();
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';charset=utf8mb4'
        . ($withDatabase ? ';dbname=' . $c['name'] : '');
    return new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Leegt de testdatabase en importeert sql/install.sql, zoals install.php dat doet. */
function test_db_reset(): void
{
    test_db_recreate_empty();
    test_db_import(file_get_contents(TEST_ROOT . '/sql/install.sql'));
}

function test_db_recreate_empty(): void
{
    $name = test_db_config()['name'];
    $pdo = test_pdo(false);
    $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
    $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Voert een SQL-bestand met meerdere statements uit via een eigen,
 * wegwerp-verbinding (net als install.php met PDO::exec()).
 */
function test_db_import(string $sql): void
{
    test_pdo()->exec($sql);
}

/** Aantal rijen per tabel in de testdatabase. */
function test_db_table_counts(PDO $pdo): array
{
    $counts = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    ksort($counts);
    return $counts;
}

/* ------------------------------------------------------------------ *
 * HTTP-hulpfuncties (http-suite), zonder curl: alleen stream wrappers
 * ------------------------------------------------------------------ */

function test_base_url(): string
{
    return rtrim((string) getenv('TEST_BASE_URL'), '/');
}

$GLOBALS['__cookies'] = [];

/**
 * Doet een HTTP-verzoek naar TEST_BASE_URL . '/' . $path, zonder redirects
 * te volgen, met een eenvoudige cookie-jar (voor de sessiecookie).
 *
 * @return array{status: int, headers: array<string, string[]>, body: string}
 */
function http_request(string $method, string $path, ?array $form = null): array
{
    $headers = "User-Agent: ScoutingVoerendaal-Tests/1.0\r\n";
    if ($GLOBALS['__cookies']) {
        $pairs = [];
        foreach ($GLOBALS['__cookies'] as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $headers .= 'Cookie: ' . implode('; ', $pairs) . "\r\n";
    }
    $content = null;
    if ($form !== null) {
        $content = http_build_query($form);
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $context = stream_context_create(['http' => array_filter([
        'method' => $method,
        'header' => $headers,
        'content' => $content,
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 60,
    ], function ($v) { return $v !== null; })]);

    $http_response_header = [];
    $body = @file_get_contents(test_base_url() . '/' . ltrim($path, '/'), false, $context);
    $rawHeaders = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : $http_response_header;
    if ($body === false && !$rawHeaders) {
        fail("Geen antwoord van {$method} {$path} (draait de webserver op " . test_base_url() . '?)');
    }

    $status = 0;
    $parsed = [];
    foreach ($rawHeaders as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            $parsed = [];
        } elseif (strpos($line, ':') !== false) {
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))][] = trim($value);
        }
    }
    foreach ($parsed['set-cookie'] ?? [] as $cookie) {
        [$pair] = explode(';', $cookie, 2);
        [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
        if ($value === '' || $value === 'deleted') {
            unset($GLOBALS['__cookies'][trim($name)]);
        } else {
            $GLOBALS['__cookies'][trim($name)] = trim($value);
        }
    }
    return ['status' => $status, 'headers' => $parsed, 'body' => (string) $body];
}

function http_get(string $path): array
{
    return http_request('GET', $path);
}

function http_post(string $path, array $form): array
{
    return http_request('POST', $path, $form);
}

function http_header(array $response, string $name): string
{
    return $response['headers'][strtolower($name)][0] ?? '';
}

/** Haalt het CSRF-token uit een formulier in de HTML. */
function http_csrf_token(array $response): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $m)) {
        fail('Geen CSRF-token gevonden in het antwoord.');
    }
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

/** Faalt als de HTML een zichtbare PHP-fout/waarschuwing bevat (APP_DEBUG staat aan in Docker). */
function assert_no_php_errors(array $response, string $what): void
{
    if (preg_match('#\b(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\b(</b>)?:.{0,500}? on line (<b>)?\d+#s', $response['body'], $m)) {
        fail("PHP-fout in {$what}: " . test_export(strip_tags($m[0])));
    }
}

function assert_status(int $expected, array $response, string $what): void
{
    if ($response['status'] !== $expected) {
        fail("{$what}: verwacht HTTP {$expected}, kreeg {$response['status']}");
    }
}

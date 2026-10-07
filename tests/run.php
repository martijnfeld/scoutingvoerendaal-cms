<?php
/**
 * Testrunner — alleen voor ontwikkeling en GitHub Actions, nooit via de
 * webserver (zie tests/.htaccess; een web-aanroep stopt hieronder meteen).
 *
 * Suites (mappen onder tests/):
 *   unit  — pure PHP, geen database of webserver nodig
 *   db    — tegen een aparte MySQL/MariaDB-testdatabase (TEST_DB_*, wordt gewist!)
 *   http  — tegen een draaiende site (TEST_BASE_URL, bv. de Docker-omgeving)
 *
 * Gebruik:
 *   php tests/run.php                  alle beschikbare suites
 *   php tests/run.php unit db          alleen deze suites
 *   php tests/run.php --filter=slug    alleen tests waarvan de naam "slug" bevat
 *
 * Lokaal in Docker (alle suites):
 *   docker compose exec -e XDEBUG_MODE=off web php tests/run.php
 *
 * Omgevingsvariabelen:
 *   TEST_DB_HOST/PORT/USER/PASS/NAME  testdatabase (standaard: DB_HOST, root/root, scouting_test)
 *   TEST_BASE_URL                     basis-URL voor de http-suite (bv. http://localhost)
 *   TEST_ADMIN_USER/TEST_ADMIN_PASS   beheerder voor de http-suite (anders via install.php aangemaakt)
 *   TEST_STRICT=1                     overgeslagen suites/tests tellen als fout (gebruikt in CI)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/lib.php';

const TEST_SUITES = ['unit', 'db', 'http'];

$suites = [];
$filter = '';
$file = null;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--filter=') === 0) {
        $filter = substr($arg, 9);
    } elseif (strpos($arg, '--file=') === 0) {
        $file = substr($arg, 7);
    } elseif (in_array($arg, TEST_SUITES, true)) {
        $suites[] = $arg;
    } else {
        fwrite(STDERR, "Onbekend argument: {$arg}\n");
        exit(2);
    }
}

$strict = getenv('TEST_STRICT') === '1';

if ($file !== null) {
    exit(test_run_file($file, $filter, $strict));
}

/** Waarom een suite hier niet kan draaien, of null als hij wel kan. */
function test_suite_unavailable(string $suite): ?string
{
    if ($suite === 'db') {
        if (!extension_loaded('pdo_mysql')) {
            return 'PHP-extensie pdo_mysql ontbreekt';
        }
        if (!getenv('TEST_DB_HOST') && !getenv('DB_HOST')) {
            return 'geen database ingesteld (zet TEST_DB_HOST, of draai de tests in de Docker-container)';
        }
    }
    if ($suite === 'http' && !getenv('TEST_BASE_URL')) {
        return 'geen site ingesteld (zet TEST_BASE_URL, of draai de tests in de Docker-container)';
    }
    return null;
}

/**
 * Draait één testbestand (in dit proces) en geeft de exitcode terug. De
 * laatste regel van de uitvoer is "@@RESULT {json}" voor de hoofdrunner.
 */
function test_run_file(string $file, string $filter, bool $strict): int
{
    $suite = basename(dirname($file));
    $counts = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
    $lines = [];

    // Uitvoer bufferen: zo zijn er nog geen "headers verstuurd" en kunnen
    // tests sessies starten (session_start() weigert anders).
    ob_start();
    try {
        test_bootstrap($suite);
        require $file;
    } catch (Throwable $e) {
        $GLOBALS['__tests'] = [];
        $counts['failed']++;
        $lines[] = '  FAIL  (laden van het bestand)';
        $lines[] = '        ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }

    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        if ($filter !== '' && stripos($name, $filter) === false) {
            continue;
        }
        try {
            $fn();
            $counts['passed']++;
            $lines[] = "  ok    {$name}";
        } catch (TestSkipped $e) {
            $counts[$strict ? 'failed' : 'skipped']++;
            $lines[] = ($strict ? '  FAIL  ' : '  skip  ') . "{$name} (overgeslagen: {$e->getMessage()})";
        } catch (Throwable $e) {
            $counts['failed']++;
            $lines[] = "  FAIL  {$name}";
            $where = $e instanceof TestFailure ? '' : ' (' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            $lines[] = '        ' . $e->getMessage() . $where;
        }
    }
    $stray = ob_get_clean();

    if (trim((string) $stray) !== '') {
        $lines[] = '        [uitvoer] ' . str_replace("\n", "\n        ", trim($stray));
    }
    echo implode("\n", $lines), "\n";
    echo '@@RESULT ', json_encode($counts), "\n";
    return $counts['failed'] > 0 ? 1 : 0;
}

/* ------------------------------------------------------------------ *
 * Hoofdrunner: elk testbestand in een eigen PHP-proces
 * ------------------------------------------------------------------ */

$total = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
$started = microtime(true);
$explicit = $suites !== [];

foreach ($explicit ? $suites : TEST_SUITES as $suite) {
    $reason = test_suite_unavailable($suite);
    if ($reason !== null) {
        echo "\n[{$suite}] overgeslagen: {$reason}\n";
        $total[$strict ? 'failed' : 'skipped']++;
        continue;
    }

    $files = glob(__DIR__ . '/' . $suite . '/*_test.php') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $testFile) {
        echo "\n[{$suite}] " . basename($testFile) . "\n";
        $command = [PHP_BINARY, __FILE__, '--file=' . $testFile];
        if ($filter !== '') {
            $command[] = '--filter=' . $filter;
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (!is_resource($process)) {
            echo "  FAIL  kon geen PHP-proces starten\n";
            $total['failed']++;
            continue;
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        if (preg_match('/^@@RESULT (\{.*\})\s*$/m', $output, $m)) {
            $counts = json_decode($m[1], true);
            echo rtrim(str_replace($m[0], '', $output)), "\n";
            foreach ($total as $key => $_) {
                $total[$key] += (int) ($counts[$key] ?? 0);
            }
        } else {
            // Fatale fout of die() (bv. geen databaseverbinding) vóór het einde.
            echo rtrim($output), "\n  FAIL  testproces stopte onverwacht (exitcode {$exitCode})\n";
            $total['failed']++;
        }
    }
}

printf(
    "\n%d geslaagd, %d mislukt, %d overgeslagen (%.1fs)\n",
    $total['passed'], $total['failed'], $total['skipped'], microtime(true) - $started
);
exit($total['failed'] > 0 ? 1 : 0);

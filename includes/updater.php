<?php
/**
 * Update-functionaliteit voor Beheerpaneel → Updates: controleert de
 * nieuwste release op GitHub, toont de releasenotes, en kan een update
 * volledig geautomatiseerd installeren (back-up -> download -> bestanden
 * bijwerken -> database-/bestandsmigraties).
 *
 * LET OP bij nieuwe features die site-specifieke bestanden toevoegen buiten
 * assets/uploads/backups/includes/cache: breid UPDATE_PRESERVE hieronder uit,
 * anders overschrijft een update die map alsnog. Zie ook CLAUDE.md.
 * ('backups' blijft in UPDATE_PRESERVE staan: daar staan back-ups van
 * oudere installaties, en het is de terugvalmap als BACKUP_DIR buiten de
 * webroot niet bruikbaar is — zie includes/backup.php.)
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/backup.php';

/** Paden (relatief aan de projectroot) die een update nooit overschrijft of aanraakt. */
const UPDATE_PRESERVE = [
    'config.local.php',
    'assets/uploads',
    'backups',
    'includes/cache',
    'install.php',
    '.git',
    '.claude',
];

const UPDATE_CHECK_CACHE_TTL = 3600; // 1 uur

/* ------------------------------------------------------------------ *
 * HTTP-hulpfuncties (GitHub API + bestandsdownload)
 * ------------------------------------------------------------------ */

const UPDATER_MAX_REDIRECTS = 5;

function updater_url_is_https(string $url): bool
{
    return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
}

/**
 * Gedeelde curl-opties: alleen HTTPS (ook bij redirects), certificaat en
 * hostnaam altijd controleren, en een maximum aantal redirects — zodat een
 * update nooit via onversleuteld HTTP of een vervalst certificaat binnenkomt.
 */
function updater_curl_options(int $timeout, int $connectTimeout): array
{
    return [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => UPDATER_MAX_REDIRECTS,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_USERAGENT => 'ScoutingVoerendaal-Updater/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ];
}

/**
 * Terugval zonder curl: haalt $url op via file_get_contents(). Redirects
 * volgen we zelf (in plaats van follow_location), zodat we elke stap kunnen
 * weigeren als die niet naar HTTPS gaat. Geeft de body terug bij een
 * 2xx-antwoord, anders null.
 */
function updater_stream_get(string $url, int $timeout): ?string
{
    for ($i = 0; $i <= UPDATER_MAX_REDIRECTS; $i++) {
        if (!updater_url_is_https($url)) {
            return null;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'timeout' => $timeout,
                'header' => "User-Agent: ScoutingVoerendaal-Updater/1.0\r\nAccept: application/vnd.github+json\r\n",
                'follow_location' => 0, 'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $http_response_header = [];
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        $status = 0;
        $location = null;
        foreach ($http_response_header as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
                $status = (int) $m[1];
            } elseif (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        if ($status >= 200 && $status < 300) {
            return $body;
        }
        if ($status >= 300 && $status < 400 && $location !== null && $location !== '') {
            if (strpos($location, '//') === 0) {
                $location = 'https:' . $location;
            } elseif ($location[0] === '/') {
                $location = 'https://' . parse_url($url, PHP_URL_HOST) . $location;
            }
            $url = $location;
            continue;
        }
        return null;
    }
    return null;
}

function updater_http_get_json(string $url): ?array
{
    if (!updater_url_is_https($url)) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true] + updater_curl_options(15, 10));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            return null;
        }
    } else {
        $body = updater_stream_get($url, 15);
        if ($body === null) {
            return null;
        }
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function updater_download(string $url, string $destination): bool
{
    if (!updater_url_is_https($url)) {
        return false;
    }

    if (function_exists('curl_init')) {
        $fh = fopen($destination, 'wb');
        if ($fh === false) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh] + updater_curl_options(180, 15));
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);
        return $ok !== false && $status >= 200 && $status < 300;
    }

    $body = updater_stream_get($url, 180);
    if ($body === null) {
        return false;
    }
    return file_put_contents($destination, $body, LOCK_EX) !== false;
}

/* ------------------------------------------------------------------ *
 * Nieuwste release ophalen + cachen
 * ------------------------------------------------------------------ */

function update_cache_dir(): string
{
    return __DIR__ . '/cache';
}

function update_cache_file(): string
{
    return update_cache_dir() . '/update_check.json';
}

function update_lock_file(): string
{
    return update_cache_dir() . '/update_check.lock';
}

function update_read_cache(): ?array
{
    $file = update_cache_file();
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    $decoded = $raw === false ? null : json_decode($raw, true);
    return is_array($decoded) && isset($decoded['checked_at']) ? $decoded : null;
}

function update_write_cache(array $data): void
{
    $dir = update_cache_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(update_cache_file(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * Haalt info over de nieuwste GitHub-release op (1 uur gecached, met
 * flock() zodat gelijktijdige bezoekers niet allemaal de GitHub API
 * aanroepen). Geef $forceRefresh mee om de cache te negeren.
 */
function fetch_latest_release(bool $forceRefresh = false): array
{
    $cached = update_read_cache();
    if (!$forceRefresh && $cached !== null && (time() - $cached['checked_at']) < UPDATE_CHECK_CACHE_TTL) {
        return $cached;
    }

    if (GITHUB_REPO === 'jouw-gebruikersnaam/jouw-repo-naam') {
        $result = [
            'checked_at' => time(), 'ok' => false, 'release' => null,
            'error' => 'Er is nog geen GitHub-repository ingesteld. Zet GITHUB_REPO in config.php (of config.local.php) op "eigenaar/repo-naam".',
        ];
        update_write_cache($result);
        return $result;
    }

    $dir = update_cache_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $lockHandle = fopen(update_lock_file(), 'c');
    if ($lockHandle !== false && flock($lockHandle, LOCK_EX | LOCK_NB)) {
        try {
            $data = updater_http_get_json('https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest');
            if ($data === null || empty($data['tag_name'])) {
                $result = [
                    'checked_at' => time(), 'ok' => false, 'release' => null,
                    'error' => 'Kan de nieuwste release niet ophalen bij GitHub. Controleer GITHUB_REPO en of deze server uitgaande HTTPS-verbindingen toestaat.',
                ];
            } else {
                $result = ['checked_at' => time(), 'ok' => true, 'error' => null, 'release' => [
                    'tag' => $data['tag_name'],
                    'version' => ltrim($data['tag_name'], 'vV'),
                    'name' => $data['name'] ?? $data['tag_name'],
                    'body' => (string) ($data['body'] ?? ''),
                    'html_url' => $data['html_url'] ?? '',
                    'zipball_url' => $data['zipball_url'] ?? '',
                    'published_at' => $data['published_at'] ?? null,
                ]];
            }
            update_write_cache($result);
            return $result;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
    if ($lockHandle !== false) {
        fclose($lockHandle);
    }

    // Een ander verzoek is al bezig met verversen: geef bekende data terug.
    return $cached ?? ['checked_at' => time(), 'ok' => false, 'release' => null, 'error' => 'Updatecheck loopt al, probeer het zo nogmaals.'];
}

function update_is_newer(string $latestVersion): bool
{
    return version_compare($latestVersion, APP_VERSION, '>');
}

/* ------------------------------------------------------------------ *
 * Releasenotes: minimale, veilige markdown-naar-HTML-weergave
 * (alles wordt eerst HTML-geëscaped; er komt alleen markup bij die wij
 * hier zelf toevoegen, dus de externe releasetekst kan geen HTML/JS
 * insmuggelen).
 * ------------------------------------------------------------------ */

function render_release_notes(string $markdown): string
{
    $lines = explode("\n", e($markdown));
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = rtrim($line);
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $level = min(5, strlen($m[1]) + 2);
            $html .= "<h{$level}>" . update_inline_markdown($m[2]) . "</h{$level}>\n";
        } elseif (preg_match('/^[-*]\s+(.*)$/', $trimmed, $m)) {
            if (!$inList) { $html .= "<ul>\n"; $inList = true; }
            $html .= '<li>' . update_inline_markdown($m[1]) . "</li>\n";
        } elseif ($trimmed === '') {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
        } else {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $html .= '<p>' . update_inline_markdown($trimmed) . "</p>\n";
        }
    }
    if ($inList) {
        $html .= "</ul>\n";
    }
    return $html !== '' ? $html : '<p class="muted">Geen releasenotes opgegeven.</p>';
}

/** $text is hier al HTML-geëscaped; voegt alleen door onszelf gegenereerde tags toe. */
function update_inline_markdown(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);
    $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function (array $m): string {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $text);
    return $text;
}

/* ------------------------------------------------------------------ *
 * Migraties (sql/migrations/*.sql en *.php)
 * ------------------------------------------------------------------ */

function migrations_dir(): string
{
    return dirname(__DIR__) . '/sql/migrations';
}

function ensure_schema_migrations_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            filename VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_filename (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function list_applied_migrations(): array
{
    ensure_schema_migrations_table();
    return db()->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
}

function list_migration_files(): array
{
    $dir = migrations_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = array_merge(glob($dir . '/*.sql') ?: [], glob($dir . '/*.php') ?: []);
    sort($files, SORT_STRING);
    return $files;
}

function mark_migration_applied(string $filename): void
{
    $stmt = db()->prepare(
        'INSERT INTO schema_migrations (filename) VALUES (:f) ON DUPLICATE KEY UPDATE filename = filename'
    );
    $stmt->execute(['f' => $filename]);
}

/**
 * Markeert alle huidige migratiebestanden als al toegepast. Wordt gebruikt
 * door install.php bij een verse installatie: die importeert het complete
 * schema uit sql/install.sql, dus migraties die op dat moment al bestaan
 * hoeven niet nogmaals uitgevoerd te worden.
 */
function mark_all_migrations_applied(): void
{
    ensure_schema_migrations_table();
    foreach (list_migration_files() as $path) {
        mark_migration_applied(basename($path));
    }
}

/**
 * Voert alle nog niet toegepaste migraties uit sql/migrations/ uit, op
 * bestandsnaam gesorteerd — geef migraties dus een oplopend nummer (bv.
 * "0002_...."). .sql-bestanden worden als schema-wijziging uitgevoerd,
 * .php-bestanden voor overige migraties (bv. geüploade bestanden
 * verplaatsen/hernoemen, data omzetten). Stopt bij de eerste fout, zodat
 * latere migraties niet verder bouwen op een half toegepaste stap — er is
 * dan wel al een back-up (zie perform_full_update()).
 */
function run_pending_migrations(): array
{
    $applied = list_applied_migrations();
    $result = ['applied' => [], 'error' => null];

    foreach (list_migration_files() as $path) {
        $filename = basename($path);
        if (in_array($filename, $applied, true)) {
            continue;
        }
        try {
            if (substr($filename, -4) === '.php') {
                (function (string $__migrationFile): void {
                    require $__migrationFile;
                })($path);
            } else {
                $sql = file_get_contents($path);
                if ($sql === false) {
                    throw new RuntimeException('Kon migratiebestand niet lezen.');
                }
                db()->exec($sql);
            }
            mark_migration_applied($filename);
            $result['applied'][] = $filename;
        } catch (Throwable $e) {
            $result['error'] = "Migratie {$filename} is mislukt: " . $e->getMessage();
            break;
        }
    }

    return $result;
}

/* ------------------------------------------------------------------ *
 * Config-migratie voor bestaande installaties (vóór deze updatefunctie)
 * ------------------------------------------------------------------ */

/**
 * Bestaande installaties (van vóór deze updatefunctie) hebben hun echte
 * databasegegevens rechtstreeks in config.php staan. Omdat config.php bij
 * een update overschreven wordt met de generieke versie uit de repository,
 * zetten we die gegevens hier eenmalig over naar config.local.php (dat een
 * update nooit aanraakt) — vóórdat er ook maar één bestand wordt bijgewerkt.
 */
function ensure_config_local_migrated(): void
{
    $path = dirname(__DIR__) . '/config.local.php';
    if (is_file($path)) {
        return;
    }
    if (getenv('DB_HOST')) {
        return; // Docker: omgevingsvariabelen regelen dit al.
    }
    if (!defined('DB_NAME') || DB_NAME === 'vul_hier_je_database_naam_in') {
        return; // Nog niet geconfigureerd, er is niets te bewaren.
    }

    $lines = [
        '<?php',
        '// Automatisch aangemaakt bij de eerste update, met de databasegegevens die al in gebruik waren.',
        "define('DB_HOST', " . var_export(DB_HOST, true) . ');',
        "define('DB_PORT', " . var_export(DB_PORT, true) . ');',
        "define('DB_NAME', " . var_export(DB_NAME, true) . ');',
        "define('DB_USER', " . var_export(DB_USER, true) . ');',
        "define('DB_PASS', " . var_export(DB_PASS, true) . ');',
    ];
    if (defined('BACKUP_CRON_KEY') && BACKUP_CRON_KEY !== 'wijzig_deze_geheime_sleutel') {
        $lines[] = "define('BACKUP_CRON_KEY', " . var_export(BACKUP_CRON_KEY, true) . ');';
    }
    if (defined('GITHUB_REPO')
        && !in_array(GITHUB_REPO, ['jouw-gebruikersnaam/jouw-repo-naam', 'martijnfeld/scoutingvoerendaal-cms'], true)) {
        $lines[] = "define('GITHUB_REPO', " . var_export(GITHUB_REPO, true) . ');';
    }
    @file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
}

/* ------------------------------------------------------------------ *
 * Update toepassen: back-up -> downloaden -> bestanden bijwerken -> migraties
 * ------------------------------------------------------------------ */

/**
 * Tijdelijke map voor het downloaden/uitpakken van een release: binnen de
 * actieve back-upmap (standaard buiten de webroot; bij terugval `backups/`,
 * dat via .htaccess is afgeschermd), zodat we geen extra beschrijfbare map
 * nodig hebben.
 */
function update_tmp_dir(): string
{
    return backup_dir() . DIRECTORY_SEPARATOR . '.update-tmp';
}

function remove_directory(string $dir): void
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

function update_should_preserve(string $relativePath): bool
{
    $normalized = str_replace('\\', '/', $relativePath);
    foreach (UPDATE_PRESERVE as $preserved) {
        if ($normalized === $preserved || strpos($normalized, $preserved . '/') === 0) {
            return true;
        }
    }
    return false;
}

/** GitHub-zipballs pakken uit in één submap (bv. "eigenaar-repo-<sha>/"); zoek die op. */
function find_extracted_root(string $extractDir): string
{
    $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
    if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
        return $extractDir . '/' . $entries[0];
    }
    return $extractDir;
}

/** Kopieert $sourceDir over $targetDir, met UPDATE_PRESERVE overgeslagen. */
function copy_update_files(string $sourceDir, string $targetDir, string $relativeBase, array &$copied): void
{
    foreach (scandir($sourceDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $relPath = $relativeBase === '' ? $entry : $relativeBase . '/' . $entry;
        if (update_should_preserve($relPath)) {
            continue;
        }

        $srcPath = $sourceDir . '/' . $entry;
        $dstPath = $targetDir . '/' . $entry;

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath) && !mkdir($dstPath, 0755, true) && !is_dir($dstPath)) {
                throw new RuntimeException("Kon map niet aanmaken: {$relPath}");
            }
            copy_update_files($srcPath, $dstPath, $relPath, $copied);
        } else {
            if (!copy($srcPath, $dstPath)) {
                throw new RuntimeException("Kon bestand niet bijwerken: {$relPath} (controleer schrijfrechten).");
            }
            $copied[] = $relPath;
        }
    }
}

/**
 * Voert de volledige update uit: verplichte back-up, release downloaden en
 * uitpakken, bestanden bijwerken (met UPDATE_PRESERVE overgeslagen) en
 * daarna eventuele nieuwe migraties draaien. Gooit een Exception zodra iets
 * niet lukt — de admin-pagina toont dat, en er staat in elk geval altijd
 * een verse back-up klaar om op terug te vallen.
 */
function perform_full_update(array $release): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('De PHP-extensie "zip" ontbreekt op deze server; automatisch bijwerken is niet mogelijk.');
    }
    if (empty($release['zipball_url'])) {
        throw new RuntimeException('Geen downloadlocatie voor deze release gevonden.');
    }

    @set_time_limit(0);

    ensure_config_local_migrated();
    ensure_schema_migrations_table();

    // Verplicht: pas als de back-up gelukt is, raken we bestanden aan.
    $backupResult = run_backup();

    $tmpDir = update_tmp_dir();
    remove_directory($tmpDir);
    if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('Kon de tijdelijke updatemap niet aanmaken (controleer schrijfrechten).');
    }

    $zipPath = $tmpDir . '/release.zip';
    $extractDir = $tmpDir . '/extracted';

    try {
        if (!updater_download($release['zipball_url'], $zipPath)) {
            throw new RuntimeException('Downloaden van de update is mislukt. Controleer of deze server uitgaande HTTPS-verbindingen toestaat.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Kon het gedownloade updatebestand niet openen.');
        }
        mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        $sourceRoot = find_extracted_root($extractDir);

        $copied = [];
        copy_update_files($sourceRoot, dirname(__DIR__), '', $copied);

        $migrations = run_pending_migrations();

        return [
            'backup' => $backupResult,
            'files_copied' => count($copied),
            'migrations' => $migrations,
            'version' => $release['version'],
        ];
    } finally {
        remove_directory($tmpDir);
    }
}

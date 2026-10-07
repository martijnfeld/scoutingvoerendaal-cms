<?php
/**
 * Systeemcontrole voor Beheerpaneel → Systeemcontrole (admin/controle.php).
 *
 * Drie soorten controles:
 *   - run_system_checks(): alles wat PHP zelf kan zien (versie, extensies,
 *     database, configuratie, schrijfrechten, back-ups). Snel, draait bij
 *     elk bezoek aan de pagina.
 *   - checks_external(): uitgaande verbindingen (GitHub, Scoutdash). Traag,
 *     draait alleen na een klik op de knop.
 *   - checks_browser_probes(): een lijst URL's die de browser van de
 *     beheerder zelf opvraagt (assets/js/admin.js), om te zien hoe de
 *     webserver ze uitlevert (rewrites, afgeschermde mappen, headers).
 *     Vanuit PHP de eigen site opvragen werkt op veel shared hosts niet
 *     betrouwbaar (loopback geblokkeerd of via een andere route), en zo
 *     zien we precies wat een bezoeker ziet.
 *
 * Elke controle levert een array op met label, status ('ok', 'warn',
 * 'error' of 'info'), toelichting (platte tekst) en optioneel een link.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/scoutdash.php';
require_once __DIR__ . '/geoip.php';

/** Einde van de beveiligingsupdates per PHP-versie (https://www.php.net/supported-versions.php). */
const CHECK_PHP_EOL = [
    '7.4' => '2022-11-28',
    '8.0' => '2023-11-26',
    '8.1' => '2025-12-31',
    '8.2' => '2026-12-31',
    '8.3' => '2027-12-31',
    '8.4' => '2028-12-31',
    '8.5' => '2029-12-31',
];

/** Tabellen die sql/install.sql (plus migraties) aanmaakt. */
const CHECK_EXPECTED_TABLES = [
    'settings', 'speltakken', 'documents', 'info_cards', 'pages',
    'admin_users', 'login_attempts', 'schema_migrations',
];

/** .htaccess-bestanden die mappen afschermen (relatief aan de projectroot). */
const CHECK_HTACCESS_FILES = [
    '.htaccess', 'assets/uploads/.htaccess', 'includes/.htaccess', 'admin/includes/.htaccess',
    'sql/.htaccess', 'tools/.htaccess', 'backups/.htaccess', 'docker/.htaccess', 'tests/.htaccess',
];

/**
 * Bestanden die via de webserver NIET bereikbaar mogen zijn (relatief aan
 * de projectroot). Alleen bestanden die echt bestaan worden getest: een 404
 * op een ontbrekend bestand zegt niets over de afscherming.
 */
const CHECK_BLOCKED_FILES = [
    'includes/functions.php', 'includes/geo/europe-ipv4.bin', 'includes/cache/opkomsten.json',
    'admin/includes/auth.php', 'sql/install.sql', 'tools/build_geo_europe.php',
    'docker/xdebug.ini', 'tests/run.php', 'backups/.htaccess', '.htaccess', 'config.local.php.example',
    'INSTALL.md', 'CLAUDE.md', 'Dockerfile', 'docker-compose.yml',
];

const CHECK_STATUS_LABELS = [
    'ok' => 'OK',
    'warn' => 'Let op',
    'error' => 'Fout',
    'info' => 'Info',
    'pending' => 'Bezig…',
];

function check_result(string $label, string $status, string $detail = '', ?array $link = null): array
{
    return ['label' => $label, 'status' => $status, 'detail' => $detail, 'link' => $link];
}

/** Lokale Docker-ontwikkelomgeving (databasegegevens via environment-variabelen, zie config.php). */
function check_is_docker(): bool
{
    return (bool) getenv('DB_HOST');
}

/** php.ini-grootte ("8M", "1G", "-1") naar bytes; -1 betekent onbeperkt. */
function check_ini_bytes($value): int
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $number = (int) $value;
    switch (strtolower(substr($value, -1))) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
    }
    return $number;
}

/** Of PHP echt in $dir kan schrijven (is_writable() liegt soms, zie backup_prepare_dir()). */
function check_dir_writable(string $dir): bool
{
    if (!@is_dir($dir) && !@mkdir($dir, 0755, true) && !@is_dir($dir)) {
        return false;
    }
    $probe = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, '') === false) {
        return false;
    }
    @unlink($probe);
    return true;
}

function check_disabled_functions(): array
{
    return array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
}

/** Alle server-side controles, gegroepeerd per onderdeel. */
function run_system_checks(): array
{
    return [
        'PHP en webserver' => checks_php(),
        'Database' => checks_database(),
        'Configuratie en beveiliging' => checks_config(),
        'Back-ups' => checks_backups(),
        'Updates' => checks_updates(),
        'Bestanden en schrijfrechten' => checks_files(),
        'Scoutdash-opkomsten' => checks_opkomsten_cache(),
    ];
}

/* ------------------------------------------------------------------ *
 * PHP en webserver
 * ------------------------------------------------------------------ */

function checks_php(): array
{
    $r = [];

    $minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $eol = CHECK_PHP_EOL[$minor] ?? null;
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $r[] = check_result('PHP-versie', 'error',
            'PHP ' . PHP_VERSION . ' is te oud: deze website heeft minimaal PHP 7.4 nodig. Kies een nieuwere versie in je hostingpaneel.');
    } elseif ($eol !== null && strtotime($eol) < time()) {
        $r[] = check_result('PHP-versie', 'warn',
            'PHP ' . PHP_VERSION . ' werkt, maar krijgt sinds ' . date('d-m-Y', strtotime($eol))
            . ' geen beveiligingsupdates meer. Kies een nieuwere PHP-versie in je hostingpaneel.');
    } else {
        $r[] = check_result('PHP-versie', 'ok',
            'PHP ' . PHP_VERSION . ($eol !== null ? ' (beveiligingsupdates tot ' . date('d-m-Y', strtotime($eol)) . ')' : ''));
    }

    $extensions = [
        'pdo_mysql' => 'nodig voor de database',
        'zip' => 'nodig voor back-ups en automatisch bijwerken',
        'dom' => 'nodig om de Scoutdash-opkomsten veilig op te schonen',
        'json' => 'nodig voor de opkomsten- en updatecache',
    ];
    $missing = false;
    foreach ($extensions as $extension => $reason) {
        if (!extension_loaded($extension)) {
            $missing = true;
            $r[] = check_result('PHP-extensie "' . $extension . '"', 'error',
                'Ontbreekt — ' . $reason . '. Zet deze extensie aan in je hostingpaneel (PHP-instellingen/-extensies).');
        }
    }
    if (!$missing) {
        $r[] = check_result('PHP-extensies', 'ok', 'Alle benodigde extensies zijn aanwezig (' . implode(', ', array_keys($extensions)) . ').');
    }

    $hasCurl = function_exists('curl_init');
    $hasStreams = ini_get('allow_url_fopen') && extension_loaded('openssl');
    if ($hasCurl) {
        $r[] = check_result('Uitgaande HTTPS-verbindingen', 'ok', 'Via curl. Of de verbinding echt lukt, test je met de knop onderaan.');
    } elseif ($hasStreams) {
        $r[] = check_result('Uitgaande HTTPS-verbindingen', 'ok',
            'Via allow_url_fopen (curl ontbreekt, maar dat is niet nodig). Of de verbinding echt lukt, test je met de knop onderaan.');
    } else {
        $r[] = check_result('Uitgaande HTTPS-verbindingen', 'error',
            'Geen curl en geen allow_url_fopen met openssl: updates ophalen en de Scoutdash-opkomsten werken niet. Zet curl aan in je hostingpaneel.');
    }

    $upload = check_ini_bytes(ini_get('upload_max_filesize'));
    $post = check_ini_bytes(ini_get('post_max_size'));
    $limits = array_filter([$upload, $post], fn($v) => $v > 0);
    $uploadLimit = $limits ? min($limits) : -1;
    if ($uploadLimit > 0 && $uploadLimit < 8 * 1024 * 1024) {
        $r[] = check_result('Maximale uploadgrootte', 'warn',
            format_bytes($uploadLimit) . ' (upload_max_filesize/post_max_size): grotere foto\'s of PDF\'s kunnen niet worden geüpload. Verhoog dit in je hostingpaneel naar minimaal 8 MB.');
    } else {
        $r[] = check_result('Maximale uploadgrootte', 'ok', $uploadLimit > 0 ? format_bytes($uploadLimit) : 'Onbeperkt');
    }

    $memory = check_ini_bytes(ini_get('memory_limit'));
    if ($memory > 0 && $memory < 128 * 1024 * 1024) {
        $r[] = check_result('Geheugenlimiet (memory_limit)', 'warn',
            format_bytes($memory) . ': back-ups maken of bijwerken kan hierdoor mislukken. Advies: minimaal 128 MB.');
    } else {
        $r[] = check_result('Geheugenlimiet (memory_limit)', 'ok', $memory > 0 ? format_bytes($memory) : 'Onbeperkt');
    }

    $disabled = check_disabled_functions();
    $maxExecution = (int) ini_get('max_execution_time');
    if ($maxExecution > 0 && $maxExecution < 120 && in_array('set_time_limit', $disabled, true)) {
        $r[] = check_result('Maximale scriptduur', 'warn',
            $maxExecution . ' seconden, en set_time_limit() is uitgeschakeld: een back-up of update van een grote site kan halverwege afbreken.');
    } else {
        $r[] = check_result('Maximale scriptduur', 'ok',
            ($maxExecution > 0 ? $maxExecution . ' seconden' : 'Onbeperkt') . '; back-ups en updates heffen deze limiet zelf op.');
    }

    if ($disabled) {
        $r[] = check_result('Uitgeschakelde PHP-functies', 'info', implode(', ', $disabled));
    }

    $openBasedir = (string) ini_get('open_basedir');
    if ($openBasedir !== '') {
        $r[] = check_result('open_basedir', 'info',
            'PHP mag alleen in deze mappen komen: ' . $openBasedir . '. Dit kan verklaren waarom back-ups niet buiten de websitemap kunnen staan.');
    }

    if (ini_get('display_errors') && !APP_DEBUG) {
        $r[] = check_result('Foutmeldingen tonen (display_errors)', 'warn',
            'Staat aan: PHP-fouten (met serverpaden) worden aan bezoekers getoond. Zet display_errors uit in je hostingpaneel.');
    }

    $software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
    $serverInfo = ($software !== '' ? $software : 'onbekende webserver') . ', PHP via ' . PHP_SAPI;
    if (stripos($software, 'nginx') !== false) {
        $r[] = check_result('Webserver', 'warn',
            $serverInfo . '. nginx negeert .htaccess: zorg dat de nginx-configuratie uit INSTALL.md (hoofdstuk "Hostingvereisten en nginx") is ingesteld, en controleer de webservertests hieronder.');
    } else {
        $r[] = check_result('Webserver', 'info', $serverInfo);
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Database
 * ------------------------------------------------------------------ */

function checks_database(): array
{
    $r = [];
    try {
        $pdo = db();
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $e) {
        return [check_result('Verbinding', 'error', 'Geen verbinding met de database: ' . $e->getMessage())];
    }
    $product = stripos($version, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
    $r[] = check_result('Verbinding', 'ok', $product . ' ' . preg_replace('/-.*$/', '', $version) . ', database "' . DB_NAME . '"');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_diff(CHECK_EXPECTED_TABLES, $tables);
    if ($missingTables) {
        $r[] = check_result('Tabellen', 'error',
            'Ontbreekt: ' . implode(', ', $missingTables) . '. Voer openstaande migraties uit, of importeer sql/install.sql opnieuw.',
            ['href' => 'updates.php', 'text' => 'Naar Updates']);
    } else {
        $r[] = check_result('Tabellen', 'ok', 'Alle ' . count(CHECK_EXPECTED_TABLES) . ' tabellen zijn aanwezig.');
    }

    $stmt = $pdo->query(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    );
    $problems = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!in_array($row['TABLE_NAME'], CHECK_EXPECTED_TABLES, true)) {
            continue;
        }
        if (strcasecmp((string) $row['ENGINE'], 'InnoDB') !== 0) {
            $problems[] = $row['TABLE_NAME'] . ' (engine ' . $row['ENGINE'] . ')';
        } elseif (stripos((string) $row['TABLE_COLLATION'], 'utf8mb4') !== 0) {
            $problems[] = $row['TABLE_NAME'] . ' (tekenset ' . $row['TABLE_COLLATION'] . ')';
        }
    }
    if ($problems) {
        $r[] = check_result('Opslagformaat tabellen', 'warn',
            'Niet InnoDB/utf8mb4: ' . implode(', ', $problems) . '. Emoji en speciale tekens kunnen hierdoor verkeerd worden opgeslagen.');
    } else {
        $r[] = check_result('Opslagformaat tabellen', 'ok', 'InnoDB met utf8mb4.');
    }

    try {
        $pending = array_diff(array_map('basename', list_migration_files()), list_applied_migrations());
        if ($pending) {
            $r[] = check_result('Migraties', 'error',
                count($pending) . ' openstaande migratie(s): ' . implode(', ', $pending) . '. Voer ze uit via Updates → "Alleen migraties uitvoeren".',
                ['href' => 'updates.php', 'text' => 'Naar Updates']);
        } else {
            $r[] = check_result('Migraties', 'ok', 'Alle migraties zijn uitgevoerd.');
        }
    } catch (Throwable $e) {
        $r[] = check_result('Migraties', 'error', 'Kon de migratiestatus niet bepalen: ' . $e->getMessage());
    }

    $dbNow = strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn());
    $offset = $dbNow !== false ? $dbNow - time() : 0;
    if (abs($offset) > 120) {
        $r[] = check_result('Tijd database', 'info',
            'De databaseklok loopt ' . abs((int) round($offset / 60)) . ' minuten ' . ($offset < 0 ? 'achter' : 'voor')
            . ' op PHP (Europe/Amsterdam), waarschijnlijk door een andere tijdzone. De site houdt daar zelf rekening mee.');
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Configuratie en beveiliging
 * ------------------------------------------------------------------ */

function checks_config(): array
{
    $r = [];
    $root = dirname(__DIR__);
    $docker = check_is_docker();

    $r[] = check_result('Versie', 'info', APP_VERSION);

    if (is_file($root . '/config.local.php')) {
        $r[] = check_result('config.local.php', 'ok', 'Aanwezig.');
    } elseif ($docker) {
        $r[] = check_result('config.local.php', 'info', 'Niet aanwezig; in Docker komen de gegevens uit environment-variabelen.');
    } else {
        $r[] = check_result('config.local.php', 'warn',
            'Niet aanwezig: de echte instellingen staan waarschijnlijk nog in config.php, en die wordt bij een update overschreven. De eerste automatische update zet ze vanzelf over; bij handmatig bijwerken moet je config.local.php zelf aanmaken (zie config.local.php.example).');
    }

    if (BACKUP_CRON_KEY === 'wijzig_deze_geheime_sleutel') {
        $r[] = check_result('Sleutel voor back-up-cronjob', 'warn',
            'Nog de standaardwaarde: een wekelijkse back-up via een cron-URL weigert dan te draaien. Stel een lange, willekeurige BACKUP_CRON_KEY in config.local.php in (niet nodig als je cronjob PHP-CLI gebruikt).');
    } elseif (strlen(BACKUP_CRON_KEY) < 32) {
        $r[] = check_result('Sleutel voor back-up-cronjob', 'warn',
            'Korter dan 32 tekens; gebruik een langere, willekeurige BACKUP_CRON_KEY in config.local.php.');
    } else {
        $r[] = check_result('Sleutel voor back-up-cronjob', 'ok', 'Ingesteld.');
    }

    if (GITHUB_REPO === 'jouw-gebruikersnaam/jouw-repo-naam') {
        $r[] = check_result('Update-repository (GITHUB_REPO)', 'warn',
            'Niet ingesteld: de site kan niet controleren op updates. Zet GITHUB_REPO in config.local.php.');
    } else {
        $r[] = check_result('Update-repository (GITHUB_REPO)', 'ok', GITHUB_REPO);
    }

    if (APP_DEBUG) {
        $r[] = check_result('Debugmodus (APP_DEBUG)', $docker ? 'info' : 'warn',
            $docker ? 'Aan (lokale ontwikkelomgeving).' : 'Aan: PHP-fouten worden aan bezoekers getoond. Zet APP_DEBUG uit.');
    } else {
        $r[] = check_result('Debugmodus (APP_DEBUG)', 'ok', 'Uit.');
    }

    if (is_file($root . '/install.php')) {
        $r[] = check_result('install.php', 'error',
            'Staat nog op de server. Hij is na de eerste installatie uitgeschakeld, maar verwijder het bestand via FTP of het bestandsbeheer van je hostingpaneel.');
    } else {
        $r[] = check_result('install.php', 'ok', 'Verwijderd.');
    }

    if (request_is_https()) {
        $r[] = check_result('HTTPS', 'ok', 'Het beheerpaneel wordt via HTTPS geladen.');
    } else {
        $r[] = check_result('HTTPS', $docker ? 'info' : 'warn',
            'Het beheerpaneel wordt via onversleuteld HTTP geladen; wachtwoorden en de sessiecookie gaan dan leesbaar over het netwerk. Zet een (gratis Let\'s Encrypt-)certificaat aan en "HTTPS forceren" in je hostingpaneel.');
    }

    $siteUrl = trim(get_setting('site_url'));
    $settingsLink = ['href' => 'settings.php', 'text' => 'Naar Teksten & gegevens'];
    if ($siteUrl === '') {
        $r[] = check_result('Website-adres (site_url)', 'error',
            'Niet ingesteld. De sitemap, links in zoekmachines en afbeeldingen in pagina\'s gebruiken dit adres.', $settingsLink);
    } else {
        $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $requestHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (strtolower((string) parse_url($siteUrl, PHP_URL_SCHEME)) !== 'https' && !$docker) {
            $r[] = check_result('Website-adres (site_url)', 'warn', $siteUrl . ' begint niet met https://.', $settingsLink);
        } elseif ($siteHost !== '' && $requestHost !== '' && $siteHost !== $requestHost) {
            $r[] = check_result('Website-adres (site_url)', 'warn',
                $siteUrl . ' wijkt af van het adres waarop je nu bent (' . $requestHost . '). Afbeeldingen die je in pagina\'s plakt, linken naar het ingestelde adres.', $settingsLink);
        } else {
            $r[] = check_result('Website-adres (site_url)', 'ok', $siteUrl);
        }
    }

    if (defined('ADMIN_GEO_BLOCK') && !ADMIN_GEO_BLOCK) {
        $r[] = check_result('Geoblokkade inloggen', 'warn',
            'Uitgeschakeld (ADMIN_GEO_BLOCK in config.local.php): er kan van overal ter wereld worden ingelogd.');
    } elseif (!geo_data_available()) {
        $r[] = check_result('Geoblokkade inloggen', 'warn',
            'De gegevensbestanden in includes/geo/ ontbreken, dus de blokkade staat feitelijk uit. Upload ze opnieuw.');
    } else {
        $generated = null;
        $stamp = @file_get_contents(GEO_DATA_DIR . '/GENERATED.txt');
        if ($stamp !== false && preg_match('/(\d{4}-\d{2}-\d{2})/', $stamp, $m)) {
            $generated = strtotime($m[1]);
        }
        $countries = defined('ADMIN_LOGIN_COUNTRIES') ? ' Toegestane landen: ' . ADMIN_LOGIN_COUNTRIES . '.' : ' Inloggen kan alleen vanuit Europa.';
        if ($generated !== null && $generated < strtotime('-6 months')) {
            $r[] = check_result('Geoblokkade inloggen', 'warn',
                'Aan, maar de IP-gegevens zijn van ' . date('d-m-Y', $generated) . ' en dus verouderd; een nieuwe release brengt actuele gegevens mee.' . $countries);
        } else {
            $r[] = check_result('Geoblokkade inloggen', 'ok',
                'Aan' . ($generated !== null ? ', IP-gegevens van ' . date('d-m-Y', $generated) : '') . '.' . $countries);
        }
    }

    $accounts = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    $r[] = check_result('Beheeraccounts', 'info', $accounts . ' account(s).', ['href' => 'accounts.php', 'text' => 'Naar Accounts']);

    return $r;
}

/* ------------------------------------------------------------------ *
 * Back-ups
 * ------------------------------------------------------------------ */

function checks_backups(): array
{
    $r = [];
    $backupsLink = ['href' => 'backups.php', 'text' => 'Naar Back-ups'];

    try {
        $status = backup_dir_status();
    } catch (RuntimeException $e) {
        $status = null;
        $r[] = check_result('Back-upmap', 'error', $e->getMessage(), $backupsLink);
    }
    if ($status !== null) {
        if ($status['fallback']) {
            $r[] = check_result('Back-upmap', 'warn',
                $status['configured'] . ' is niet bruikbaar; back-ups staan nu in ' . $status['dir'] . ' binnen de websitemap (afgeschermd via .htaccess, maar dat werkt niet op elke server).', $backupsLink);
        } elseif ($status['inside_webroot']) {
            $r[] = check_result('Back-upmap', 'warn',
                $status['dir'] . ' ligt binnen de websitemap. Stel bij voorkeur een map buiten de websitemap in via BACKUP_DIR.', $backupsLink);
        } else {
            $r[] = check_result('Back-upmap', 'ok', $status['dir'] . ' — beschrijfbaar en buiten de websitemap.');
        }
    }

    $backups = list_backups();
    $latest = $backups[0] ?? null;

    if ($status !== null && function_exists('disk_free_space') && !in_array('disk_free_space', check_disabled_functions(), true)) {
        $free = @disk_free_space($status['dir']);
        if ($free !== false) {
            if ($latest !== null && $free < 2 * $latest['size']) {
                $r[] = check_result('Vrije schijfruimte', 'warn',
                    format_bytes((int) $free) . ' vrij, terwijl een back-up ongeveer ' . format_bytes($latest['size']) . ' is. Ruim oude back-ups of bestanden op.');
            } else {
                $r[] = check_result('Vrije schijfruimte', 'info',
                    format_bytes((int) $free) . ' vrij volgens de server (je hostingpakket kan een lagere limiet hebben).');
            }
        }
    }

    if ($latest === null) {
        $r[] = check_result('Laatste back-up', 'error', 'Er is nog geen back-up gemaakt.', $backupsLink);
    } else {
        $days = (int) floor((time() - $latest['created_at']) / 86400);
        $detail = date('d-m-Y H:i', $latest['created_at']) . ' (' . format_bytes($latest['size']) . '), '
            . ($days === 0 ? 'vandaag' : $days . ' dag(en) geleden') . '.';
        if ($days > 14) {
            $r[] = check_result('Laatste back-up', 'error', $detail . ' Dat is te lang geleden.', $backupsLink);
        } elseif ($days > 8) {
            $r[] = check_result('Laatste back-up', 'warn', $detail . ' De wekelijkse back-up lijkt te zijn overgeslagen.', $backupsLink);
        } else {
            $r[] = check_result('Laatste back-up', 'ok', $detail);
        }
    }

    // Heuristiek: of de cronjob echt draait kunnen we niet zien, maar met een
    // wekelijkse cronjob zijn er in vijf weken minstens vier back-ups.
    $recent = count(array_filter($backups, fn($b) => $b['created_at'] >= strtotime('-35 days')));
    if ($recent >= 4) {
        $r[] = check_result('Automatische back-ups', 'ok',
            $recent . ' back-ups in de afgelopen 5 weken; de wekelijkse cronjob lijkt te werken.');
    } else {
        $r[] = check_result('Automatische back-ups', 'warn',
            'Maar ' . $recent . ' back-up(s) in de afgelopen 5 weken (handmatige meegeteld). Controleer of de wekelijkse cronjob in je hostingpaneel is ingesteld — zie INSTALL.md, hoofdstuk "Back-ups".');
    }

    if ($backups) {
        $total = array_sum(array_column($backups, 'size'));
        $r[] = check_result('Bewaarde back-ups', 'info',
            count($backups) . ' back-up(s), samen ' . format_bytes((int) $total) . '. Back-ups ouder dan ' . (int) BACKUP_RETENTION_MONTHS . ' maanden worden automatisch verwijderd.');
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Updates
 * ------------------------------------------------------------------ */

function checks_updates(): array
{
    $r = [];
    $root = dirname(__DIR__);
    $updatesLink = ['href' => 'updates.php', 'text' => 'Naar Updates'];

    $cached = update_read_cache();
    if ($cached === null) {
        $r[] = check_result('Nieuwste versie', 'info', 'Nog niet gecontroleerd; gebruik de knop "Externe verbindingen testen" onderaan.');
    } elseif (empty($cached['ok']) || empty($cached['release'])) {
        $r[] = check_result('Nieuwste versie', 'warn',
            ($cached['error'] ?? 'Laatste updatecheck is mislukt.') . ' (gecontroleerd op ' . date('d-m-Y H:i', (int) $cached['checked_at']) . ')', $updatesLink);
    } elseif (update_is_newer($cached['release']['version'])) {
        $r[] = check_result('Nieuwste versie', 'warn',
            'Versie ' . $cached['release']['version'] . ' is beschikbaar (je hebt ' . APP_VERSION . ').', $updatesLink);
    } else {
        $r[] = check_result('Nieuwste versie', 'ok',
            'Je gebruikt de nieuwste versie (gecontroleerd op ' . date('d-m-Y H:i', (int) $cached['checked_at']) . ').');
    }

    $reasons = [];
    if (!class_exists('ZipArchive')) {
        $reasons[] = 'de PHP-extensie zip ontbreekt';
    }
    if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
        $reasons[] = 'er zijn geen uitgaande HTTPS-verbindingen mogelijk';
    }
    if (!check_dir_writable($root) || !check_dir_writable($root . '/includes') || !is_writable($root . '/index.php')) {
        $reasons[] = 'PHP mag de sitebestanden niet overschrijven';
    }
    if ($reasons) {
        $r[] = check_result('Automatisch bijwerken', 'warn',
            'Niet mogelijk: ' . implode(', ', $reasons) . '. Upload nieuwe versies dan zelf via FTP en klik daarna op Updates → "Alleen migraties uitvoeren".', $updatesLink);
    } else {
        $r[] = check_result('Automatisch bijwerken', 'ok', 'Mogelijk.');
    }

    try {
        $tmpDir = update_tmp_dir();
        if (@is_dir($tmpDir)) {
            $r[] = check_result('Restanten van een update', 'info',
                $tmpDir . ' is blijven staan na een afgebroken update. Die map mag weg; de volgende update ruimt hem ook zelf op.');
        }
    } catch (RuntimeException $e) {
        // Geen back-upmap: al gemeld bij Back-ups.
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Bestanden en schrijfrechten
 * ------------------------------------------------------------------ */

function checks_files(): array
{
    $r = [];
    $root = dirname(__DIR__);

    if (check_dir_writable(UPLOAD_DIR)) {
        $r[] = check_result('Uploadmap (assets/uploads)', 'ok', 'Beschrijfbaar.');
    } else {
        $r[] = check_result('Uploadmap (assets/uploads)', 'error',
            'Niet beschrijfbaar: foto\'s en PDF\'s uploaden werkt niet. Geef de webserver schrijfrechten op deze map (bv. chmod 755 of 775 via FTP).');
    }

    if (check_dir_writable(opkomsten_cache_dir())) {
        $r[] = check_result('Cachemap (includes/cache)', 'ok', 'Beschrijfbaar.');
    } else {
        $r[] = check_result('Cachemap (includes/cache)', 'error',
            'Niet beschrijfbaar: de opkomsten en de updatecheck kunnen niet worden opgeslagen. Geef de webserver schrijfrechten op deze map.');
    }

    $missing = [];
    foreach (CHECK_HTACCESS_FILES as $file) {
        if (is_dir(dirname($root . '/' . $file)) && !is_file($root . '/' . $file)) {
            $missing[] = $file;
        }
    }
    if ($missing) {
        $r[] = check_result('Afschermende .htaccess-bestanden', 'error',
            'Ontbreekt: ' . implode(', ', $missing) . '. Upload ze opnieuw — veel FTP-programma\'s verbergen bestanden die met een punt beginnen en slaan ze dan over.');
    } else {
        $r[] = check_result('Afschermende .htaccess-bestanden', 'ok', 'Allemaal aanwezig. Of de webserver ze ook gebruikt, zie je bij de webservertests.');
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Scoutdash-opkomsten (alleen de cache; de echte verbinding testen
 * gebeurt in checks_external())
 * ------------------------------------------------------------------ */

function checks_opkomsten_cache(): array
{
    $feeds = array_filter(get_speltakken(true), fn($sp) => !empty($sp['feed_url']));
    if (!$feeds) {
        return [check_result('Opkomsten-feeds', 'info', 'Geen enkele actieve speltak gebruikt een Scoutdash-feed.')];
    }

    $cache = opkomsten_cache_overview();
    if ($cache === null) {
        return [check_result('Opkomsten-cache', 'info',
            'Nog leeg; wordt gevuld bij het eerste bezoek aan de website.')];
    }

    $r = [check_result('Opkomsten-cache', 'info', 'Laatst ververst op ' . date('d-m-Y H:i', (int) $cache['generated_at']) . '.')];
    foreach ($feeds as $sp) {
        $entry = $cache['data'][$sp['slug']] ?? null;
        $label = 'Feed ' . $sp['naam'];
        if ($entry === null) {
            $r[] = check_result($label, 'info', 'Nog niet opgehaald.');
        } elseif (!empty($entry['ok'])) {
            $r[] = check_result($label, 'ok', 'Laatste ophaling gelukt (' . count($entry['items'] ?? []) . ' opkomsten).');
        } elseif (!empty($entry['fetched_at'])) {
            $r[] = check_result($label, 'warn',
                'Scoutdash was bij de laatste poging niet bereikbaar; de website toont het programma van ' . date('d-m-Y H:i', (int) $entry['fetched_at']) . '.');
        } else {
            $r[] = check_result($label, 'error',
                'Nog nooit gelukt om deze feed op te halen; bezoekers zien een foutmelding. Controleer de feed-URL bij de speltak.',
                ['href' => 'speltak_form.php?id=' . (int) $sp['id'], 'text' => 'Speltak bewerken']);
        }
    }
    return $r;
}

/* ------------------------------------------------------------------ *
 * Externe verbindingen (alleen op verzoek, kan even duren)
 * ------------------------------------------------------------------ */

function checks_external(): array
{
    @set_time_limit(120);
    $r = [];

    $info = fetch_latest_release(true);
    if (!empty($info['ok']) && !empty($info['release'])) {
        $version = $info['release']['version'];
        $r[] = update_is_newer($version)
            ? check_result('GitHub (updates)', 'warn', 'Verbinding gelukt. Versie ' . $version . ' is beschikbaar.', ['href' => 'updates.php', 'text' => 'Naar Updates'])
            : check_result('GitHub (updates)', 'ok', 'Verbinding gelukt; je gebruikt de nieuwste versie (' . $version . ').');
    } else {
        $r[] = check_result('GitHub (updates)', 'error', $info['error'] ?? 'Kan GitHub niet bereiken.');
    }

    foreach (get_speltakken(true) as $sp) {
        if (empty($sp['feed_url'])) {
            continue;
        }
        $start = microtime(true);
        $items = scoutdash_fetch_items($sp['feed_url']);
        $ms = (int) round((microtime(true) - $start) * 1000);
        $label = 'Scoutdash: ' . $sp['naam'];
        if ($items === null) {
            $r[] = check_result($label, 'error',
                'Niet bereikbaar of geen geldig antwoord (na ' . $ms . ' ms). Controleer de feed-URL.',
                ['href' => 'speltak_form.php?id=' . (int) $sp['id'], 'text' => 'Speltak bewerken']);
        } else {
            $r[] = check_result($label, $ms > 5000 ? 'warn' : 'ok',
                count($items) . ' opkomsten opgehaald in ' . $ms . ' ms.' . ($ms > 5000 ? ' Dat is traag; de website wacht hierop als de cache verloopt.' : ''));
        }
    }

    return $r;
}

/* ------------------------------------------------------------------ *
 * Webservertests (uitgevoerd door de browser, zie assets/js/admin.js)
 * ------------------------------------------------------------------ */

/**
 * Lijst van URL's (relatief aan /admin/) die de browser opvraagt, met wat
 * we verwachten:
 *   - blocked:  403/404 (of een doorverwijzing), nooit 200
 *   - xml:      200 met een XML-content-type
 *   - page:     200
 *   - redirect: een doorverwijzing
 *   - headers:  de security-headers uit .htaccess en de CSP uit PHP
 */
function checks_browser_probes(): array
{
    $root = dirname(__DIR__);
    $probes = [];

    $probes[] = ['label' => 'URL-herschrijving (/sitemap.xml)', 'url' => '../sitemap.xml', 'expect' => 'xml'];

    $pages = get_pages(true);
    if ($pages) {
        $slug = $pages[0]['slug'];
        $probes[] = ['label' => 'Pagina-adres (/' . $slug . ')', 'url' => '../' . rawurlencode($slug), 'expect' => 'page'];
        $probes[] = [
            'label' => 'Oude pagina-links doorsturen',
            'url' => '../pagina.php?slug=' . rawurlencode($slug),
            'expect' => 'redirect',
        ];
    }

    $probes[] = ['label' => 'Security-headers (homepage)', 'url' => '../', 'expect' => 'headers'];
    $probes[] = ['label' => 'Geen mappenoverzicht (assets/uploads/)', 'url' => '../assets/uploads/', 'expect' => 'blocked'];

    foreach (CHECK_BLOCKED_FILES as $file) {
        if (is_file($root . '/' . $file)) {
            $probes[] = ['label' => 'Afgeschermd: ' . $file, 'url' => '../' . $file, 'expect' => 'blocked'];
        }
    }

    return $probes;
}

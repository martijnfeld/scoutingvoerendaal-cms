<?php
/**
 * Back-up-functionaliteit: maakt een zipbestand met een volledige
 * databasedump (pure PHP, geen mysqldump-CLI nodig op shared hosting) plus
 * alle sitebestanden, en ruimt back-ups ouder dan BACKUP_RETENTION_MONTHS op.
 *
 * LET OP bij nieuwe features: deze back-up neemt de hele projectmap mee
 * (op de uitzonderingen in BACKUP_EXCLUDES na) plus een dump van de hele
 * database, dus nieuwe tabellen en nieuwe bestanden onder deze projectmap
 * worden automatisch meegenomen. Sla je gegevens ergens anders op (bv. een
 * externe opslagdienst, of een map buiten deze projectmap), breid dit
 * bestand dan uit zodat die gegevens ook in de back-up terechtkomen. Zie
 * ook de toelichting in CLAUDE.md.
 *
 * Waar back-ups staan: standaard in BACKUP_DIR, een map náást de projectmap
 * (dus buiten de webroot, zie config.php) — een back-up bevat immers
 * config.local.php en wachtwoord-hashes. Kan die map op deze host niet
 * worden aangemaakt/beschreven (rechten, open_basedir), dan vallen we terug
 * op `backups/` binnen de projectmap (afgeschermd via .htaccess); het
 * beheerpaneel waarschuwt dan. Back-ups van vóór deze wijziging blijven in
 * `backups/` staan en worden gewoon getoond (zie backup_known_dirs()).
 */

require_once __DIR__ . '/functions.php';

/** Mappen/bestanden (relatief aan de projectroot) die nooit in de back-up horen. */
const BACKUP_EXCLUDES = [
    'backups',
    '.git',
    '.claude',
    '.DS_Store',
    'Thumbs.db',
];

/** .htaccess die we in elke back-upmap zetten (Apache 2.2/2.4, met of zonder mod_access_compat, LiteSpeed). */
const BACKUP_HTACCESS = "# Geen directe toegang via de webserver.\n"
    . "<IfModule mod_authz_core.c>\n"
    . "    Require all denied\n"
    . "</IfModule>\n"
    . "<IfModule !mod_authz_core.c>\n"
    . "    Order allow,deny\n"
    . "    Deny from all\n"
    . "</IfModule>\n";

/** De projectroot (de map met index.php/config.php). */
function backup_project_root(): string
{
    return dirname(__DIR__);
}

/**
 * De oude/terugvalmap binnen de projectmap (`backups/`). Hier stonden
 * back-ups vóórdat BACKUP_DIR standaard buiten de webroot kwam te staan, en
 * hier vallen we op terug als BACKUP_DIR niet bruikbaar is.
 */
function backup_fallback_dir(): string
{
    return backup_project_root() . DIRECTORY_SEPARATOR . 'backups';
}

/** Normaliseert een pad voor vergelijkingen (realpath indien mogelijk, forward slashes, geen slash aan het eind). */
function backup_normalize_path(string $path): string
{
    $real = @realpath($path);
    return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
}

/** Ligt $path binnen (of gelijk aan) $parent? */
function backup_path_is_inside(string $path, string $parent): bool
{
    $path = backup_normalize_path($path);
    $parent = backup_normalize_path($parent);
    return $parent !== '' && ($path === $parent || strpos($path . '/', $parent . '/') === 0);
}

/**
 * Valt $dir binnen open_basedir (als dat is ingesteld)? Zo slaan we een
 * niet-toegestaan pad meteen over in plaats van op is_dir()/mkdir() te
 * vertrouwen (die geven daar "open_basedir restriction"-waarschuwingen; die
 * onderdrukken we elders met @ als vangnet). Bij twijfel (symlinks) geeft
 * dit true terug, zodat een bruikbare map nooit onterecht wordt afgewezen.
 */
function backup_path_allowed_by_open_basedir(string $dir): bool
{
    $openBasedir = (string) ini_get('open_basedir');
    if ($openBasedir === '') {
        return true;
    }
    $candidates = [rtrim(str_replace('\\', '/', $dir), '/')];
    $realParent = @realpath(dirname($dir));
    if ($realParent !== false) {
        $candidates[] = rtrim(str_replace('\\', '/', $realParent), '/') . '/' . basename($dir);
    }
    foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowed) {
        $allowed = trim($allowed);
        if ($allowed === '') {
            continue;
        }
        if ($allowed === '.') {
            $allowed = (string) getcwd();
        }
        $allowedVariants = [rtrim(str_replace('\\', '/', $allowed), '/')];
        $realAllowed = @realpath($allowed);
        if ($realAllowed !== false) {
            $allowedVariants[] = rtrim(str_replace('\\', '/', $realAllowed), '/');
        }
        foreach ($candidates as $candidate) {
            foreach ($allowedVariants as $variant) {
                if (strpos($candidate . '/', $variant . '/') === 0) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Probeert $dir aan te maken, af te schermen en te testen op schrijfbaarheid.
 * Geeft true terug als de map bruikbaar is voor back-ups. Geeft nooit
 * PHP-waarschuwingen (alles met @ en een open_basedir-check vooraf).
 */
function backup_prepare_dir(string $dir): bool
{
    if ($dir === '' || !backup_path_allowed_by_open_basedir($dir)) {
        return false;
    }
    if (!@is_dir($dir) && !@mkdir($dir, 0755, true) && !@is_dir($dir)) {
        return false;
    }
    if (!@is_writable($dir)) {
        return false;
    }
    // is_writable() kan op sommige hosts (ACL's, netwerkschijven) liegen: echt even schrijven.
    $probe = $dir . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, '') === false) {
        return false;
    }
    @unlink($probe);

    // Afschermen via .htaccess: overbodig buiten de webroot, maar onschadelijk,
    // en het beschermt de map wél als die (bv. als terugval) binnen de webroot staat.
    // Een .htaccess met de oude, door ons gegenereerde inhoud (alleen Apache
    // 2.2-syntax) werken we bij; een zelf aangepaste .htaccess laten we staan.
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    $current = @is_file($htaccess) ? @file_get_contents($htaccess) : false;
    if ($current === false || $current === "Order allow,deny\nDeny from all\n") {
        @file_put_contents($htaccess, BACKUP_HTACCESS);
    }
    return true;
}

/**
 * Bepaalt (één keer per request) welke back-upmap er gebruikt wordt:
 * BACKUP_DIR als die bruikbaar is (standaard buiten de webroot, zie
 * config.php), anders de terugvalmap `backups/` binnen de projectmap.
 * Geeft een array terug met:
 *   - dir:            de map die daadwerkelijk gebruikt wordt
 *   - configured:     BACKUP_DIR zoals ingesteld
 *   - fallback:       true als BACKUP_DIR niet bruikbaar was en we terugvallen
 *   - inside_webroot: true als de gebruikte map via de webserver bereikbaar
 *                     zou kunnen zijn (binnen de projectmap of DOCUMENT_ROOT)
 */
function backup_dir_status(): array
{
    static $status = null;
    if ($status !== null) {
        return $status;
    }
    // Let op: gooit een RuntimeException als er helemaal geen beschrijfbare
    // map is; dan proberen we het bij de volgende aanroep gewoon opnieuw.

    $configured = rtrim(BACKUP_DIR, '/\\');
    $dir = $configured;
    $fallback = false;
    if (!backup_prepare_dir($configured)) {
        $dir = backup_fallback_dir();
        $fallback = true;
        if (!backup_prepare_dir($dir)) {
            throw new RuntimeException(
                'Er is geen beschrijfbare map voor back-ups: zowel ' . $configured . ' als ' . $dir
                . ' kon niet worden aangemaakt of beschreven. Controleer de schrijfrechten.'
            );
        }
    }

    $insideWebroot = backup_path_is_inside($dir, backup_project_root());
    $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$insideWebroot && $documentRoot !== '') {
        $insideWebroot = backup_path_is_inside($dir, $documentRoot);
    }

    $status = [
        'dir' => $dir,
        'configured' => $configured,
        'fallback' => $fallback,
        'inside_webroot' => $insideWebroot,
    ];
    return $status;
}

/** Zorgt dat de back-upmap bestaat en afgeschermd is, en geeft het pad terug. */
function backup_dir(): string
{
    return backup_dir_status()['dir'];
}

/**
 * Alle mappen waarin back-ups kunnen staan: de actieve map, de ingestelde
 * BACKUP_DIR (ook als we nu terugvallen, bv. als de cronjob onder een
 * andere gebruiker draaide) en de oude map `backups/` binnen de projectmap
 * (back-ups van vóór deze wijziging). Alleen in deze mappen mag het
 * beheerpaneel back-ups tonen, downloaden en verwijderen.
 */
function backup_known_dirs(): array
{
    $candidates = [rtrim(BACKUP_DIR, '/\\'), backup_fallback_dir()];
    try {
        array_unshift($candidates, backup_dir());
    } catch (RuntimeException $e) {
        // Geen beschrijfbare map: bestaande back-ups wel blijven tonen/downloaden.
    }
    $dirs = [];
    foreach ($candidates as $dir) {
        if (!backup_path_allowed_by_open_basedir($dir) || !@is_dir($dir)) {
            continue;
        }
        $dirs[backup_normalize_path($dir)] = $dir;
    }
    return array_values($dirs);
}

/**
 * Zoekt een back-up op naam in de bekende back-upmappen. Alleen een kale
 * bestandsnaam (basename) die eindigt op .zip is toegestaan. Geeft het
 * volledige pad terug, of null als hij niet bestaat.
 */
function find_backup_path(string $name): ?string
{
    $name = basename($name);
    if ($name === '' || substr($name, -4) !== '.zip') {
        return null;
    }
    foreach (backup_known_dirs() as $dir) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@is_file($path)) {
            return $path;
        }
    }
    return null;
}

/** Leesbare bestandsgrootte (bv. "4,3 MB"). */
function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = $bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return str_replace('.', ',', (string) round($value, 1)) . ' ' . $units[$i];
}

/**
 * Lijst van bestaande back-ups uit alle bekende back-upmappen (nieuwste
 * eerst). 'legacy' is true voor back-ups die nog in de oude map `backups/`
 * binnen de projectmap staan terwijl er inmiddels een andere map in gebruik is.
 */
function list_backups(): array
{
    try {
        $activeDir = backup_normalize_path(backup_dir());
    } catch (RuntimeException $e) {
        $activeDir = '';
    }
    $fallbackDir = backup_normalize_path(backup_fallback_dir());
    $backups = [];
    $seen = [];
    foreach (backup_known_dirs() as $dir) {
        $normalizedDir = backup_normalize_path($dir);
        foreach (@scandir($dir) ?: [] as $entry) {
            if (substr($entry, -4) !== '.zip' || isset($seen[$entry])) continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!@is_file($path)) continue;
            $seen[$entry] = true;
            $backups[] = [
                'name' => $entry,
                'path' => $path,
                'dir' => $dir,
                'legacy' => $normalizedDir === $fallbackDir && $normalizedDir !== $activeDir,
                'size' => (int) @filesize($path),
                'created_at' => (int) @filemtime($path),
            ];
        }
    }
    usort($backups, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
    return $backups;
}

/**
 * Schrijft een pure-PHP SQL-dump van de volledige database weg (schema +
 * data van alle tabellen), zodat geen mysqldump-CLI nodig is.
 */
function dump_database_to_file(string $destination): void
{
    $pdo = db();
    $fh = fopen($destination, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Kon databasedump-bestand niet aanmaken.');
    }

    fwrite($fh, "-- Databasedump van " . DB_NAME . " — gemaakt op " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $quotedTable = str_replace('`', '', $table);

        $createRow = $pdo->query('SHOW CREATE TABLE `' . $quotedTable . '`')->fetch();
        $createSql = $createRow['Create Table'] ?? null;
        if ($createSql === null) continue;

        fwrite($fh, "-- --------------------------------------------------\n");
        fwrite($fh, "DROP TABLE IF EXISTS `{$quotedTable}`;\n");
        fwrite($fh, $createSql . ";\n\n");

        $stmt = $pdo->query('SELECT * FROM `' . $quotedTable . '`');
        $columns = null;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
            }
            $values = array_map(
                fn($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                $row
            );
            $columnList = '`' . implode('`, `', $columns) . '`';
            fwrite($fh, "INSERT INTO `{$quotedTable}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n");
        }
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

/**
 * Voegt een map recursief toe aan een ZipArchive, met BACKUP_EXCLUDES
 * (relatief aan $sourceDir, alleen op het hoogste niveau) en de absolute
 * paden in $excludeDirs overgeslagen — die mappen worden ook niet doorlopen.
 */
function add_directory_to_zip(ZipArchive $zip, string $sourceDir, string $zipPathPrefix, array $excludeDirs = []): void
{
    $sourceDir = rtrim($sourceDir, '/\\');
    $excludeNormalized = array_map('backup_normalize_path', $excludeDirs);

    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $item) use ($sourceDir, $excludeNormalized): bool {
            $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceDir) + 1));
            if (in_array(explode('/', $relativePath)[0], BACKUP_EXCLUDES, true)) {
                return false;
            }
            if ($item->isDir() && $excludeNormalized) {
                $normalized = backup_normalize_path($item->getPathname());
                if (in_array($normalized, $excludeNormalized, true)) {
                    return false;
                }
            }
            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceDir) + 1));
        $zipPath = $zipPathPrefix . '/' . $relativePath;
        if ($item->isDir()) {
            $zip->addEmptyDir($zipPath);
        } else {
            $zip->addFile($item->getPathname(), $zipPath);
        }
    }
}

/**
 * Maakt een volledige back-up (database + sitebestanden) als één zipbestand
 * in BACKUP_DIR. Geeft de bestandsnaam van de aangemaakte back-up terug.
 */
function create_backup(): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('De PHP-extensie "zip" ontbreekt op deze server; back-ups maken is niet mogelijk.');
    }

    @set_time_limit(0);

    $dir = backup_dir();
    // Datum vooraan (leesbaar/sorteerbaar), plus een willekeurig achtervoegsel
    // zodat de naam niet te raden is, mocht de map ooit via het web bereikbaar zijn.
    $filename = 'backup-' . date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(4)) . '.zip';
    $zipPath = $dir . DIRECTORY_SEPARATOR . $filename;
    $tmpSqlPath = $dir . DIRECTORY_SEPARATOR . ('.tmp-' . bin2hex(random_bytes(8)) . '.sql');

    try {
        dump_database_to_file($tmpSqlPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Kon zipbestand niet aanmaken.');
        }

        $zip->addFile($tmpSqlPath, 'database.sql');
        // Altijd de echte projectroot zippen (BACKUP_DIR staat standaard
        // buiten de projectmap, dus dirname(BACKUP_DIR) zou de hele
        // bovenliggende map meenemen). Een back-upmap die wél binnen de
        // projectmap staat (terugval of zelf ingesteld) slaan we over.
        add_directory_to_zip($zip, backup_project_root(), 'files', backup_known_dirs());
        $zip->close();
    } finally {
        @unlink($tmpSqlPath);
    }

    return $filename;
}

/** Verwijdert back-ups ouder dan BACKUP_RETENTION_MONTHS. Geeft het aantal verwijderde bestanden terug. */
function purge_old_backups(): int
{
    $cutoff = strtotime('-' . BACKUP_RETENTION_MONTHS . ' months');
    $deleted = 0;
    // Geldt voor alle bekende back-upmappen, dus ook oude back-ups in `backups/`.
    foreach (list_backups() as $backup) {
        if ($backup['created_at'] < $cutoff && @unlink($backup['path'])) {
            $deleted++;
        }
    }
    return $deleted;
}

/**
 * Voert een volledige back-uprun uit: maakt een nieuwe back-up en ruimt
 * daarna back-ups op die ouder zijn dan BACKUP_RETENTION_MONTHS. Wordt
 * zowel vanuit het beheerpaneel (handmatig) als vanuit cron/backup_cron.php
 * (wekelijks, automatisch) aangeroepen.
 */
function run_backup(): array
{
    $filename = create_backup();
    $deleted = purge_old_backups();
    return ['created' => $filename, 'deleted' => $deleted];
}

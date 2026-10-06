<?php
/**
 * Startpunt voor de wekelijkse back-up via een cronjob.
 *
 * Op shared hosting stel je in je hosting-controlepaneel (DirectAdmin/cPanel/
 * Plesk) een cronjob in die dit script wekelijks aanroept:
 *
 *   - Meestal via "URL ophalen" (wget/curl):
 *     https://jouw-domein.nl/cron/backup_cron.php?key=JOUW_BACKUP_CRON_KEY
 *     De sleutel stel je zelf in via BACKUP_CRON_KEY in config.local.php.
 *
 *   - Heeft je host wel PHP-CLI beschikbaar in de cronjob-instelling, dan
 *     kan de cronjob dit script ook direct aanroepen, bv.
 *     "php /pad/naar/cron/backup_cron.php" — dan is geen sleutel nodig,
 *     want alleen de hostingomgeving zelf kan dit starten.
 *
 * Zie het hoofdstuk "Back-ups" in INSTALL.md voor de volledige uitleg.
 */

require_once __DIR__ . '/../includes/backup.php';

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    $key = (string) ($_GET['key'] ?? '');
    if ($key === '' || BACKUP_CRON_KEY === 'wijzig_deze_geheime_sleutel' || !hash_equals(BACKUP_CRON_KEY, $key)) {
        http_response_code(403);
        exit("Niet toegestaan.\n");
    }
}

try {
    $result = run_backup();
    echo "Back-up aangemaakt: {$result['created']}\n";
    // Alleen in CLI het volledige pad tonen; via de URL geen serverpaden prijsgeven.
    $dirStatus = backup_dir_status();
    if (PHP_SAPI === 'cli') {
        echo "Opgeslagen in: {$dirStatus['dir']}\n";
    }
    if ($dirStatus['fallback']) {
        echo "LET OP: BACKUP_DIR is niet bruikbaar; back-up staat in de terugvalmap backups/ binnen de websitemap. Zie Beheerpaneel -> Back-ups.\n";
    }
    if ($result['deleted'] > 0) {
        echo "{$result['deleted']} verlopen back-up(s) verwijderd.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Back-up mislukt: {$e->getMessage()}\n";
}

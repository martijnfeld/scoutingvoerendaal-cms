<?php
/**
 * Startpunt voor alle geplande taken (opruimen, back-ups, ...).
 *
 * Stel in je hosting-controlepaneel (DirectAdmin/cPanel/Plesk) of bij een
 * externe webcron-dienst (bv. cron-job.org) één cronjob in die dit script
 * DAGELIJKS aanroept. Het script bepaalt zelf welke taken aan de beurt zijn:
 * opruimen draait dagelijks, de back-up volgens het interval dat je instelt
 * onder Beheerpaneel -> Back-ups (standaard wekelijks).
 *
 *   - Meestal via "URL ophalen" (wget/curl) of een webcron-dienst:
 *     https://jouw-domein.nl/cron/cron.php?key=JOUW_BACKUP_CRON_KEY
 *     De sleutel stel je zelf in via BACKUP_CRON_KEY in config.local.php.
 *     Het antwoord is platte tekst met één regel per taak; bij een
 *     mislukte taak is de HTTP-status 500 (handig voor foutmeldingen van
 *     een webcron-dienst).
 *
 *   - Heeft je host wel PHP-CLI beschikbaar in de cronjob-instelling, dan
 *     kan de cronjob dit script ook direct aanroepen, bv.
 *     "php /pad/naar/cron/cron.php" — dan is geen sleutel nodig, want
 *     alleen de hostingomgeving zelf kan dit starten.
 *
 * Optioneel: ?job=backup (of: php cron.php backup) draait alleen die taak,
 * en &force=1 (CLI: --force) slaat de beurtcontrole over.
 *
 * Zie het hoofdstuk "Geplande taken" in INSTALL.md voor de volledige uitleg.
 */

require_once __DIR__ . '/../includes/cron.php';

header('Content-Type: text/plain; charset=utf-8');

// Externe diensten zoals cron-job.org verbreken na ~30 s de verbinding; de
// taken moeten dan gewoon doorlopen in plaats van halverwege af te breken.
ignore_user_abort(true);

$cli = PHP_SAPI === 'cli';

if (!$cli) {
    $key = (string) ($_GET['key'] ?? '');
    if ($key === '' || BACKUP_CRON_KEY === 'wijzig_deze_geheime_sleutel' || !hash_equals(BACKUP_CRON_KEY, $key)) {
        http_response_code(403);
        exit("Niet toegestaan.\n");
    }
}

// Heartbeat voor Systeemcontrole: alleen dit script zet hem (niet de terugval
// via de inlogpagina), dus hij zegt of de cronjob echt wordt aangeroepen.
try {
    set_setting('cron_last_call', (string) time());
} catch (Throwable $e) {
    // Bijzaak; de taken zelf gaan voor.
}

$only = null;
$force = false;
if ($cli) {
    $args = array_slice($_SERVER['argv'] ?? [], 1);
    $force = in_array('--force', $args, true);
    $names = array_values(array_filter($args, fn($a) => $a !== '' && $a[0] !== '-'));
    $only = $names ?: null;
} else {
    $force = ($_GET['force'] ?? '') === '1';
    $job = (string) ($_GET['job'] ?? '');
    $only = $job !== '' ? [$job] : null;
}

$failed = false;
try {
    $results = run_cron($only, $force);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Cron mislukt: ' . $e->getMessage() . "\n");
}

foreach ($results as $r) {
    echo strtoupper($r['status']) . ' ' . $r['label'] . ($r['message'] !== '' ? ': ' . $r['message'] : '') . "\n";
    if ($r['status'] === 'error') {
        $failed = true;
    }
}
if (!$results) {
    echo "Geen taken gevonden" . ($only ? ' voor: ' . implode(', ', $only) : '') . ".\n";
}

if ($failed) {
    http_response_code(500);
}

// Alleen in CLI het volledige pad tonen; via de URL geen serverpaden prijsgeven.
if ($cli) {
    try {
        $dirStatus = backup_dir_status();
        if ($dirStatus['fallback']) {
            echo "LET OP: BACKUP_DIR is niet bruikbaar; back-ups staan in de terugvalmap backups/ binnen de websitemap. Zie Beheerpaneel -> Back-ups.\n";
        }
    } catch (Throwable $e) {
        // Back-upmap onbruikbaar: de back-uptaak meldt dat zelf al.
    }
}

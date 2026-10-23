<?php
/**
 * Centrale taakplanner voor geplande taken (opruimen, back-ups, ...).
 *
 * Er is één cronjob nodig, die dagelijks cron/cron.php aanroept (hostingpaneel
 * of externe webcron-dienst). Bij elke aanroep bepaalt run_cron() zelf welke
 * taken aan de beurt zijn: per taak staat in de settings-tabel wanneer die
 * voor het laatst succesvol draaide (cron_last_run_<taak>). Een vaker of
 * onregelmatig aangeroepen cron kan dus nooit kwaad, en een taak met een
 * lange interval (bv. de wekelijkse back-up) hoeft geen eigen cronjob.
 *
 * Een nieuwe geplande taak toevoegen: voeg een item toe aan cron_jobs()
 * (naam, label, interval in seconden of 0 = uitgeschakeld, en een closure die
 * een korte samenvatting teruggeeft of een exception gooit) — verder is er
 * niets te regelen.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/backup.php';

/**
 * Een taak is "aan de beurt" als de laatste run minstens (interval - marge)
 * geleden is. De marge voorkomt dat een dagelijkse cron die elke dag een paar
 * minuten eerder dan 24 uur na de vorige run binnenkomt (of een back-up die
 * zelf minuten duurt) een wekelijkse taak een hele dag laat opschuiven.
 */
const CRON_DUE_TOLERANCE = 43200;

/** Dagelijks opruimen; ook gebruikt als terugval vanaf admin/login.php (zie cron_run_cleanup_fallback()). */
const CRON_CLEANUP_INTERVAL = 86400;

/** Beschikbare back-upintervallen (dagen => label); 0 = automatisch back-uppen uit. */
const BACKUP_INTERVAL_OPTIONS = [
    1 => 'Dagelijks',
    7 => 'Wekelijks',
    14 => 'Elke twee weken',
    30 => 'Maandelijks',
    0 => 'Niet automatisch',
];

/** Ingestelde back-upinterval in dagen (instelling backup_interval_days; standaard 7). */
function backup_interval_days(): int
{
    $days = (int) get_setting('backup_interval_days', '7');
    return array_key_exists($days, BACKUP_INTERVAL_OPTIONS) ? $days : 7;
}

/**
 * Ruimt alles op wat een bewaartermijn heeft. Elke stap staat op zichzelf:
 * een fout in één stap houdt de andere niet tegen (de eerste fout wordt na
 * afloop alsnog gegooid, zodat de cronrun als mislukt geldt).
 */
function run_cleanup(): string
{
    $steps = [
        'login-pogingen' => 'purge_old_login_attempts',
        'inloggeschiedenis' => 'purge_old_admin_logins',
        'auditlog' => 'purge_old_audit_log',
        'back-ups' => 'purge_old_backups',
    ];
    $parts = [];
    $errors = [];
    foreach ($steps as $label => $fn) {
        try {
            $parts[] = $label . ': ' . (int) $fn();
        } catch (Throwable $e) {
            $errors[] = $label . ' (' . $e->getMessage() . ')';
        }
    }
    if ($errors) {
        throw new RuntimeException('Opruimen mislukt voor ' . implode(', ', $errors) . '. Verwijderd: ' . implode(', ', $parts));
    }
    return 'Verwijderd — ' . implode(', ', $parts);
}

/** Maakt een back-up en ruimt daarbij verlopen back-ups op. */
function run_scheduled_backup(): string
{
    $result = run_backup();
    audit_log('backup', 'backup', null, 'Back-up "' . $result['created'] . '" aangemaakt via cron', ['id' => null, 'username' => 'systeem']);
    $message = 'Back-up aangemaakt: ' . $result['created'];
    if ($result['deleted'] > 0) {
        $message .= ' (' . $result['deleted'] . ' verlopen back-up(s) verwijderd)';
    }
    return $message;
}

/**
 * Alle geplande taken: naam => ['label', 'interval' (seconden, 0 = uit), 'run'].
 *
 * @return array<string, array{label: string, interval: int, run: callable}>
 */
function cron_jobs(): array
{
    return [
        'cleanup' => [
            'label' => 'Opruimen',
            'interval' => CRON_CLEANUP_INTERVAL,
            'run' => 'run_cleanup',
        ],
        'backup' => [
            'label' => 'Back-up',
            'interval' => backup_interval_days() * 86400,
            'run' => 'run_scheduled_backup',
        ],
    ];
}

/** Tijdstip van de laatste geslaagde run van een taak (unix-tijd), of null. */
function cron_last_run(string $name): ?int
{
    $value = get_setting('cron_last_run_' . $name);
    return ctype_digit($value) && $value !== '' ? (int) $value : null;
}

/** Laatste resultaatmelding van een taak ("ok: ..." of "fout: ..."), of ''. */
function cron_last_status(string $name): string
{
    return get_setting('cron_last_status_' . $name);
}

function cron_job_is_due(array $job, ?int $lastRun, int $now): bool
{
    if ($job['interval'] <= 0) {
        return false;
    }
    return $lastRun === null || $now - $lastRun >= $job['interval'] - CRON_DUE_TOLERANCE;
}

/**
 * Pakt een exclusieve, niet-blokkerende lock zodat twee gelijktijdige aanroepen
 * (webcron die opnieuw probeert na een time-out, cron + terugval) nooit
 * dezelfde taak tegelijk draaien. Geeft false terug als er al een run bezig is;
 * kan het lockbestand niet worden aangemaakt, dan draaien we zonder lock.
 *
 * @return resource|true|false
 */
function cron_acquire_lock()
{
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $handle = @fopen($dir . '/cron.lock', 'c');
    if ($handle === false) {
        return true;
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return false;
    }
    return $handle;
}

/**
 * Draait alle taken die aan de beurt zijn (of alleen de genoemde in $only).
 * $force slaat de beurtcontrole over (niet voor uitgeschakelde taken).
 *
 * @param string[]|null $only
 * @return array<int, array{job: string, label: string, status: string, message: string}>
 *         status: 'ok', 'error', 'skipped' (nog niet aan de beurt), 'disabled' of 'busy'
 */
function run_cron(?array $only = null, bool $force = false): array
{
    $lock = cron_acquire_lock();
    if ($lock === false) {
        return [['job' => '*', 'label' => 'Cron', 'status' => 'busy', 'message' => 'Er draait al een andere cronrun.']];
    }

    // Een taak mag de webserver-/PHP-tijdslimiet niet halverwege afbreken.
    @set_time_limit(0);

    $results = [];
    $now = time();
    foreach (cron_jobs() as $name => $job) {
        if ($only !== null && !in_array($name, $only, true)) {
            continue;
        }
        $result = ['job' => $name, 'label' => $job['label'], 'status' => 'skipped', 'message' => ''];
        if ($job['interval'] <= 0) {
            $result['status'] = 'disabled';
            $result['message'] = 'Staat uit.';
        } elseif ($force || cron_job_is_due($job, cron_last_run($name), $now)) {
            try {
                $result['message'] = (string) call_user_func($job['run']);
                $result['status'] = 'ok';
                set_setting('cron_last_run_' . $name, (string) time());
                set_setting('cron_last_status_' . $name, 'ok: ' . substr($result['message'], 0, 400));
            } catch (Throwable $e) {
                $result['status'] = 'error';
                $result['message'] = $e->getMessage();
                // Geen cron_last_run: de volgende aanroep probeert het opnieuw.
                try {
                    set_setting('cron_last_status_' . $name, 'fout: ' . substr($e->getMessage(), 0, 400));
                } catch (Throwable $ignored) {
                    // Statusmelding is bijzaak.
                }
            }
        } else {
            $result['message'] = 'Nog niet aan de beurt.';
        }
        $results[] = $result;
    }

    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $results;
}

/**
 * Terugval voor sites waar de cronjob (nog) niet is ingesteld: voert de
 * opruimtaak hooguit eens per dag uit, aangeroepen vanaf admin/login.php.
 * Zo blijven bewaartermijnen (IP-adressen!) ook zonder cron gehandhaafd.
 * Mag nooit het inloggen verstoren.
 */
function cron_run_cleanup_fallback(): void
{
    try {
        // Goedkope controle eerst, zodat gewone bezoeken geen lockbestand openen.
        if (cron_job_is_due(cron_jobs()['cleanup'], cron_last_run('cleanup'), time())) {
            run_cron(['cleanup']);
        }
    } catch (Throwable $e) {
        // Opruimen is bijzaak; inloggen gaat voor.
    }
}

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/backup.php';

$pageTitle = 'Back-ups';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $result = run_backup();
            $message = 'Back-up aangemaakt: ' . $result['created'] . '.';
            if ($result['deleted'] > 0) {
                $message .= ' ' . $result['deleted'] . ' verlopen back-up(s) verwijderd.';
            }
            flash_set('success', $message);
        } catch (Throwable $e) {
            flash_set('error', 'Back-up maken is mislukt: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        // Alleen .zip-bestanden uit de bekende back-upmappen (zie backup_known_dirs()).
        $path = find_backup_path((string) ($_POST['name'] ?? ''));
        if ($path !== null) {
            if (@unlink($path)) {
                flash_set('success', 'Back-up verwijderd.');
            } else {
                flash_set('error', 'Kon de back-up niet verwijderen (controleer de schrijfrechten).');
            }
        }
    }
    header('Location: backups.php');
    exit;
}

try {
    $dirStatus = backup_dir_status();
    $dirError = null;
} catch (RuntimeException $e) {
    $dirStatus = null;
    $dirError = $e->getMessage();
}
$backups = list_backups();
$hasLegacy = (bool) array_filter($backups, fn($b) => $b['legacy']);

// Gegevens voor de cronjob-uitleg hieronder. Basisadres: site_url, of anders
// afgeleid van het adres waarop het beheerpaneel nu draait.
$cronKeySet = BACKUP_CRON_KEY !== 'wijzig_deze_geheime_sleutel';
$cronBase = rtrim(trim(get_setting('site_url')), '/');
if ($cronBase === '') {
    $cronBase = (request_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'jouw-domein.nl')
        . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/backups.php'))), '/');
}
$cronUrl = $cronBase . '/cron/backup_cron.php?key=' . ($cronKeySet ? rawurlencode(BACKUP_CRON_KEY) : 'JOUW_BACKUP_CRON_KEY');
$cronScriptPath = realpath(__DIR__ . '/../cron/backup_cron.php') ?: dirname(__DIR__) . '/cron/backup_cron.php';
// Wekelijks op zondag om 03:15. In een crontab-regel betekent % een nieuwe
// regel, dus die moet daar als \% geschreven worden.
$cronSchedule = '15 3 * * 0';
$cronUrlTab = str_replace('%', '\\%', $cronUrl);
$cronLines = [
    'cli'  => $cronSchedule . ' /usr/local/bin/php ' . $cronScriptPath . ' >/dev/null 2>&1',
    'wget' => $cronSchedule . ' wget -q -O /dev/null "' . $cronUrlTab . '"',
    'curl' => $cronSchedule . ' curl -fsS -o /dev/null "' . $cronUrlTab . '"',
];

include __DIR__ . '/includes/layout_top.php';
?>
<p>
  Een back-up is één zipbestand met een volledige databasedump (<code>database.sql</code>) en alle
  sitebestanden (inclusief geüploade foto's en PDF's). Back-ups ouder dan <?= (int) BACKUP_RETENTION_MONTHS ?>
  maanden worden automatisch verwijderd, ook bij de wekelijkse geplande back-up.
</p>
<p class="muted">
  Naast handmatig back-uppen hieronder kan er wekelijks automatisch een back-up worden gemaakt via een
  cronjob in je hosting-controlepaneel of via een externe dienst zoals cron-job.org — zie
  "Automatische wekelijkse back-up" onderaan deze pagina.
</p>

<div class="admin-card">
  <h2>Opslaglocatie</h2>
  <?php if ($dirError !== null): ?>
    <p class="admin-flash admin-flash-error" style="margin:0;">
      <?= e($dirError) ?> Back-ups maken (en daarmee ook automatisch bijwerken) is zo niet mogelijk.
    </p>
  <?php else: ?>
    <p>Back-ups worden opgeslagen in: <code><?= e($dirStatus['dir']) ?></code></p>
    <?php if ($dirStatus['fallback']): ?>
      <p class="admin-flash admin-flash-error" style="margin:0;">
        <strong>Let op:</strong> de ingestelde back-upmap <code><?= e($dirStatus['configured']) ?></code> kon niet worden
        aangemaakt of is niet beschrijfbaar (op veel hostingpakketten mag de website niet buiten de websitemap schrijven).
        Back-ups staan daarom nu in de map <code>backups/</code> <strong>binnen de websitemap</strong>. Die is wel
        afgeschermd via <code>.htaccess</code>, maar dat werkt niet op elke webserver (bv. nginx), en een back-up bevat je
        databasegegevens en wachtwoord-hashes. Maak de map hierboven zelf aan via FTP/het controlepaneel (beschrijfbaar
        voor de webserver), of stel een andere map buiten de websitemap in via <code>BACKUP_DIR</code> in
        <code>config.local.php</code> — zie het hoofdstuk "Back-ups" in <code>INSTALL.md</code>.
      </p>
    <?php elseif ($dirStatus['inside_webroot']): ?>
      <p class="admin-flash admin-flash-error" style="margin:0;">
        <strong>Let op:</strong> deze map ligt binnen de websitemap en is mogelijk via de browser bereikbaar. Een back-up
        bevat je databasegegevens en wachtwoord-hashes; stel bij voorkeur via <code>BACKUP_DIR</code> in
        <code>config.local.php</code> een map buiten de websitemap in — zie het hoofdstuk "Back-ups" in <code>INSTALL.md</code>.
      </p>
    <?php else: ?>
      <p class="muted" style="margin:0;">Deze map staat buiten de websitemap en is dus niet via de browser bereikbaar.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($hasLegacy): ?>
    <p class="muted" style="margin:12px 0 0;">
      Back-ups gemarkeerd met "oude locatie" staan nog in de map <code>backups/</code> binnen de websitemap (van vóór
      deze wijziging). Ze worden na <?= (int) BACKUP_RETENTION_MONTHS ?> maanden vanzelf opgeruimd; wil je ze eerder weg
      hebben, download ze dan en verwijder ze hier.
    </p>
  <?php endif; ?>
</div>

<div class="top-actions">
  <form method="post" data-confirm="Nu een back-up maken? Dit kan, afhankelijk van de hoeveelheid bestanden, enkele minuten duren." style="display:inline;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <button type="submit" class="btn">Nu back-uppen</button>
  </form>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Bestand</th><th>Grootte</th><th>Aangemaakt</th><th>Acties</th></tr>
    <?php foreach ($backups as $backup): ?>
    <tr>
      <td><?= e($backup['name']) ?><?php if ($backup['legacy']): ?> <span class="muted">(oude locatie)</span><?php endif; ?></td>
      <td><?= e(format_bytes($backup['size'])) ?></td>
      <td><?= e(date('d-m-Y H:i', $backup['created_at'])) ?></td>
      <td class="actions">
        <a class="btn btn-small" href="backup_download.php?name=<?= urlencode($backup['name']) ?>">Downloaden</a>
        <form method="post" data-confirm="Deze back-up verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="name" value="<?= e($backup['name']) ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$backups): ?>
    <tr><td colspan="4" class="muted">Nog geen back-ups. Klik op "Nu back-uppen" om de eerste te maken.</td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="admin-card">
  <h2>Automatische wekelijkse back-up</h2>
  <?php if (!$cronKeySet): ?>
    <p class="admin-flash admin-flash-error">
      <strong>Let op:</strong> <code>BACKUP_CRON_KEY</code> staat nog op de standaardwaarde, dus het cronscript weigert via
      een URL te draaien. Zet in <code>config.local.php</code> een lange, willekeurige waarde, bv.
      <code>define('BACKUP_CRON_KEY', '<?= e(bin2hex(random_bytes(24))) ?>');</code> en laad deze pagina opnieuw — dan
      staat de juiste URL hieronder ingevuld. (Voor optie 1, PHP-CLI, is geen sleutel nodig.)
    </p>
  <?php endif; ?>
  <p>
    Kies één van de onderstaande manieren. Elke run maakt een nieuwe back-up en ruimt back-ups ouder dan
    <?= (int) BACKUP_RETENTION_MONTHS ?> maanden op. Het voorbeeldschema <code><?= e($cronSchedule) ?></code> betekent
    <em>elke zondag om 03:15</em> (servertijd).
  </p>

  <h3>1. Cronjob in je hostingpaneel met PHP-CLI (voorkeur)</h3>
  <p>
    In DirectAdmin/cPanel/Plesk onder "Cron Jobs". Gebruikt geen sleutel en heeft geen last van time-outs van de
    webserver. Vul in je paneel de velden zo in:
  </p>
  <table class="admin-table">
    <tr><th>Minuut</th><th>Uur</th><th>Dag v/d maand</th><th>Maand</th><th>Dag v/d week</th></tr>
    <tr><td><code>15</code></td><td><code>3</code></td><td><code>*</code></td><td><code>*</code></td><td><code>0</code> (zondag)</td></tr>
  </table>
  <p>Opdracht:</p>
  <pre class="cron-code"><?= e('/usr/local/bin/php ' . $cronScriptPath) ?></pre>
  <p>Of als volledige crontab-regel:</p>
  <pre class="cron-code"><?= e($cronLines['cli']) ?></pre>
  <p class="muted">
    Het pad naar PHP verschilt per host: vaak <code>/usr/local/bin/php</code>, soms gewoon <code>php</code> of een
    versiespecifiek pad (bv. <code>/opt/alt/php82/usr/bin/php</code>) — je hostingpaneel of helpdesk vermeldt het juiste pad.
  </p>

  <h3>2. Cronjob in je hostingpaneel die de URL ophaalt</h3>
  <p>Als je host geen PHP-CLI in cronjobs aanbiedt, laat de cronjob dan de back-up-URL ophalen met wget of curl:</p>
  <pre class="cron-code"><?= e($cronLines['wget']) ?></pre>
  <pre class="cron-code"><?= e($cronLines['curl']) ?></pre>
  <p class="muted">
    Vul je in je paneel alleen het opdrachtveld in (schema in losse velden), laat dan het schema vooraan weg.
    <code>%</code>-tekens in een crontab-regel moeten als <code>\%</code> geschreven worden; dat is hierboven al gedaan.
  </p>

  <h3>3. Externe dienst: cron-job.org</h3>
  <p>
    Heeft je hostingpakket helemaal geen cronjobs, gebruik dan de gratis dienst
    <a href="https://cron-job.org" target="_blank" rel="noopener noreferrer">cron-job.org</a>:
  </p>
  <ol>
    <li>Maak een (gratis) account aan op cron-job.org en klik op <strong>Create cronjob</strong>.</li>
    <li>Titel: bv. "Back-up <?= e(parse_url($cronBase, PHP_URL_HOST) ?: 'website') ?>".</li>
    <li>URL:
      <pre class="cron-code"><?= e($cronUrl) ?></pre>
    </li>
    <li>Schema (Execution schedule): <strong>Every week</strong>, op zondag om 03:15 — of via
      <em>Custom</em> de crontab-notatie <code><?= e($cronSchedule) ?></code>.</li>
    <li>Zet onder <em>Notifications</em> "Notify me when the execution fails" aan, dan krijg je een mail als de
      back-up mislukt.</li>
    <li>Sla op en gebruik <strong>Test run</strong> om te controleren dat er een back-up verschijnt in de lijst hierboven.</li>
  </ol>
  <p class="muted">
    cron-job.org wacht maximaal 30 seconden op antwoord. Duurt de back-up langer, dan meldt cron-job.org een time-out,
    maar de back-up loopt op de server gewoon door — controleer in de lijst hierboven of hij is aangemaakt. Zet in dat
    geval de foutmelding-notificatie eventueel uit om valse alarmen te voorkomen.
  </p>

  <p class="muted">
    <strong>Houd de URL geheim:</strong> iedereen met deze URL kan back-ups laten maken (niet downloaden). Lekt hij
    uit, verander dan <code>BACKUP_CRON_KEY</code> in <code>config.local.php</code> en pas je cronjob aan.
  </p>
</div>

<div class="admin-card">
  <h2>Terugzetten (restore)</h2>
  <p class="muted">
    Een back-up terugzetten vanuit dit beheerpaneel is nog niet mogelijk — die functie volgt later.
    Wil je nu al een back-up terugzetten, download het zipbestand dan en zet het handmatig terug: importeer
    <code>database.sql</code> via phpMyAdmin en upload de inhoud van de map <code>files/</code> naar de server.
  </p>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

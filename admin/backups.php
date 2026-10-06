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

include __DIR__ . '/includes/layout_top.php';
?>
<p>
  Een back-up is één zipbestand met een volledige databasedump (<code>database.sql</code>) en alle
  sitebestanden (inclusief geüploade foto's en PDF's). Back-ups ouder dan <?= (int) BACKUP_RETENTION_MONTHS ?>
  maanden worden automatisch verwijderd, ook bij de wekelijkse geplande back-up.
</p>
<p class="muted">
  Naast handmatig back-uppen hieronder kan er wekelijks automatisch een back-up worden gemaakt via een
  cronjob in je hosting-controlepaneel. Zie <code>cron/backup_cron.php</code> en het hoofdstuk "Back-ups"
  in <code>INSTALL.md</code> voor hoe je dat instelt.
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
  <h2>Terugzetten (restore)</h2>
  <p class="muted">
    Een back-up terugzetten vanuit dit beheerpaneel is nog niet mogelijk — die functie volgt later.
    Wil je nu al een back-up terugzetten, download het zipbestand dan en zet het handmatig terug: importeer
    <code>database.sql</code> via phpMyAdmin en upload de inhoud van de map <code>files/</code> naar de server.
  </p>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

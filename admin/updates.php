<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/updater.php';

$pageTitle = 'Updates';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'check') {
        fetch_latest_release(true);
        flash_set('success', 'Updatecheck uitgevoerd.');
    } elseif ($action === 'update') {
        $info = fetch_latest_release();
        if (empty($info['ok']) || empty($info['release'])) {
            flash_set('error', 'Kan de release-informatie niet ophalen. Voer eerst een updatecheck uit.');
        } elseif (empty($_POST['confirm_notes'])) {
            flash_set('error', 'Bevestig dat je de releasenotes hebt gelezen voordat je bijwerkt.');
        } else {
            try {
                $result = perform_full_update($info['release']);
                $message = 'Bijgewerkt naar versie ' . $result['version'] . '. Back-up: ' . $result['backup']['created'] . '. '
                    . $result['files_copied'] . ' bestand(en) bijgewerkt.';
                if ($result['migrations']['applied']) {
                    $message .= ' Migraties uitgevoerd: ' . implode(', ', $result['migrations']['applied']) . '.';
                }
                if ($result['migrations']['error']) {
                    flash_set('error', $message . ' LET OP: ' . $result['migrations']['error'] . ' Er staat een back-up klaar op de back-uppagina.');
                } else {
                    flash_set('success', $message);
                }
            } catch (Throwable $e) {
                flash_set('error', 'Bijwerken is mislukt: ' . $e->getMessage() . ' Er staat een back-up klaar op de back-uppagina om op terug te vallen.');
            }
        }
    } elseif ($action === 'migrate') {
        try {
            $result = run_pending_migrations();
            if ($result['error']) {
                flash_set('error', 'Migratie mislukt: ' . $result['error']);
            } elseif ($result['applied']) {
                flash_set('success', 'Migraties uitgevoerd: ' . implode(', ', $result['applied']) . '.');
            } else {
                flash_set('success', 'Geen openstaande migraties.');
            }
        } catch (Throwable $e) {
            flash_set('error', 'Migratie mislukt: ' . $e->getMessage());
        }
    }
    header('Location: updates.php');
    exit;
}

$info = fetch_latest_release();
$release = $info['release'] ?? null;
$hasNewer = $release !== null && update_is_newer($release['version']);
$canAutoUpdate = class_exists('ZipArchive') && (function_exists('curl_init') || ini_get('allow_url_fopen'));

include __DIR__ . '/includes/layout_top.php';
?>
<p>
  Huidige versie: <strong><?= e(APP_VERSION) ?></strong>
  <?php if (GITHUB_REPO === 'jouw-gebruikersnaam/jouw-repo-naam'): ?>
    &mdash; <span class="muted">er is nog geen GitHub-repository ingesteld (<code>GITHUB_REPO</code> in <code>config.php</code>/<code>config.local.php</code>).</span>
  <?php endif; ?>
</p>

<div class="top-actions">
  <form method="post" style="display:inline;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="check">
    <button type="submit" class="btn btn-secondary">Controleer op updates</button>
  </form>
</div>

<?php if (!$info['ok']): ?>
  <div class="admin-card">
    <p class="admin-flash admin-flash-error" style="margin:0;"><?= e($info['error'] ?? 'Kan de updatestatus niet bepalen.') ?></p>
  </div>
<?php elseif (!$hasNewer): ?>
  <div class="admin-card">
    <p>Je gebruikt de nieuwste versie (<?= e($release['version']) ?>).</p>
  </div>
<?php else: ?>
  <div class="admin-card">
    <h2>Versie <?= e($release['version']) ?> beschikbaar</h2>
    <p class="muted">
      Gepubliceerd: <?= $release['published_at'] ? e(date('d-m-Y H:i', strtotime($release['published_at']))) : '—' ?>
      <?php if ($release['html_url']): ?> &middot; <a href="<?= e($release['html_url']) ?>" target="_blank" rel="noopener">bekijk op GitHub</a><?php endif; ?>
    </p>
    <div class="release-notes"><?= render_release_notes($release['body']) ?></div>

    <?php if (!$canAutoUpdate): ?>
      <p class="admin-flash admin-flash-error">
        Automatisch bijwerken is op deze server niet mogelijk (ontbrekende PHP-extensie <code>zip</code>, of geen uitgaande
        HTTPS-verbindingen toegestaan). Download de release handmatig via GitHub en upload de bestanden via FTP/SFTP —
        laat daarbij <code>config.local.php</code>, <code>assets/uploads/</code>, <code>backups/</code> en <code>includes/cache/</code>
        ongewijzigd. Klik daarna hieronder op "Alleen migraties uitvoeren".
      </p>
    <?php else: ?>
      <form method="post" data-confirm="Nu bijwerken naar versie <?= e($release['version']) ?>? Er wordt eerst automatisch een back-up gemaakt.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <div class="field">
          <label class="field-inline-label">
            <input type="checkbox" name="confirm_notes" value="1" required>
            Ik heb de releasenotes hierboven gelezen
          </label>
        </div>
        <button type="submit" class="btn">Nu bijwerken naar <?= e($release['version']) ?></button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h2>Migraties</h2>
  <p class="muted">
    Wordt automatisch uitgevoerd bij "Nu bijwerken" hierboven. Heb je de bestanden van een nieuwe versie handmatig via
    FTP/SFTP geüpload (bv. omdat automatisch bijwerken hier niet kan), klik dan hieronder om openstaande
    database-/bestandsmigraties alsnog uit te voeren.
  </p>
  <form method="post" data-confirm="Openstaande migraties uitvoeren?" style="display:inline;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="migrate">
    <button type="submit" class="btn btn-secondary">Alleen migraties uitvoeren</button>
  </form>
</div>

<div class="admin-card">
  <h2>Hoe werkt bijwerken?</h2>
  <p class="muted">
    "Nu bijwerken" maakt eerst automatisch een volledige back-up (zoals bij <a href="backups.php">Back-ups</a>), haalt
    daarna de nieuwste release van GitHub op en overschrijft de sitebestanden. <code>config.local.php</code>,
    <code>assets/uploads/</code>, <code>backups/</code>, <code>includes/cache/</code> en <code>install.php</code> worden
    nooit aangeraakt. Tot slot worden nieuwe database-/bestandsmigraties automatisch uitgevoerd. Mislukt een stap, dan
    staat er altijd een verse back-up klaar op de back-uppagina om op terug te vallen.
  </p>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

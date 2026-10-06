<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/checks.php';

$pageTitle = 'Systeemcontrole';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (($_POST['action'] ?? '') === 'external') {
        // In de sessie bewaren (POST/redirect/GET); blijft zichtbaar tot de volgende test.
        $_SESSION['check_external'] = ['ran_at' => time(), 'results' => checks_external()];
    }
    header('Location: controle.php#extern');
    exit;
}

$sections = run_system_checks();
$external = $_SESSION['check_external'] ?? null;
if ($external !== null) {
    $sections = ['Externe verbindingen' => $external['results']] + $sections;
}
$probes = checks_browser_probes();

$counts = ['error' => 0, 'warn' => 0];
foreach ($sections as $results) {
    foreach ($results as $result) {
        if (isset($counts[$result['status']])) {
            $counts[$result['status']]++;
        }
    }
}

include __DIR__ . '/includes/layout_top.php';
?>
<p>
  Deze pagina controleert of de server goed is ingericht voor de website: PHP-versie en -extensies, database,
  configuratie, back-ups, updates, schrijfrechten en hoe de webserver bestanden uitlevert.
</p>

<div class="admin-card check-summary">
  <span class="check-status check-error"><span data-check-count="error"><?= $counts['error'] ?></span> fout(en)</span>
  <span class="check-status check-warn"><span data-check-count="warn"><?= $counts['warn'] ?></span> aandachtspunt(en)</span>
  <span class="muted">De webservertests hieronder lopen in je browser en worden na het laden meegeteld.</span>
</div>

<?php foreach ($sections as $title => $results): ?>
<div class="admin-card"<?= $title === 'Externe verbindingen' ? ' id="extern"' : '' ?>>
  <h2><?= e($title) ?></h2>
  <?php if ($title === 'Externe verbindingen'): ?>
    <p class="muted">Getest op <?= e(date('d-m-Y H:i', (int) $external['ran_at'])) ?>.</p>
  <?php endif; ?>
  <table class="admin-table check-table">
    <?php foreach ($results as $result): ?>
    <tr class="check-row-<?= e($result['status']) ?>">
      <td><span class="check-status check-<?= e($result['status']) ?>"><?= e(CHECK_STATUS_LABELS[$result['status']]) ?></span></td>
      <td><?= e($result['label']) ?></td>
      <td>
        <?= e($result['detail']) ?>
        <?php if ($result['link']): ?> <a href="<?= e($result['link']['href']) ?>"><?= e($result['link']['text']) ?></a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endforeach; ?>

<div class="admin-card">
  <h2>Webserver</h2>
  <p class="muted">
    Je browser vraagt deze adressen zelf op, zonder in te loggen, en ziet dus precies wat een bezoeker ziet.
  </p>
  <noscript><p class="admin-flash admin-flash-error">Zet JavaScript aan om deze tests uit te voeren.</p></noscript>
  <table class="admin-table check-table">
    <?php foreach ($probes as $probe): ?>
    <tr class="check-row-pending" data-probe-url="<?= e($probe['url']) ?>" data-probe-expect="<?= e($probe['expect']) ?>">
      <td><span class="check-status check-pending"><?= e(CHECK_STATUS_LABELS['pending']) ?></span></td>
      <td><?= e($probe['label']) ?></td>
      <td class="check-detail"></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="admin-card">
  <h2>Externe verbindingen testen</h2>
  <p class="muted">
    Maakt echt verbinding met GitHub (updates) en met de Scoutdash-feed van elke speltak. Dit kan enkele seconden
    duren. De updatestatus op de pagina Updates wordt daarbij meteen ververst.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="external">
    <button type="submit" class="btn btn-secondary">Externe verbindingen testen</button>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

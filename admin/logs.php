<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Logs';

// Tabs: een nieuwe soort log (bv. inlogpogingen) is een extra regel hier
// plus een eigen blok hieronder.
$logTabs = [
    'audit' => 'Auditlog',
];
$activeTab = $_GET['type'] ?? 'audit';
if (!isset($logTabs[$activeTab])) {
    $activeTab = 'audit';
}


$perPage = 50;
$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = [
    'username' => (string) ($_GET['gebruiker'] ?? ''),
    'entiteit' => (string) ($_GET['onderwerp'] ?? ''),
];
$log = audit_log_list($perPage, ($page - 1) * $perPage, $filters);
$pages = max(1, (int) ceil($log['total'] / $perPage));
$options = audit_log_filter_options();

$pageUrl = static function (int $p) use ($activeTab, $filters): string {
    return 'logs.php?' . http_build_query([
        'type' => $activeTab,
        'gebruiker' => $filters['username'],
        'onderwerp' => $filters['entiteit'],
        'p' => $p,
    ]);
};

include __DIR__ . '/includes/layout_top.php';
?>
<p>Hier zie je wie wanneer welke wijziging heeft gedaan in het beheerpaneel.</p>

<ul class="nav nav-tabs" style="margin-bottom:16px;">
  <?php foreach ($logTabs as $key => $label): ?>
  <li class="nav-item"><a class="nav-link<?= $key === $activeTab ? ' active' : '' ?>" href="logs.php?type=<?= e($key) ?>"><?= e($label) ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($activeTab === 'audit'): ?>
<div class="admin-card">
  <h2>Auditlog</h2>
  <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
    <input type="hidden" name="type" value="audit">
    <select name="gebruiker" aria-label="Gebruiker">
      <option value="">Alle gebruikers</option>
      <?php foreach ($options['users'] as $u): ?>
      <option value="<?= e($u) ?>"<?= $u === $filters['username'] ? ' selected' : '' ?>><?= e($u) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="onderwerp" aria-label="Onderwerp">
      <option value="">Alle onderwerpen</option>
      <?php foreach ($options['entiteiten'] as $en): ?>
      <option value="<?= e($en) ?>"<?= $en === $filters['entiteit'] ? ' selected' : '' ?>><?= e($en) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary btn-small">Filteren</button>
  </form>
  <?php if (!$log['rows']): ?>
  <p class="muted">Geen acties gevonden.</p>
  <?php else: ?>
  <p class="muted"><?= $log['total'] ?> actie<?= $log['total'] === 1 ? '' : 's' ?> in de laatste <?= max(1, (int) AUDIT_LOG_RETENTION_MONTHS) ?> maanden, nieuwste eerst. Oudere acties worden automatisch verwijderd.</p>
  <table class="admin-table">
    <tr><th>Tijdstip</th><th>Gebruiker</th><th>Actie</th><th>Onderwerp</th><th>Omschrijving</th><th>IP-adres</th></tr>
    <?php foreach ($log['rows'] as $row): ?>
    <tr>
      <td><?= e($row['aangemaakt_op']) ?></td>
      <td><?= e($row['username']) ?></td>
      <td><?= e($row['actie']) ?></td>
      <td><?= e($row['entiteit']) ?><?= $row['entiteit_id'] !== null ? ' <span class="muted">#' . (int) $row['entiteit_id'] . '</span>' : '' ?></td>
      <td><?= e($row['omschrijving']) ?></td>
      <td><?= e($row['ip_address']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($pages > 1): ?>
  <p>
    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>">&laquo; Nieuwer</a><?php endif; ?>
    <span class="muted">Pagina <?= $page ?> van <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>">Ouder &raquo;</a><?php endif; ?>
  </p>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

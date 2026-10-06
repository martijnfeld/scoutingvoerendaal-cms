<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/scoutdash.php';
require_login();

$pageTitle = 'Speltakken';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'clear_cache') {
        opkomsten_clear_cache();
        flash_set('success', 'Opkomsten-cache geleegd. Bij het volgende bezoek wordt alles opnieuw bij Scoutdash opgehaald.');
        header('Location: speltakken.php');
        exit;
    } elseif ($action === 'delete' && $id) {
        $stmt = db()->prepare('DELETE FROM speltakken WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash_set('success', 'Speltak verwijderd.');
    } elseif ($action === 'move' && $id) {
        $dir = $_POST['dir'] ?? '';
        $sp = get_speltak($id);
        if ($sp) {
            $sql = $dir === 'up'
                ? 'SELECT * FROM speltakken WHERE (volgorde < :v OR (volgorde = :v AND id < :id)) ORDER BY volgorde DESC, id DESC LIMIT 1'
                : 'SELECT * FROM speltakken WHERE (volgorde > :v OR (volgorde = :v AND id > :id)) ORDER BY volgorde ASC, id ASC LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute(['v' => $sp['volgorde'], 'id' => $sp['id']]);
            $neighbor = $stmt->fetch();
            if ($neighbor) {
                $upd = db()->prepare('UPDATE speltakken SET volgorde = :v WHERE id = :id');
                $upd->execute(['v' => $neighbor['volgorde'], 'id' => $sp['id']]);
                $upd->execute(['v' => $sp['volgorde'], 'id' => $neighbor['id']]);
            }
        }
    } elseif ($action === 'toggle' && $id) {
        db()->prepare('UPDATE speltakken SET actief = 1 - actief WHERE id = :id')->execute(['id' => $id]);
    }
    header('Location: speltakken.php');
    exit;
}

$speltakken = get_speltakken();
$opkomstenCache = opkomsten_cache_overview();
include __DIR__ . '/includes/layout_top.php';
?>
<p>Beheer hier de speltakken: naam, kleur, leeftijden, volgorde, toelichting, leiding en de link naar de opkomsten-feed (JSON).</p>
<div class="top-actions">
  <a href="speltak_form.php" class="btn">+ Nieuwe speltak</a>
  <form method="post" data-confirm="Opkomsten-cache legen? Bij het eerstvolgende bezoek wordt alles opnieuw bij Scoutdash opgehaald." style="display:inline;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear_cache">
    <button type="submit" class="btn btn-secondary"><?= tabler_icon('rotate') ?> Opkomsten-cache legen</button>
  </form>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Volgorde</th><th>Speltak</th><th>Leeftijd</th><th>Wanneer / leiding</th><th>Feed</th><th>Status</th><th>Acties</th></tr>
    <?php foreach ($speltakken as $sp): ?>
    <tr>
      <td>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $sp['id'] ?>">
          <input type="hidden" name="dir" value="up">
          <button type="submit" class="btn btn-secondary btn-small" title="Omhoog" aria-label="Omhoog"><?= tabler_icon('arrow-up') ?></button>
        </form>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $sp['id'] ?>">
          <input type="hidden" name="dir" value="down">
          <button type="submit" class="btn btn-secondary btn-small" title="Omlaag" aria-label="Omlaag"><?= tabler_icon('arrow-down') ?></button>
        </form>
      </td>
      <td><span class="color-swatch" style="background:<?= e($sp['kleur']) ?>"></span><strong><?= e($sp['naam']) ?></strong></td>
      <td><?= e($sp['leeftijd'] ?: '-') ?></td>
      <td><?= e(trim(implode(' · ', array_filter([$sp['dag_tijd'], $sp['leiding']])))) ?: '<span class="muted">-</span>' ?></td>
      <td><?= $sp['feed_url'] ? '<span title="' . e($sp['feed_url']) . '">' . tabler_icon('check') . ' ingesteld</span>' : '<span class="muted">geen</span>' ?></td>
      <td>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $sp['id'] ?>">
          <button type="submit" class="btn btn-small <?= $sp['actief'] ? '' : 'btn-secondary' ?>" title="Klik om te wisselen"><?= $sp['actief'] ? 'Actief ' . tabler_icon('check') : 'Inactief' ?></button>
        </form>
      </td>
      <td class="actions">
        <a class="btn btn-small" href="speltak_form.php?id=<?= $sp['id'] ?>">Bewerken</a>
        <form method="post" data-confirm="Deze speltak verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $sp['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$speltakken): ?>
    <tr><td colspan="7" class="muted">Nog geen speltakken.</td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">Opkomsten-cache</h2>
  <?php if ($opkomstenCache === null): ?>
  <p class="muted">Nog niets gecached. Bij het eerstvolgende bezoek aan de website wordt het programma per speltak bij Scoutdash opgehaald.</p>
  <?php else:
      $cacheAge = time() - $opkomstenCache['generated_at'];
      $ttlLeft = OPKOMSTEN_CACHE_TTL - $cacheAge;
  ?>
  <p class="muted">
    Cache laatst ververst op <strong><?= e(date('d-m-Y H:i:s', $opkomstenCache['generated_at'])) ?></strong>
    &middot; <?= $ttlLeft > 0 ? 'nog ' . ceil($ttlLeft / 60) . ' min geldig' : 'verlopen, wordt bij het eerstvolgende bezoek ververst' ?>.
  </p>
  <table class="admin-table">
    <tr><th>Speltak</th><th>Laatst opgehaald bij Scoutdash</th><th>Aantal items</th><th>Status</th></tr>
    <?php foreach ($speltakken as $sp):
        if (empty($sp['feed_url'])) continue;
        $entry = $opkomstenCache['data'][$sp['slug']] ?? null;
    ?>
    <tr>
      <td><strong><?= e($sp['naam']) ?></strong></td>
      <td><?= ($entry && $entry['fetched_at']) ? e(date('d-m-Y H:i:s', $entry['fetched_at'])) : '<span class="muted">nog niet opgehaald</span>' ?></td>
      <td><?= $entry ? count($entry['items']) : '-' ?></td>
      <td>
        <?php if (!$sp['actief']): ?>
          <span class="muted">inactief &mdash; wordt niet opgehaald</span>
        <?php elseif (!$entry): ?>
          <span class="muted">geen data</span>
        <?php elseif ($entry['ok']): ?>
          <?= tabler_icon('check') ?> up-to-date
        <?php else: ?>
          <?= tabler_icon('alert-triangle') ?> Scoutdash niet bereikbaar, toont laatst bekende programma
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!array_filter($speltakken, fn($sp) => !empty($sp['feed_url']))): ?>
    <tr><td colspan="4" class="muted">Geen enkele speltak heeft een opkomsten-feed ingesteld.</td></tr>
    <?php endif; ?>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

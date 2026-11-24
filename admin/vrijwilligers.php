<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/content_order.php';
require_login();

$pageTitle = 'Vrijwilligers';
$tabs = ['groep' => 'Per groep', 'personen' => 'Personen', 'groepen' => 'Groepen'];
$tab = $_POST['tab'] ?? $_GET['tab'] ?? 'groep';
$tab = isset($tabs[$tab]) ? $tab : 'groep';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $dir = $_POST['dir'] ?? '';

    if ($action === 'move_rol' && $id) {
        move_content_record('vrijwilliger_rollen', $id, $dir);
    } elseif ($action === 'move_groep' && $id) {
        vrijwilliger_move_overzicht_item('groep', $id, $dir);
    } elseif ($action === 'move_speltakken') {
        vrijwilliger_move_overzicht_item('speltakken', 0, $dir);
    } elseif ($action === 'toggle_persoon' && $id) {
        db()->prepare('UPDATE vrijwilligers SET actief = 1 - actief WHERE id = :id')->execute(['id' => $id]);
        $v = get_vrijwilliger($id);
        if ($v) {
            audit_log('toggle', 'vrijwilliger', $id, 'Vrijwilliger "' . $v['naam'] . '" ' . ($v['actief'] ? 'geactiveerd' : 'gedeactiveerd'));
        }
    } elseif ($action === 'delete_persoon' && $id) {
        $v = get_vrijwilliger($id);
        if ($v) {
            db()->prepare('DELETE FROM vrijwilligers WHERE id = :id')->execute(['id' => $id]);
            $photoRemoved = vrijwilliger_remove_photo_if_unused($v['foto']);
            audit_log('delete', 'vrijwilliger', $id, 'Vrijwilliger "' . $v['naam'] . '" verwijderd' . ($photoRemoved ? ' (inclusief foto)' : ''));
            flash_set('success', 'Vrijwilliger verwijderd.');
        }
    } elseif ($action === 'toggle_groep' && $id) {
        db()->prepare('UPDATE vrijwilliger_groepen SET actief = 1 - actief WHERE id = :id')->execute(['id' => $id]);
        $g = get_vrijwilliger_groep($id);
        if ($g) {
            audit_log('toggle', 'vrijwilliger_groep', $id, 'Groep "' . $g['naam'] . '" ' . ($g['actief'] ? 'geactiveerd' : 'gedeactiveerd'));
        }
    } elseif ($action === 'delete_groep' && $id) {
        $g = get_vrijwilliger_groep($id);
        if ($g) {
            $count = db()->prepare('SELECT COUNT(*) FROM vrijwilliger_rollen WHERE groep_id = :id');
            $count->execute(['id' => $id]);
            if ((int) $count->fetchColumn() > 0) {
                flash_set('error', 'Groep "' . $g['naam'] . '" heeft nog vrijwilligers. Haal die eerst uit de groep (bij Personen) en verwijder dan de groep.');
            } else {
                db()->prepare('DELETE FROM vrijwilliger_groepen WHERE id = :id')->execute(['id' => $id]);
                audit_log('delete', 'vrijwilliger_groep', $id, 'Groep "' . $g['naam'] . '" verwijderd');
                flash_set('success', 'Groep verwijderd.');
            }
        }
    }
    header('Location: vrijwilligers.php?tab=' . $tab);
    exit;
}

/** Pijlenknoppen (POST) voor omhoog/omlaag. */
function vrijwilligers_move_form(string $action, int $id, string $tab): string
{
    $html = '';
    foreach (['up' => ['Omhoog', 'arrow-up'], 'down' => ['Omlaag', 'arrow-down']] as $dir => $meta) {
        $html .= '<form method="post" style="display:inline;">' . csrf_field()
            . '<input type="hidden" name="action" value="' . e($action) . '">'
            . '<input type="hidden" name="tab" value="' . e($tab) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="dir" value="' . $dir . '">'
            . '<button type="submit" class="btn btn-secondary btn-small" title="' . $meta[0] . '"><i class="fa-solid fa-' . $meta[1] . '"></i></button></form>';
    }
    return $html;
}

function vrijwilligers_thumb(array $v): string
{
    if (empty($v['foto'])) {
        return '<span class="vrijwilliger-thumb vrijwilliger-thumb-leeg"><i class="fa-solid fa-user" aria-hidden="true"></i></span>';
    }
    $src = preg_match('#^https?://#i', $v['foto']) ? $v['foto'] : '../' . $v['foto'];
    return '<img class="vrijwilliger-thumb" src="' . e($src) . '" alt="">';
}

$groepen = get_vrijwilliger_groepen();
$overzicht = vrijwilliger_overzicht_items(false);
$speltakken = get_speltakken();
include __DIR__ . '/includes/layout_top.php';
?>
<p>Beheer hier de vrijwilligers (smoelenboek). Een persoon kan in meerdere groepen of speltakken zitten, met per groep een eigen functie en volgorde. De leiding van een speltak staat automatisch op de speltakpagina; het hele smoelenboek plaats je met <code>[smoelenboek]</code> in de inhoud van een pagina.</p>
<div class="top-actions">
  <a href="vrijwilliger_form.php" class="btn">+ Nieuwe vrijwilliger</a>
  <a href="vrijwilliger_groep_form.php" class="btn btn-secondary">+ Nieuwe groep</a>
</div>

<nav class="tabs" aria-label="Vrijwilligersonderdelen">
  <?php foreach ($tabs as $key => $label): ?>
  <a class="<?= $key === $tab ? 'active' : '' ?>" href="vrijwilligers.php?tab=<?= e($key) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'groep'): ?>
  <?php
  $sections = [];
  foreach ($overzicht as $item) {
      if ($item['type'] === 'groep') {
          $g = $item['groep'];
          $sections[] = ['titel' => $g['naam'], 'extra' => $g['actief'] ? '' : 'inactief', 'leden' => get_groep_leden(['groep' => (int) $g['id']], false)];
      } else {
          foreach ($speltakken as $sp) {
              $sections[] = ['titel' => $sp['naam'] . ' (speltak)', 'extra' => $sp['actief'] ? '' : 'inactief', 'leden' => get_groep_leden(['speltak' => (int) $sp['id']], false)];
          }
      }
  }
  ?>
  <?php foreach ($sections as $section): ?>
  <div class="admin-card">
    <h2 style="margin-top:0;"><?= e($section['titel']) ?><?= $section['extra'] ? ' <span class="muted">(' . e($section['extra']) . ')</span>' : '' ?></h2>
    <?php if ($section['leden']): ?>
    <table class="admin-table">
      <tr><th>Volgorde</th><th>Vrijwilliger</th><th>Functie</th><th>Acties</th></tr>
      <?php foreach ($section['leden'] as $lid): ?>
      <tr>
        <td><?= vrijwilligers_move_form('move_rol', (int) $lid['rol_id'], 'groep') ?></td>
        <td><?= vrijwilligers_thumb($lid) ?> <strong><?= e($lid['naam']) ?></strong><?= $lid['actief'] ? '' : ' <span class="muted">(inactief)</span>' ?></td>
        <td><?= $lid['functie'] !== '' ? e($lid['functie']) : '<span class="muted">-</span>' ?></td>
        <td class="actions"><a class="btn btn-small" href="vrijwilliger_form.php?id=<?= (int) $lid['id'] ?>">Bewerken</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="muted" style="margin-bottom:0;">Nog geen vrijwilligers in deze groep.</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$sections): ?><div class="admin-card"><p class="muted">Nog geen groepen of speltakken.</p></div><?php endif; ?>

<?php elseif ($tab === 'personen'): ?>
  <?php $personen = get_vrijwilligers(); ?>
  <div class="admin-card">
    <table class="admin-table">
      <tr><th>Vrijwilliger</th><th>Groepen / speltakken</th><th>Status</th><th>Acties</th></tr>
      <?php foreach ($personen as $v):
          $labels = array_map(function ($r) {
              $naam = $r['speltak_id'] ? $r['speltak_naam'] : $r['groep_naam'];
              return $naam . ($r['functie'] !== '' ? ' — ' . $r['functie'] : '');
          }, get_vrijwilliger_rollen((int) $v['id']));
      ?>
      <tr>
        <td><?= vrijwilligers_thumb($v) ?> <strong><?= e($v['naam']) ?></strong></td>
        <td><?= $labels ? e(implode('; ', $labels)) : '<span class="muted">geen (niet zichtbaar op de website)</span>' ?></td>
        <td>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_persoon">
            <input type="hidden" name="tab" value="personen">
            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <button type="submit" class="btn btn-small <?= $v['actief'] ? '' : 'btn-secondary' ?>" title="Klik om te wisselen"><?= $v['actief'] ? 'Actief <i class="fa-solid fa-check"></i>' : 'Inactief' ?></button>
          </form>
        </td>
        <td class="actions">
          <a class="btn btn-small" href="vrijwilliger_form.php?id=<?= (int) $v['id'] ?>">Bewerken</a>
          <form method="post" data-confirm="Deze vrijwilliger verwijderen? De foto wordt ook van de server verwijderd als die nergens anders wordt gebruikt." style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_persoon">
            <input type="hidden" name="tab" value="personen">
            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$personen): ?><tr><td colspan="4" class="muted">Nog geen vrijwilligers.</td></tr><?php endif; ?>
    </table>
  </div>

<?php else: ?>
  <div class="admin-card">
    <p class="muted">Bepaal hier de volgorde van het smoelenboek (<code>[smoelenboek]</code>) met de pijlen. Alle speltakken staan samen in één blok, in de volgorde die je bij <a href="speltakken.php">Speltakken</a> instelt.</p>
    <table class="admin-table">
      <tr><th>Volgorde</th><th>Groep</th><th>Slug (voor <code>[smoelenboek groep="…"]</code>)</th><th>Status</th><th>Acties</th></tr>
      <?php foreach ($overzicht as $item):
          if ($item['type'] === 'speltakken'): ?>
      <tr class="vrijwilliger-speltakken-rij">
        <td><?= vrijwilligers_move_form('move_speltakken', 0, 'groepen') ?></td>
        <td><strong><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Speltakken</strong></td>
        <td colspan="3" class="muted"><?= e(implode(', ', array_column(array_filter($speltakken, fn($s) => $s['actief']), 'naam')) ?: 'Nog geen speltakken') ?> &middot; <a href="speltakken.php">beheren</a></td>
      </tr>
      <?php continue; endif; $g = $item['groep']; ?>
      <tr>
        <td><?= vrijwilligers_move_form('move_groep', (int) $g['id'], 'groepen') ?></td>
        <td><span class="color-swatch" style="background:<?= e($g['kleur']) ?>"></span><strong><?= e($g['naam']) ?></strong></td>
        <td><code><?= e($g['slug']) ?></code></td>
        <td>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_groep">
            <input type="hidden" name="tab" value="groepen">
            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <button type="submit" class="btn btn-small <?= $g['actief'] ? '' : 'btn-secondary' ?>" title="Klik om te wisselen"><?= $g['actief'] ? 'Actief <i class="fa-solid fa-check"></i>' : 'Inactief' ?></button>
          </form>
        </td>
        <td class="actions">
          <a class="btn btn-small" href="vrijwilliger_groep_form.php?id=<?= (int) $g['id'] ?>">Bewerken</a>
          <form method="post" data-confirm="Deze groep verwijderen?" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_groep">
            <input type="hidden" name="tab" value="groepen">
            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$groepen): ?><tr><td colspan="5" class="muted">Nog geen groepen. Maak er een aan, bijvoorbeeld &ldquo;Bestuur&rdquo;.</td></tr><?php endif; ?>
    </table>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

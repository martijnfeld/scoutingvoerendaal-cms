<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';
$speltakCount = (int) db()->query('SELECT COUNT(*) FROM speltakken')->fetchColumn();
$infoCardCount = (int) db()->query('SELECT COUNT(*) FROM info_cards')->fetchColumn();
$documentCount = (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();
$pageCount = (int) db()->query('SELECT COUNT(*) FROM pages')->fetchColumn();

include __DIR__ . '/includes/layout_top.php';
?>
<p>Welkom, <strong><?= e(current_admin_username()) ?></strong>. Vanuit dit beheerpaneel pas je de teksten, gegevens, speltakken en documenten van de website aan. Wijzigingen zijn direct zichtbaar op de website.</p>

<div class="field-row">
  <div class="admin-card" style="flex:1; min-width:200px;">
    <h2><?= $speltakCount ?></h2>
    <p class="muted">Speltakken</p>
    <a href="speltakken.php" class="btn btn-small">Beheren</a>
  </div>
  <div class="admin-card" style="flex:1; min-width:200px;">
    <h2><?= $infoCardCount ?></h2>
    <p class="muted">Info-vakjes</p>
    <a href="info_cards.php" class="btn btn-small">Beheren</a>
  </div>
  <div class="admin-card" style="flex:1; min-width:200px;">
    <h2><?= $documentCount ?></h2>
    <p class="muted">Documenten (PDF's)</p>
    <a href="documents.php" class="btn btn-small">Beheren</a>
  </div>
  <div class="admin-card" style="flex:1; min-width:200px;">
    <h2><?= $pageCount ?></h2>
    <p class="muted">Pagina's</p>
    <a href="paginas.php" class="btn btn-small">Beheren</a>
  </div>
</div>

<div class="admin-card">
  <h2>Snel naar</h2>
  <p><a href="settings.php">Teksten &amp; gegevens</a> — pas alle vaste teksten aan (hero, intro's, contactgegevens, verhuurvoorwaarden, footer, etc).</p>
  <p><a href="speltakken.php">Speltakken</a> — naam, kleur, leeftijden, volgorde, toelichting, leiding en opkomsten-feed per speltak.</p>
  <p><a href="info_cards.php">Info-vakjes</a> — de kaarten bij "Doe mee!" zoals "Gratis kennismaken"; voeg er zoveel toe als je wilt.</p>
  <p><a href="documents.php">Documenten</a> — upload en vervang PDF's zoals het inschrijfformulier.</p>
  <p><a href="paginas.php">Pagina's</a> — losse informatieve pagina's naast de hoofdpagina, bv. voor leden.</p>
  <p><a href="backups.php">Back-ups</a> — maak handmatig een back-up of bekijk de wekelijkse automatische back-ups.</p>
  <p><a href="updates.php">Updates</a> — controleer op een nieuwe versie en werk de website met één klik bij.</p>
  <p><a href="controle.php">Systeemcontrole</a> — controleer of de server, back-ups, updates en beveiliging goed zijn ingesteld.</p>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

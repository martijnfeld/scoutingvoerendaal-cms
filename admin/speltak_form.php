<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/scoutdash.php'; // url_has_allowed_scheme()

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$sp = $id ? get_speltak($id) : null;
if ($id && !$sp) {
    flash_set('error', 'Speltak niet gevonden.');
    header('Location: speltakken.php');
    exit;
}

$pageTitle = $sp ? 'Speltak bewerken' : 'Nieuwe speltak';
$error = '';

$values = $sp ?: [
    'naam' => '', 'kleur' => '#00A551', 'leeftijd' => '', 'dag_tijd' => '', 'leiding' => '',
    'toelichting' => '', 'feed_url' => '', 'afmeld_url' => '', 'actief' => 1, 'volgorde' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $values['naam'] = trim($_POST['naam'] ?? '');
        $values['kleur'] = trim($_POST['kleur'] ?? '#00A551');
        $values['leeftijd'] = trim($_POST['leeftijd'] ?? '');
        $values['dag_tijd'] = trim($_POST['dag_tijd'] ?? '');
        $values['leiding'] = trim($_POST['leiding'] ?? '');
        $values['toelichting'] = trim($_POST['toelichting'] ?? '');
        $values['feed_url'] = trim($_POST['feed_url'] ?? '');
        $values['afmeld_url'] = trim($_POST['afmeld_url'] ?? '');
        $values['actief'] = isset($_POST['actief']) ? 1 : 0;
        $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

        // De feed wordt door de server zelf opgehaald, dus alleen https (zie
        // scoutdash_http_get()). De afmeldlink komt als klikbare link op de
        // website, dus alleen http(s) — geen javascript:-links e.d.
        if ($values['naam'] === '') {
            $error = 'Naam is verplicht.';
        } elseif ($values['feed_url'] !== '' && !url_has_allowed_scheme($values['feed_url'], ['https'])) {
            $error = 'De opkomsten-feed-URL moet een volledige URL zijn die begint met https:// (bv. https://scoutdash.nl/feeds/json.php?k=...), of leeg blijven.';
        } elseif ($values['afmeld_url'] !== '' && !url_has_allowed_scheme($values['afmeld_url'], ['http', 'https'])) {
            $error = 'De afmeldlink moet een volledige URL zijn die begint met https:// of http://, of leeg blijven.';
        } else {
            $slug = unique_speltak_slug($values['naam'], $id ?: null);
            if ($sp) {
                $stmt = db()->prepare(
                    'UPDATE speltakken SET naam=:naam, slug=:slug, kleur=:kleur, leeftijd=:leeftijd, dag_tijd=:dag_tijd,
                     leiding=:leiding, toelichting=:toelichting, feed_url=:feed_url, afmeld_url=:afmeld_url,
                     actief=:actief, volgorde=:volgorde WHERE id=:id'
                );
                $stmt->execute([
                    'naam' => $values['naam'], 'slug' => $slug, 'kleur' => $values['kleur'],
                    'leeftijd' => $values['leeftijd'] ?: null, 'dag_tijd' => $values['dag_tijd'] ?: null,
                    'leiding' => $values['leiding'] ?: null, 'toelichting' => $values['toelichting'] ?: null,
                    'feed_url' => $values['feed_url'] ?: null, 'afmeld_url' => $values['afmeld_url'] ?: null,
                    'actief' => $values['actief'], 'volgorde' => $values['volgorde'], 'id' => $id,
                ]);
                flash_set('success', 'Speltak bijgewerkt.');
            } else {
                if (!$values['volgorde']) {
                    $values['volgorde'] = (int) db()->query('SELECT COALESCE(MAX(volgorde),0) FROM speltakken')->fetchColumn() + 1;
                }
                $stmt = db()->prepare(
                    'INSERT INTO speltakken (naam, slug, kleur, leeftijd, dag_tijd, leiding, toelichting, feed_url, afmeld_url, actief, volgorde)
                     VALUES (:naam, :slug, :kleur, :leeftijd, :dag_tijd, :leiding, :toelichting, :feed_url, :afmeld_url, :actief, :volgorde)'
                );
                $stmt->execute([
                    'naam' => $values['naam'], 'slug' => $slug, 'kleur' => $values['kleur'],
                    'leeftijd' => $values['leeftijd'] ?: null, 'dag_tijd' => $values['dag_tijd'] ?: null,
                    'leiding' => $values['leiding'] ?: null, 'toelichting' => $values['toelichting'] ?: null,
                    'feed_url' => $values['feed_url'] ?: null, 'afmeld_url' => $values['afmeld_url'] ?: null,
                    'actief' => $values['actief'], 'volgorde' => $values['volgorde'],
                ]);
                flash_set('success', 'Speltak toegevoegd.');
            }
            header('Location: speltakken.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:720px;">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="field-row">
      <div class="field">
        <label for="naam">Naam</label>
        <input type="text" id="naam" name="naam" value="<?= e($values['naam']) ?>" required>
      </div>
      <div class="field">
        <label for="kleur">Kleur</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="color" id="kleur" name="kleur" value="<?= e($values['kleur']) ?>">
        </div>
        <span class="hint">Gebruikt voor het bolletje en de snelkoppeling bij Programma.</span>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="leeftijd">Leeftijden</label>
        <input type="text" id="leeftijd" name="leeftijd" value="<?= e($values['leeftijd']) ?>" placeholder="bv. 7–11 jaar">
      </div>
      <div class="field">
        <label for="volgorde">Volgorde</label>
        <input type="number" id="volgorde" name="volgorde" value="<?= (int) $values['volgorde'] ?>">
        <span class="hint">Laag getal = eerder in de lijst. 0 = automatisch achteraan bij nieuwe speltak.</span>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="dag_tijd">Wanneer (dag / tijd)</label>
        <input type="text" id="dag_tijd" name="dag_tijd" value="<?= e($values['dag_tijd']) ?>" placeholder="bv. Zaterdag 10:00 – 12:00 uur">
      </div>
      <div class="field">
        <label for="leiding">Leiding</label>
        <input type="text" id="leiding" name="leiding" value="<?= e($values['leiding']) ?>" placeholder="bv. leiding: Anna, Bram of adviseur: Hanna">
      </div>
    </div>

    <div class="field">
      <label for="toelichting">Toelichting</label>
      <textarea class="rich-text" id="toelichting" name="toelichting" rows="4"><?= e($values['toelichting']) ?></textarea>
      <span class="hint">Wordt getoond in plaats van het programma zolang er geen opkomsten-feed is ingesteld (bv. voor een tijdelijk inactieve speltak).</span>
    </div>

    <div class="field">
      <label for="feed_url">Opkomsten JSON-feed URL</label>
      <input type="url" id="feed_url" name="feed_url" value="<?= e($values['feed_url']) ?>" placeholder="https://scoutdash.nl/feeds/json.php?k=..." pattern="[Hh][Tt][Tt][Pp][Ss]://.+">
      <span class="hint">Moet beginnen met https://. Leeg laten als deze speltak nog geen programma heeft; dan wordt de toelichting hierboven getoond.</span>
    </div>

    <div class="field">
      <label for="afmeld_url">Afmeldlink (optioneel)</label>
      <input type="url" id="afmeld_url" name="afmeld_url" value="<?= e($values['afmeld_url']) ?>" placeholder="https://scoutdash.nl/public/afmelden.php?...">
      <span class="hint">Volledige link, beginnend met https:// (of http://).</span>
    </div>

    <div class="field">
      <label class="field-inline-label"><input type="checkbox" name="actief" <?= $values['actief'] ? 'checked' : '' ?>> Actief (zichtbaar op de website)</label>
    </div>

    <button type="submit" class="btn">Opslaan</button>
    <a href="speltakken.php" class="btn btn-secondary">Annuleren</a>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

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
    'toelichting' => '', 'feed_url' => '', 'afmeld_url' => '', 'logo' => '', 'inhoud' => '',
    'meta_omschrijving' => '', 'actief' => 1, 'volgorde' => 0,
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
        $values['logo'] = trim($_POST['logo'] ?? '');
        $values['inhoud'] = $_POST['inhoud'] ?? '';
        $values['meta_omschrijving'] = trim($_POST['meta_omschrijving'] ?? '');
        $values['actief'] = isset($_POST['actief']) ? 1 : 0;
        $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

        try {
            $uploaded = handle_upload('upload_logo', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['logo'] = $uploaded;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        // De feed wordt door de server zelf opgehaald, dus alleen https (zie
        // scoutdash_http_get()). De afmeldlink komt als klikbare link op de
        // website, dus alleen http(s) — geen javascript:-links e.d.
        if ($error !== '') {
            // Uploadfout: melding hierboven, niets opslaan.
        } elseif ($values['naam'] === '') {
            $error = 'Naam is verplicht.';
        } elseif ($values['feed_url'] !== '' && !url_has_allowed_scheme($values['feed_url'], ['https'])) {
            $error = 'De opkomsten-feed-URL moet een volledige URL zijn die begint met https:// (bv. https://scoutdash.nl/feeds/json.php?k=...), of leeg blijven.';
        } elseif ($values['afmeld_url'] !== '' && !url_has_allowed_scheme($values['afmeld_url'], ['http', 'https'])) {
            $error = 'De afmeldlink moet een volledige URL zijn die begint met https:// of http://, of leeg blijven.';
        } elseif (strlen($values['logo']) > 255) {
            $error = 'Het pad van het logo is te lang (maximaal 255 tekens).';
        } elseif (strlen($values['meta_omschrijving']) > 255) {
            $error = 'De meta-omschrijving is te lang (maximaal 255 tekens).';
        } else {
            $slug = unique_speltak_slug($values['naam'], $id ?: null);
            if ($sp) {
                $stmt = db()->prepare(
                    'UPDATE speltakken SET naam=:naam, slug=:slug, kleur=:kleur, leeftijd=:leeftijd, dag_tijd=:dag_tijd,
                     leiding=:leiding, toelichting=:toelichting, feed_url=:feed_url, afmeld_url=:afmeld_url,
                     logo=:logo, inhoud=:inhoud, meta_omschrijving=:meta_omschrijving,
                     actief=:actief, volgorde=:volgorde WHERE id=:id'
                );
                $stmt->execute([
                    'naam' => $values['naam'], 'slug' => $slug, 'kleur' => $values['kleur'],
                    'leeftijd' => $values['leeftijd'] ?: null, 'dag_tijd' => $values['dag_tijd'] ?: null,
                    'leiding' => $values['leiding'] ?: null, 'toelichting' => $values['toelichting'] ?: null,
                    'feed_url' => $values['feed_url'] ?: null, 'afmeld_url' => $values['afmeld_url'] ?: null,
                    'logo' => $values['logo'] ?: null, 'inhoud' => $values['inhoud'] ?: null,
                    'meta_omschrijving' => $values['meta_omschrijving'] ?: null,
                    'actief' => $values['actief'], 'volgorde' => $values['volgorde'], 'id' => $id,
                ]);
                flash_set('success', 'Speltak bijgewerkt.');
            } else {
                if (!$values['volgorde']) {
                    $values['volgorde'] = (int) db()->query('SELECT COALESCE(MAX(volgorde),0) FROM speltakken')->fetchColumn() + 1;
                }
                $stmt = db()->prepare(
                    'INSERT INTO speltakken (naam, slug, kleur, leeftijd, dag_tijd, leiding, toelichting, feed_url, afmeld_url,
                     logo, inhoud, meta_omschrijving, actief, volgorde)
                     VALUES (:naam, :slug, :kleur, :leeftijd, :dag_tijd, :leiding, :toelichting, :feed_url, :afmeld_url,
                     :logo, :inhoud, :meta_omschrijving, :actief, :volgorde)'
                );
                $stmt->execute([
                    'naam' => $values['naam'], 'slug' => $slug, 'kleur' => $values['kleur'],
                    'leeftijd' => $values['leeftijd'] ?: null, 'dag_tijd' => $values['dag_tijd'] ?: null,
                    'leiding' => $values['leiding'] ?: null, 'toelichting' => $values['toelichting'] ?: null,
                    'feed_url' => $values['feed_url'] ?: null, 'afmeld_url' => $values['afmeld_url'] ?: null,
                    'logo' => $values['logo'] ?: null, 'inhoud' => $values['inhoud'] ?: null,
                    'meta_omschrijving' => $values['meta_omschrijving'] ?: null,
                    'actief' => $values['actief'], 'volgorde' => $values['volgorde'],
                ]);
                flash_set('success', 'Speltak toegevoegd.');
            }
            header('Location: speltakken.php');
            exit;
        }
    }
}

$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:900px;">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($sp && $sp['actief']): ?>
  <p class="muted">Eigen pagina van deze speltak: <a href="../<?= e(speltak_url($sp['slug'])) ?>" target="_blank" rel="noopener"><code>/<?= e(speltak_url($sp['slug'])) ?></code></a> (het adres volgt de naam).</p>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
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
        <span class="hint">Gebruikt voor het bolletje en de snelkoppeling bij Programma, en als accentkleur op de eigen pagina.</span>
      </div>
    </div>

    <div class="field">
      <label for="logo">Logo (optioneel)</label>
      <?php if (!empty($values['logo'])): ?><div style="margin-bottom:8px;"><img src="<?= e(preg_match('#^https?://#i', $values['logo']) ? $values['logo'] : '../' . $values['logo']) ?>" alt="" class="thumb" style="width:90px; height:90px; object-fit:contain;"></div><?php endif; ?>
      <input type="text" id="logo" name="logo" value="<?= e($values['logo'] ?? '') ?>" maxlength="255">
      <select class="file-picker" data-target="logo" style="margin-top:6px;">
        <option value="">— of kies een reeds geüpload bestand —</option>
        <?php foreach ($existingImages as $img): ?>
        <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Of upload hieronder een nieuw bestand:</span>
      <input type="file" name="upload_logo" accept="image/*" style="margin-top:6px;">
      <span class="hint">Getoond op de eigen pagina en op het overzicht van de speltakken. Bij voorkeur een vierkante PNG met transparante achtergrond. Leeg laten = gekleurd rondje met de beginletter.</span>
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
      <label for="inhoud">Inhoud eigen pagina</label>
      <textarea class="rich-text" id="inhoud" name="inhoud" rows="16"><?= e($values['inhoud'] ?? '') ?></textarea>
      <span class="hint">De tekst op de eigen pagina van deze speltak, tussen de gegevens (leeftijd, tijd, leiding) en het programma. Je kunt hier ook afbeeldingen uploaden en YouTube-video's plakken (gewoon de video-URL op een eigen regel).</span>
    </div>

    <div class="field">
      <label for="meta_omschrijving">Meta-omschrijving (optioneel)</label>
      <input type="text" id="meta_omschrijving" name="meta_omschrijving" value="<?= e($values['meta_omschrijving'] ?? '') ?>" maxlength="255">
      <span class="hint">Getoond in zoekmachines en bij delen op social media; houd het rond de 150 tekens. Leeg laten = automatisch op basis van naam, leeftijd en opkomsttijd.</span>
    </div>

    <div class="field">
      <label for="toelichting">Toelichting</label>
      <textarea class="rich-text" id="toelichting" name="toelichting" rows="4"><?= e($values['toelichting']) ?></textarea>
      <span class="hint">Wordt (op de homepage én op de eigen pagina) getoond in plaats van het programma zolang er geen opkomsten-feed is ingesteld (bv. voor een tijdelijk inactieve speltak).</span>
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

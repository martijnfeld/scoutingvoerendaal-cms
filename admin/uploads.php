<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Geüploade bestanden';
$error = '';

/**
 * Zoekt uit waar een bestand wordt gebruikt.
 *
 * Documenten (PDF's) slaan alleen de kale bestandsnaam op, dus daar volstaat
 * een exacte match. Afbeeldingen kunnen ook binnen een groter HTML-veld
 * voorkomen (bv. binnen een CKEditor-tekst) of met een licht afwijkend pad
 * zijn opgeslagen, dus daar wordt ook op een LIKE-patroon met de
 * bestandsnaam gezocht. Geüploade bestandsnamen zijn altijd hexadecimaal
 * (of eenvoudige seed-namen) en bevatten dus nooit LIKE-jokertekens.
 */
function find_upload_usage(string $filename): array
{
    $used = [];
    $urlPath = rtrim(UPLOAD_URL, '/') . '/' . $filename;
    $likePattern = '%' . $filename . '%';

    $stmt = db()->prepare('SELECT naam FROM documents WHERE bestand = :f');
    $stmt->execute(['f' => $filename]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Document: ' . $row['naam'];
    }

    $stmt = db()->prepare(
        'SELECT titel FROM info_cards WHERE afbeelding = :f OR afbeelding LIKE :p1 OR tekst LIKE :p2'
    );
    $stmt->execute(['f' => $urlPath, 'p1' => $likePattern, 'p2' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Info-vakje: ' . $row['titel'];
    }

    $stmt = db()->prepare(
        'SELECT setting_key FROM settings WHERE setting_value = :f OR setting_value LIKE :p'
    );
    $stmt->execute(['f' => $urlPath, 'p' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Instelling: ' . $row['setting_key'];
    }

    return array_unique($used);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $filename = basename((string) ($_POST['filename'] ?? ''));

    // Verborgen bestanden (zoals .htaccess, die het uitvoeren van scripts in
    // de uploadmap blokkeert) mogen nooit via dit formulier verwijderd worden.
    if ($action === 'delete' && ($filename === '' || $filename[0] === '.')) {
        flash_set('error', 'Dit bestand kan niet verwijderd worden.');
        header('Location: uploads.php');
        exit;
    }

    if ($action === 'delete') {
        $usage = find_upload_usage($filename);
        $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if ($usage) {
            flash_set('error', 'Bestand "' . $filename . '" is nog in gebruik (' . implode(', ', $usage) . ') en is niet verwijderd. Koppel het eerst los.');
        } elseif (is_file($path)) {
            @unlink($path);
            flash_set('success', 'Bestand verwijderd.');
        }
        header('Location: uploads.php');
        exit;
    }

    if ($action === 'upload') {
        try {
            $uploaded = handle_upload('bestand', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
            if ($uploaded === null) {
                $error = 'Kies een bestand om te uploaden.';
            } else {
                flash_set('success', 'Bestand geüpload: ' . basename($uploaded) . '. Je kunt dit pad nu gebruiken bij Teksten & gegevens, Info-vakjes of Documenten.');
                header('Location: uploads.php');
                exit;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$files = [];
if (is_dir(UPLOAD_DIR)) {
    foreach (scandir(UPLOAD_DIR) as $entry) {
        // Verborgen bestanden (o.a. .htaccess) niet tonen en dus ook niet verwijderbaar maken.
        if ($entry === '' || $entry[0] === '.') continue;
        $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) continue;
        $files[] = [
            'name' => $entry,
            'size' => filesize($path),
            'modified' => filemtime($path),
            'is_image' => in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
        ];
    }
    usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

include __DIR__ . '/includes/layout_top.php';
?>
<p>Alle bestanden die via het beheerpaneel zijn geüpload (foto's en PDF's). Bestanden die nog ergens aan gekoppeld zijn, kun je hier niet per ongeluk verwijderen.</p>

<div class="admin-card" style="max-width:480px;">
  <h2>Bestand uploaden</h2>
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="field">
      <label for="bestand">Bestand (afbeelding of PDF)</label>
      <input type="file" id="bestand" name="bestand" accept="image/*,application/pdf" required>
    </div>
    <button type="submit" class="btn">Uploaden</button>
  </form>
</div>

<div class="admin-card">
  <h2>Bestanden (<?= count($files) ?>)</h2>
  <table class="admin-table">
    <tr><th>Voorbeeld</th><th>Bestandsnaam</th><th>Grootte</th><th>Gebruikt door</th><th>Acties</th></tr>
    <?php foreach ($files as $file): $usage = find_upload_usage($file['name']); $url = rtrim(UPLOAD_URL, '/') . '/' . $file['name']; ?>
    <tr>
      <td>
        <?php if ($file['is_image']): ?>
        <img src="../<?= e($url) ?>" class="thumb" alt="">
        <?php else: ?>
        <span class="muted"><i class="fa-solid fa-file-pdf"></i> PDF</span>
        <?php endif; ?>
      </td>
      <td><a href="../<?= e($url) ?>" target="_blank"><?= e($file['name']) ?></a><br><span class="muted"><?= e(date('d-m-Y H:i', $file['modified'])) ?></span></td>
      <td><?= e(format_bytes($file['size'])) ?></td>
      <td><?= $usage ? e(implode(', ', $usage)) : '<span class="muted">ongebruikt</span>' ?></td>
      <td class="actions">
        <?php if ($usage): ?>
        <span class="muted" title="Eerst loskoppelen om te kunnen verwijderen">Niet te verwijderen</span>
        <?php else: ?>
        <form method="post" data-confirm="Bestand &quot;<?= e($file['name']) ?>&quot; verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="filename" value="<?= e($file['name']) ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$files): ?>
    <tr><td colspan="5" class="muted">Nog geen bestanden geüpload.</td></tr>
    <?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

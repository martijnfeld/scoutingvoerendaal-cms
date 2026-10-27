<?php
/** Velden en opslag in pages; de centrale editor controleert de aanvraag. */
$values = $page ?: [
    'titel' => '', 'slug' => '', 'inhoud' => '', 'meta_omschrijving' => '',
    'og_afbeelding' => '', 'actief' => 1, 'volgorde' => 0,
];

if ($canSave) {
    $values['titel'] = trim($_POST['titel'] ?? '');
    $values['slug'] = trim($_POST['slug'] ?? '');
    $values['inhoud'] = $_POST['inhoud'] ?? '';
    $values['meta_omschrijving'] = trim($_POST['meta_omschrijving'] ?? '');
    $values['og_afbeelding'] = trim($_POST['og_afbeelding'] ?? '');
    $values['actief'] = isset($_POST['actief']) ? 1 : 0;
    $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

    if ($values['titel'] === '') {
        $error = 'Titel is verplicht.';
    } elseif (strlen($values['og_afbeelding']) > 255) {
        $error = 'Het pad van de deelafbeelding is te lang (maximaal 255 tekens).';
    } else {
        try {
            $uploaded = handle_upload('upload_og_afbeelding', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['og_afbeelding'] = $uploaded;
            }
            $slug = unique_page_slug($values['slug'] !== '' ? $values['slug'] : $values['titel'], $id ?: null);
            if (!$page && !$values['volgorde']) {
                $values['volgorde'] = (int) db()->query('SELECT COALESCE(MAX(volgorde),0) FROM pages')->fetchColumn() + 1;
            }
            $params = [
                'titel' => $values['titel'], 'slug' => $slug, 'inhoud' => $values['inhoud'],
                'meta_omschrijving' => $values['meta_omschrijving'] ?: null,
                'og_afbeelding' => $values['og_afbeelding'] ?: null,
                'actief' => $values['actief'], 'volgorde' => $values['volgorde'],
            ];
            if ($page) {
                $params['id'] = $id;
                $stmt = db()->prepare(
                    'UPDATE pages SET titel=:titel, slug=:slug, inhoud=:inhoud, meta_omschrijving=:meta_omschrijving,
                     og_afbeelding=:og_afbeelding, actief=:actief, volgorde=:volgorde WHERE id=:id'
                );
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO pages (titel, slug, inhoud, meta_omschrijving, og_afbeelding, actief, volgorde)
                     VALUES (:titel, :slug, :inhoud, :meta_omschrijving, :og_afbeelding, :actief, :volgorde)'
                );
            }
            $stmt->execute($params);
            $id = $page ? $id : (int) db()->lastInsertId();
            audit_log($page ? 'update' : 'create', 'pagina', $id,
                'Pagina "' . $values['titel'] . '" ' . ($page ? 'bijgewerkt' : 'toegevoegd'));
            flash_set('success', $page ? 'Pagina bijgewerkt.' : 'Pagina toegevoegd.');
            header('Location: ' . page_editor_url('content', '', $id));
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

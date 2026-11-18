<?php
/** Kaartvelden en opslag; de centrale pagina-editor verzorgt de aanvraagcontrole. */
$requestedId = $cardAction === 'bewerken' ? ($_GET['id'] ?? $_POST['id'] ?? '0') : '0';
$id = is_scalar($requestedId) && ctype_digit((string) $requestedId) ? (int) $requestedId : 0;
$card = $id ? get_info_card($id) : null;
if ($cardAction === 'bewerken' && !$card) {
    flash_set('error', 'Info-vakje niet gevonden.');
    header('Location: ' . $editorUrl);
    exit;
}

$cardFormTitle = $card ? 'Info-vakje bewerken' : 'Nieuw info-vakje';
$documents = get_documents();
$values = $card ?: [
    'sectie' => 'lidworden', 'titel' => '', 'tekst' => '', 'afbeelding' => '',
    'document_id' => null, 'knop_tekst' => '', 'anchor' => '', 'volgorde' => 0,
];

if ($canSave) {
    $values['sectie'] = trim($_POST['sectie'] ?? 'lidworden') ?: 'lidworden';
    $values['titel'] = trim($_POST['titel'] ?? '');
    $values['tekst'] = $_POST['tekst'] ?? '';
    $values['afbeelding'] = trim($_POST['afbeelding'] ?? '');
    $values['document_id'] = ($_POST['document_id'] ?? '') !== '' ? (int) $_POST['document_id'] : null;
    $values['knop_tekst'] = trim($_POST['knop_tekst'] ?? '');
    $values['anchor'] = trim($_POST['anchor'] ?? '');
    $values['volgorde'] = (int) ($_POST['volgorde'] ?? 0);

    if ($values['titel'] === '') {
        $error = 'Titel is verplicht.';
    } elseif ($values['document_id'] !== null && !get_document($values['document_id'])) {
        $error = 'Het gekoppelde document bestaat niet meer. Kies een ander document.';
    } else {
        try {
            $uploaded = handle_upload('upload_afbeelding', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($uploaded !== null) {
                $values['afbeelding'] = $uploaded;
            }
            if (!$card && !$values['volgorde']) {
                $maxQuery = db()->prepare('SELECT COALESCE(MAX(volgorde),0) FROM info_cards WHERE sectie = :sectie');
                $maxQuery->execute(['sectie' => $values['sectie']]);
                $values['volgorde'] = (int) $maxQuery->fetchColumn() + 1;
            }
            $params = [
                'sectie' => $values['sectie'], 'titel' => $values['titel'], 'tekst' => $values['tekst'],
                'afbeelding' => $values['afbeelding'] ?: null, 'document_id' => $values['document_id'],
                'knop_tekst' => $values['knop_tekst'] ?: null, 'anchor' => $values['anchor'] ?: null,
                'volgorde' => $values['volgorde'],
            ];
            if ($card) {
                $params['id'] = $id;
                $stmt = db()->prepare(
                    'UPDATE info_cards SET sectie=:sectie, titel=:titel, tekst=:tekst, afbeelding=:afbeelding,
                     document_id=:document_id, knop_tekst=:knop_tekst, anchor=:anchor, volgorde=:volgorde WHERE id=:id'
                );
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO info_cards (sectie, titel, tekst, afbeelding, document_id, knop_tekst, anchor, volgorde)
                     VALUES (:sectie, :titel, :tekst, :afbeelding, :document_id, :knop_tekst, :anchor, :volgorde)'
                );
            }
            $stmt->execute($params);
            $id = $card ? $id : (int) db()->lastInsertId();
            audit_log($card ? 'update' : 'create', 'info_card', $id,
                'Info-vakje "' . $values['titel'] . '" ' . ($card ? 'bijgewerkt' : 'toegevoegd'));
            flash_set('success', $card ? 'Info-vakje bijgewerkt.' : 'Info-vakje toegevoegd.');
            header('Location: ' . $editorUrl);
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

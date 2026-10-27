<?php

if ($canSave) {
    $action = $_POST['action'] ?? '';
    $id = ctype_digit($_POST['id'] ?? '') ? (int) $_POST['id'] : 0;

    $old = $id ? get_info_card($id) : null;
    if ($action === 'delete' && $old) {
        $stmt = db()->prepare('DELETE FROM info_cards WHERE id = :id');
        $stmt->execute(['id' => $id]);
        audit_log('delete', 'info_card', $id, 'Info-vakje "' . ($old['titel'] ?? '') . '" verwijderd');
        flash_set('success', 'Info-vakje verwijderd.');
    } elseif ($action === 'move' && $id) {
        require_once __DIR__ . '/content_order.php';
        move_content_record('info_cards', $id, is_string($_POST['dir'] ?? null) ? $_POST['dir'] : '');
    }
    header('Location: ' . $editorUrl);
    exit;
}

$cards = db()->query('SELECT ic.*, d.naam AS document_naam FROM info_cards ic LEFT JOIN documents d ON d.id = ic.document_id ORDER BY ic.sectie ASC, ic.volgorde ASC, ic.id ASC')->fetchAll();


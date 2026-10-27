<?php
/** Sorteert beheerinhoud met gelijke volgordenummers en logt alleen echte verplaatsingen. */
function move_content_record(string $table, int $id, string $direction): void
{
    // Tabel- en kolomnamen komen uitsluitend uit deze interne whitelist.
    $entities = ['info_cards' => 'info_card'];
    if (!isset($entities[$table]) || !in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $lookup = db()->prepare('SELECT * FROM ' . $table . ' WHERE id = :id');
    $lookup->execute(['id' => $id]);
    $record = $lookup->fetch();
    if (!$record) {
        return;
    }
    $scope = $table === 'info_cards' ? ' WHERE sectie = :sectie' : '';
    $params = $table === 'info_cards' ? ['sectie' => $record['sectie']] : [];
    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT id FROM ' . $table . $scope . ' ORDER BY volgorde ASC, id ASC FOR UPDATE');
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $index = array_search($id, $ids, true);
        $neighbor = $index === false ? -1 : $index + ($direction === 'up' ? -1 : 1);
        if ($index !== false && isset($ids[$neighbor])) {
            [$ids[$index], $ids[$neighbor]] = [$ids[$neighbor], $ids[$index]];
            $update = db()->prepare('UPDATE ' . $table . ' SET volgorde = :v WHERE id = :id');
            foreach ($ids as $position => $recordId) {
                $update->execute(['v' => $position + 1, 'id' => $recordId]);
            }
            $label = $table === 'pages' ? 'Pagina' : 'Info-vakje';
            audit_log('move', $entities[$table], $id, $label . ' "' . $record['titel'] . '" '
                . ($direction === 'up' ? 'omhoog' : 'omlaag') . ' verplaatst');
        }
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

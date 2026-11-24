<?php
/**
 * Sorteert beheerinhoud met gelijke volgordenummers en logt alleen echte verplaatsingen.
 * Verplaatst binnen de "scope" van het record (bv. dezelfde sectie of groep).
 */
function content_order_config(): array
{
    // Tabel- en kolomnamen komen uitsluitend uit deze interne whitelist.
    return [
        'info_cards' => [
            'entity' => 'info_card', 'label' => 'Info-vakje', 'scope' => ['sectie'],
            'name' => function (array $record): string { return (string) $record['titel']; },
        ],
        'vrijwilliger_rollen' => [
            'entity' => 'vrijwilliger_rol', 'label' => 'Vrijwilliger', 'scope' => ['speltak_id', 'groep_id'],
            'name' => function (array $record): string {
                $stmt = db()->prepare('SELECT naam FROM vrijwilligers WHERE id = :id');
                $stmt->execute(['id' => $record['vrijwilliger_id']]);
                return (string) $stmt->fetchColumn();
            },
        ],
    ];
}

function move_content_record(string $table, int $id, string $direction): void
{
    $config = content_order_config();
    if (!isset($config[$table]) || !in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $entity = $config[$table];
    $lookup = db()->prepare('SELECT * FROM ' . $table . ' WHERE id = :id');
    $lookup->execute(['id' => $id]);
    $record = $lookup->fetch();
    if (!$record) {
        return;
    }
    $conditions = [];
    $params = [];
    foreach ($entity['scope'] as $column) {
        // NULL-veilige vergelijking: een rol hoort bij een speltak of een groep.
        $conditions[] = $column . ' <=> :' . $column;
        $params[$column] = $record[$column];
    }
    $scope = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
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
            audit_log('move', $entity['entity'], $id, $entity['label'] . ' "' . $entity['name']($record) . '" '
                . ($direction === 'up' ? 'omhoog' : 'omlaag') . ' verplaatst');
        }
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

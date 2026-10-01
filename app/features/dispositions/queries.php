<?php
declare(strict_types=1);

// Pomace and other co-product dispositions: a lot leaves to compost, a farm, a buyer or waste.

require_once __DIR__ . '/../inventory/ledger.php';
require_once __DIR__ . '/../press-runs/queries.php';

const DISPOSITION_DESTINATIONS = ['compost' => 'Compost', 'farm' => 'Farm (feed)', 'sale' => 'Sale', 'waste' => 'Waste', 'other' => 'Other'];
/** compost and waste destroy the lot; farm, sale and other remove it. */
const DISPOSITION_DESTRUCTIONS = ['compost', 'waste'];

/** Co-product lots with stock: lot_id => row with on_hand and label "lot · item · 1,000.0 lb". */
function find_co_product_lot_options(PDO $pdo): array
{
    $statement = $pdo->query(<<<'SQL'
        SELECT b.lot_id, b.lot_number, b.item_id, b.item_code, b.item_name, b.base_unit_code, b.unit_cost_base, sum(b.qty_on_hand) AS on_hand
        FROM app.v_lot_balances b
        WHERE b.item_class = 'co_product' AND b.qty_on_hand > 0
        GROUP BY b.lot_id, b.lot_number, b.item_id, b.item_code, b.item_name, b.base_unit_code, b.unit_cost_base
        ORDER BY b.lot_number
    SQL);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $row['label'] = $row['lot_number'] . ' · ' . $row['item_code'] . ' · ' . fmt_qty($row['on_hand'], $row['base_unit_code']);
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Record a disposition and its ledger row (destruction or removal at the lot's primary location). Caller owns the transaction. */
function insert_disposition(PDO $pdo, int $lotId, float $qtyKg, string $destination, ?string $recipient, string $disposedAt, ?string $note, int $userId): array
{
    $lot = find_lot($pdo, $lotId) ?? throw new RuntimeException('That lot does not exist.');
    $location = find_lot_primary_location($pdo, $lotId) ?? throw new RuntimeException('Lot ' . $lot['lot_number'] . ' has no stock left.');
    $group = new_group_id();
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.co_product_dispositions (lot_id, qty_base, destination, recipient, disposed_at, actor_id, ledger_group_id, note)
        VALUES (:lot, :qty, :dest, :recipient, :at, :by, :grp, :note) RETURNING id, lot_id, qty_base, destination, recipient, disposed_at
    SQL);
    $statement->execute(['lot' => $lotId, 'qty' => round($qtyKg, 4), 'dest' => $destination, 'recipient' => $recipient, 'at' => $disposedAt, 'by' => $userId, 'grp' => $group, 'note' => $note]);
    $row = $statement->fetch();
    insert_inventory_transaction($pdo, $group, in_array($destination, DISPOSITION_DESTRUCTIONS, true) ? 'destruction' : 'removal', (int) $lot['item_id'], $lotId,
        (int) $location['location_id'], (int) $lot['premises_id'], -round($qtyKg, 4), (float) $lot['unit_cost_base'], 'disposal', null, null, 'none',
        'co_product_disposition', (int) $row['id'], 'co_product_disposition:' . $row['id'] . ':1', $disposedAt, $userId, $note);
    return $row + ['lot_number' => $lot['lot_number'], 'ledger_group_id' => $group];
}

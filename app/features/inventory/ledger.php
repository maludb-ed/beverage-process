<?php
declare(strict_types=1);

// The only way to write app.inventory_transactions. Every posting handler
// (receipts, transfers, adjustments, counts, press runs, batches, packaging,
// removals) calls insert_inventory_transaction() inside its own transaction.
// The ledger triggers enforce the interlocks: lot/item match, quarantine,
// negative stock, and bonded <-> tax-paid moves only through removals/returns.

/** A new UUID v4 shared by every ledger row of one logical event. */
function new_group_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * Insert one signed ledger row. tax_state and premises are snapshotted from the
 * location by the trigger; pass the premises anyway for readability.
 */
function insert_inventory_transaction(
    PDO $pdo,
    string $groupId,
    string $txnType,
    int $itemId,
    int $lotId,
    int $locationId,
    int $premisesId,
    float $qtyBase,
    float $unitCostBase,
    string $counterpartyKind,
    ?int $counterpartyId,
    ?int $reasonCodeId,
    string $ttbCategory,
    string $referenceKind,
    int $referenceId,
    string $idempotencyKey,
    string $occurredAt,
    ?int $actorId,
    ?string $note = null,
    ?int $reversesId = null
): array {
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.inventory_transactions
            (group_id, txn_type, item_id, lot_id, location_id, premises_id, qty_base, unit_cost_base, tax_state,
             counterparty_kind, counterparty_id, reason_code_id, ttb_category, reference_kind, reference_id,
             reverses_id, idempotency_key, occurred_at, actor_id, note)
        VALUES
            (:group_id, :txn_type, :item_id, :lot_id, :location_id, :premises_id, :qty_base, :unit_cost_base, 'bonded',
             :counterparty_kind, :counterparty_id, :reason_code_id, :ttb_category, :reference_kind, :reference_id,
             :reverses_id, :idempotency_key, :occurred_at, :actor_id, :note)
        RETURNING id, group_id, txn_type, item_id, lot_id, location_id, qty_base, tax_state, ttb_category
    SQL);
    $statement->execute([
        'group_id' => $groupId, 'txn_type' => $txnType, 'item_id' => $itemId, 'lot_id' => $lotId,
        'location_id' => $locationId, 'premises_id' => $premisesId, 'qty_base' => $qtyBase, 'unit_cost_base' => $unitCostBase,
        'counterparty_kind' => $counterpartyKind, 'counterparty_id' => $counterpartyId, 'reason_code_id' => $reasonCodeId,
        'ttb_category' => $ttbCategory, 'reference_kind' => $referenceKind, 'reference_id' => $referenceId,
        'reverses_id' => $reversesId, 'idempotency_key' => $idempotencyKey, 'occurred_at' => $occurredAt,
        'actor_id' => $actorId, 'note' => $note,
    ]);
    return $statement->fetch();
}

/** On-hand quantity of a lot at a location (0 when none). */
function lot_on_hand(PDO $pdo, int $lotId, int $locationId): float
{
    $statement = $pdo->prepare('SELECT COALESCE(sum(qty_on_hand), 0) FROM app.inventory_balances WHERE lot_id = :lot AND location_id = :loc');
    $statement->execute(['lot' => $lotId, 'loc' => $locationId]);
    return (float) $statement->fetchColumn();
}

/**
 * Post compensating ledger rows for every row of one document that has not been reversed yet.
 * Rows are matched by reference_kind/reference_id and written as txn_type 'reversal' (reference_kind 'reversal',
 * reference_id = the document id, reverses_id = the original row) in one new group. Caller owns the transaction;
 * the ledger triggers may refuse (stock already consumed) and the PDOException propagates.
 * Idempotency key: "{feature}:{id}:reverse:{rowId}". Returns the new rows.
 */
function reverse_document_ledger(PDO $pdo, string $feature, string $referenceKind, int $documentId, ?int $actorId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.* FROM app.inventory_transactions t
        WHERE t.reference_kind = :kind AND t.reference_id = :id
          AND NOT EXISTS (SELECT 1 FROM app.inventory_transactions r WHERE r.reverses_id = t.id)
        ORDER BY (t.qty_base > 0) DESC, t.id
    SQL);
    $statement->execute(['kind' => $referenceKind, 'id' => $documentId]);
    $group = new_group_id();
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $rows = [];
    foreach ($statement->fetchAll() as $original) {
        $rows[] = insert_inventory_transaction($pdo, $group, 'reversal', (int) $original['item_id'], (int) $original['lot_id'], (int) $original['location_id'],
            (int) $original['premises_id'], -(float) $original['qty_base'], (float) $original['unit_cost_base'], (string) $original['counterparty_kind'],
            $original['counterparty_id'] === null ? null : (int) $original['counterparty_id'], $original['reason_code_id'] === null ? null : (int) $original['reason_code_id'],
            (string) $original['ttb_category'], 'reversal', $documentId, $feature . ':' . $documentId . ':reverse:' . $original['id'], $now, $actorId,
            'Reversal of ' . $original['txn_type'] . ' #' . $original['id'], (int) $original['id']);
    }
    return $rows;
}

/** True when the document has reversal rows posted against it. */
function ledger_document_reversed(PDO $pdo, string $feature, int $documentId): bool
{
    $statement = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM app.inventory_transactions WHERE reference_kind = 'reversal' AND idempotency_key LIKE :p)");
    $statement->execute(['p' => $feature . ':' . $documentId . ':reverse:%']);
    return (bool) $statement->fetchColumn();
}

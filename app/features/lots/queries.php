<?php
declare(strict_types=1);

const LOT_STATUSES = ['quarantine' => 'Quarantine', 'hold' => 'Hold', 'released' => 'Released', 'rejected' => 'Rejected'];
const LOT_SORTS = ['lot_number' => 'l.lot_number', 'received_on' => 'COALESCE(l.received_on, l.produced_on)', 'expires_on' => 'l.expires_on', 'quality_status' => 'l.quality_status', 'item' => 'i.code'];
const LOT_ATTRIBUTE_KEYS = [
    'variety' => 'Variety', 'orchard' => 'Orchard', 'block' => 'Block', 'brix' => 'Brix (°Bx)', 'ph' => 'pH', 'ta' => 'TA (g/L)',
    'free_so2' => 'Free SO2 (mg/L)', 'total_so2' => 'Total SO2 (mg/L)', 'abv' => 'ABV (%)', 'co2_g_100ml' => 'CO2 (g/100 mL)',
    'fruit_share_pct' => 'Fruit share (%)', 'strain' => 'Yeast strain', 'generation' => 'Yeast generation', 'viability_pct' => 'Viability (%)',
    'alpha_acid_pct' => 'Alpha acid (%)', 'moisture_pct' => 'Moisture (%)', 'bin_count' => 'Bins', 'net_kg' => 'Net weight (kg)',
];
const RELEASE_BASES = ['coa' => 'Certificate of analysis', 'inspection' => 'Inspection', 'readings' => 'Readings', 'sensory' => 'Sensory', 'override' => 'Override', 'other' => 'Other'];

const LOT_COLUMNS = <<<'SQL'
    l.id, l.lot_number, l.item_id, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
    l.premises_id, l.supplier_lot_number, l.supplier_id, s.name AS supplier_name, l.received_on, l.produced_on, l.expires_on,
    l.quality_status, l.unit_cost_base, l.source_kind, l.source_id, l.notes, l.created_at, l.updated_at,
    (l.expires_on IS NOT NULL AND l.expires_on < current_date + 30) AS expiring_soon,
    COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id), 0) AS qty_on_hand
SQL;
const LOT_FROM = ' FROM app.lots l JOIN app.items i ON i.id = l.item_id LEFT JOIN app.suppliers s ON s.id = l.supplier_id';

function find_lots(PDO $pdo, string $search = '', string $sort = '-lot_number', int $page = 1, ?string $qualityStatus = null, ?string $itemClass = null, bool $expiringOnly = false): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(l.lot_number ILIKE :s OR l.supplier_lot_number ILIKE :s OR i.code ILIKE :s OR i.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($qualityStatus) { $where[] = 'l.quality_status = :qs'; $params['qs'] = $qualityStatus; }
    if ($itemClass) { $where[] = 'i.item_class = :ic'; $params['ic'] = $itemClass; }
    if ($expiringOnly) { $where[] = 'l.expires_on IS NOT NULL AND l.expires_on < current_date + 30'; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo, 'SELECT ' . LOT_COLUMNS . LOT_FROM . $whereSql . ' ORDER BY ' . order_by($sort, LOT_SORTS, '-lot_number'),
        'SELECT count(*)' . LOT_FROM . $whereSql, $params, $page);
}

function find_lot(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . LOT_COLUMNS . ', p.name AS premises_name, i.lot_controlled, i.default_receipt_status' . LOT_FROM . ' JOIN app.premises p ON p.id = l.premises_id WHERE l.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_lot_by_number(PDO $pdo, string $lotNumber): ?array
{
    $statement = $pdo->prepare('SELECT id FROM app.lots WHERE lot_number = :n');
    $statement->execute(['n' => $lotNumber]);
    $id = $statement->fetchColumn();
    return $id === false ? null : find_lot($pdo, (int) $id);
}

function find_lot_balances(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_lot_balances WHERE lot_id = :id ORDER BY location_name');
    $statement->execute(['id' => $lotId]);
    return $statement->fetchAll();
}

function update_lot(PDO $pdo, int $id, ?string $supplierLotNumber, ?string $expiresOn, ?string $notes): array
{
    $statement = $pdo->prepare('UPDATE app.lots SET supplier_lot_number = :sln, expires_on = :exp, notes = :notes WHERE id = :id RETURNING id, lot_number, supplier_lot_number, expires_on, notes');
    $statement->execute(['id' => $id, 'sln' => $supplierLotNumber, 'exp' => $expiresOn, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Lot not found.');
    }
    return $row;
}

function insert_lot(PDO $pdo, string $lotNumber, int $itemId, int $premisesId, ?int $supplierId, ?string $supplierLotNumber, ?string $receivedOn, ?string $expiresOn, string $qualityStatus, float $unitCostBase, string $sourceKind, int $sourceId, int $createdBy, ?string $producedOn = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.lots (lot_number, item_id, premises_id, supplier_id, supplier_lot_number, received_on, produced_on, expires_on,
                              quality_status, unit_cost_base, source_kind, source_id, created_by)
        VALUES (CASE WHEN :lot_number = '' THEN app.next_number('lot') ELSE :lot_number END, :item_id, :premises_id, :supplier_id, :sln,
                :received_on, :produced_on, :expires_on, :quality_status, :unit_cost_base, :source_kind, :source_id, :created_by)
        RETURNING id, lot_number, item_id, quality_status, unit_cost_base
    SQL);
    $statement->execute([
        'lot_number' => $lotNumber, 'item_id' => $itemId, 'premises_id' => $premisesId, 'supplier_id' => $supplierId, 'sln' => $supplierLotNumber,
        'received_on' => $receivedOn, 'produced_on' => $producedOn, 'expires_on' => $expiresOn, 'quality_status' => $qualityStatus,
        'unit_cost_base' => $unitCostBase, 'source_kind' => $sourceKind, 'source_id' => $sourceId, 'created_by' => $createdBy,
    ]);
    return $statement->fetch();
}

/** Upsert one typed attribute on a lot. */
function set_lot_attribute(PDO $pdo, int $lotId, string $key, ?float $valueNum, ?string $valueText, ?string $unitCode, string $source, ?int $recordedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.lot_attributes (lot_id, key, value_num, value_text, unit_code, source, recorded_by)
        VALUES (:lot, :key, :num, :txt, :unit, :source, :by)
        ON CONFLICT (lot_id, key) DO UPDATE SET value_num = EXCLUDED.value_num, value_text = EXCLUDED.value_text,
            unit_code = EXCLUDED.unit_code, source = EXCLUDED.source, recorded_by = EXCLUDED.recorded_by, recorded_at = now()
        RETURNING id, lot_id, key, value_num, value_text, unit_code, source
    SQL);
    $statement->execute(['lot' => $lotId, 'key' => $key, 'num' => $valueNum, 'txt' => $valueText, 'unit' => $unitCode, 'source' => $source, 'by' => $recordedBy]);
    return $statement->fetch();
}

function find_lot_attributes(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare('SELECT a.*, u.display_name AS recorded_by_name FROM app.lot_attributes a LEFT JOIN app.users u ON u.id = a.recorded_by WHERE a.lot_id = :id ORDER BY a.key');
    $statement->execute(['id' => $lotId]);
    return $statement->fetchAll();
}

function find_lot_attribute(PDO $pdo, int $lotId, string $key): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.lot_attributes WHERE lot_id = :id AND key = :key');
    $statement->execute(['id' => $lotId, 'key' => $key]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_lot_certificates(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT c.*, a.file_name, a.mime_type, a.byte_size, u.display_name AS recorded_by_name
        FROM app.certificates_of_analysis c
        LEFT JOIN app.attachments a ON a.id = c.attachment_id
        LEFT JOIN app.users u ON u.id = c.recorded_by
        WHERE c.lot_id = :id ORDER BY c.created_at DESC
    SQL);
    $statement->execute(['id' => $lotId]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row['values_json'] = json_decode((string) $row['values_json'], true) ?: [];
    }
    return $rows;
}

function insert_attachment(PDO $pdo, string $entityType, int $entityId, string $kind, string $fileName, string $mimeType, string $storagePath, int $byteSize, int $uploadedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.attachments (entity_type, entity_id, kind, file_name, mime_type, storage_path, byte_size, uploaded_by)
        VALUES (:et, :eid, :kind, :fn, :mime, :path, :size, :by) RETURNING id, file_name
    SQL);
    $statement->execute(['et' => $entityType, 'eid' => $entityId, 'kind' => $kind, 'fn' => $fileName, 'mime' => $mimeType, 'path' => $storagePath, 'size' => $byteSize, 'by' => $uploadedBy]);
    return $statement->fetch();
}

function find_attachment(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.attachments WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_certificate(PDO $pdo, int $lotId, ?int $attachmentId, ?string $issuedOn, ?string $issuer, array $values, int $recordedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.certificates_of_analysis (lot_id, attachment_id, issued_on, issuer, values_json, recorded_by)
        VALUES (:lot, :att, :issued, :issuer, :vals, :by) RETURNING id, lot_id, issued_on, issuer
    SQL);
    $statement->execute(['lot' => $lotId, 'att' => $attachmentId, 'issued' => $issuedOn, 'issuer' => $issuer, 'vals' => json_encode($values, JSON_THROW_ON_ERROR), 'by' => $recordedBy]);
    return $statement->fetch();
}

function find_release_decisions(PDO $pdo, string $targetKind, int $targetId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT d.*, u.display_name AS decided_by_name, rc.name AS reason_name
        FROM app.release_decisions d
        JOIN app.users u ON u.id = d.decided_by
        LEFT JOIN app.reason_codes rc ON rc.id = d.reason_code_id
        WHERE d.target_kind = :kind AND d.target_id = :id ORDER BY d.decided_at DESC
    SQL);
    $statement->execute(['kind' => $targetKind, 'id' => $targetId]);
    return $statement->fetchAll();
}

function insert_release_decision(PDO $pdo, string $targetKind, int $targetId, string $fromStatus, string $toStatus, string $basis, bool $isOverride, ?int $reasonCodeId, ?string $note, int $decidedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.release_decisions (target_kind, target_id, from_status, to_status, basis, is_override, reason_code_id, note, decided_by)
        VALUES (:kind, :id, :from, :to, :basis, :override, :reason, :note, :by)
        RETURNING id, target_kind, target_id, from_status, to_status, basis, is_override, decided_at
    SQL);
    $statement->execute(['kind' => $targetKind, 'id' => $targetId, 'from' => $fromStatus, 'to' => $toStatus, 'basis' => $basis,
        'override' => $isOverride ? 't' : 'f', 'reason' => $reasonCodeId, 'note' => $note, 'by' => $decidedBy]);
    return $statement->fetch();
}

function update_lot_quality_status(PDO $pdo, int $id, string $qualityStatus): array
{
    $statement = $pdo->prepare('UPDATE app.lots SET quality_status = :qs WHERE id = :id RETURNING id, lot_number, quality_status');
    $statement->execute(['id' => $id, 'qs' => $qualityStatus]);
    return $statement->fetch();
}

function override_reason_options(PDO $pdo): array
{
    return array_column($pdo->query("SELECT id, name FROM app.reason_codes WHERE applies_to = 'override' AND active ORDER BY name")->fetchAll(), 'name', 'id');
}

/** Item classes for the lots filter. */
function lot_item_class_options(PDO $pdo): array
{
    require_once __DIR__ . '/../items/queries.php';
    return item_class_options($pdo, null, false);
}

/** The receipt behind a receipt-line lot: [id, number] or [null, null]. */
function find_lot_receipt(PDO $pdo, array $lot): array
{
    if ($lot['source_kind'] !== 'receipt_line') {
        return [null, null];
    }
    $statement = $pdo->prepare('SELECT gr.id, gr.number FROM app.goods_receipt_lines l JOIN app.goods_receipts gr ON gr.id = l.goods_receipt_id WHERE l.id = :id');
    $statement->execute(['id' => $lot['source_id']]);
    $row = $statement->fetch();
    return $row === false ? [null, null] : [(int) $row['id'], $row['number']];
}

/** Everything the lot detail screen shows. */
function lot_view_data(PDO $pdo, array $lot): array
{
    [$receiptId, $receiptNumber] = find_lot_receipt($pdo, $lot);
    return [
        'lot' => $lot, 'balances' => find_lot_balances($pdo, (int) $lot['id']), 'attributes' => find_lot_attributes($pdo, (int) $lot['id']),
        'certificates' => find_lot_certificates($pdo, (int) $lot['id']), 'decisions' => find_release_decisions($pdo, 'lot', (int) $lot['id']),
        'receiptId' => $receiptId, 'receiptNumber' => $receiptNumber,
    ];
}

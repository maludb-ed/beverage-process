<?php
declare(strict_types=1);

// Batch execution (slice 6): batches, their events, and the vessel/occupancy helpers
// every liquid-handling slice reuses. Batches have no ledger rows: volume lives in
// batches.current_volume_l and vessel_occupancies; ledger rows exist only for item lots
// consumed into or produced from a batch (insert_inventory_transaction, never direct).

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../lots/queries.php';
require_once __DIR__ . '/../inventory/ledger.php';
require_once __DIR__ . '/../press-runs/queries.php';

const BATCH_STATUSES = ['active' => 'Active', 'packaged' => 'Packaged', 'dumped' => 'Dumped', 'closed' => 'Closed'];
const BATCH_SORTS = ['number' => 'b.number', 'started_at' => 'b.started_at', 'status' => 'b.status', 'current_stage_code' => 'st.display_order', 'product_name' => 'p.name'];
const BATCH_TAX_CLASSES = ['hard_cider' => 'Hard cider', 'still_wine' => 'Still wine', 'artificially_carbonated_wine' => 'Artificially carbonated wine', 'sparkling_wine' => 'Sparkling wine'];
const BATCH_ADDITION_PURPOSES = ['nutrient' => 'Nutrient', 'sulfite' => 'Sulfite', 'enzyme' => 'Enzyme', 'sweetener' => 'Sweetener', 'acid' => 'Acid', 'fining' => 'Fining', 'base_juice' => 'Base juice', 'other' => 'Other'];
const BATCH_CONSUMPTION_PURPOSES = BATCH_ADDITION_PURPOSES + ['yeast' => 'Yeast', 'fruit' => 'Fruit'];
const BATCH_ADDITION_CLASSES = ['additive', 'yeast', 'juice', 'intermediate'];
/** Expected loss reason when a stage move leaves volume behind (spec: rack → RACK, primary → LEES, otherwise RACK). */
const BATCH_STAGE_LOSS_REASONS = ['rack' => 'RACK', 'primary' => 'LEES'];

// Vessels and occupancies ----------------------------------------------------------------

const BATCH_VESSEL_SELECT = <<<'SQL'
    SELECT v.id, v.name, v.kind, v.capacity_l, v.status, v.location_id, v.premises_id,
           o.id AS occupancy_id, o.occupant_kind, o.occupant_id, o.volume_l AS occupancy_volume_l, o.from_at AS occupied_since,
           CASE WHEN o.occupant_kind = 'batch' THEN ob.number WHEN o.occupant_kind = 'lot' THEN ol.lot_number END AS occupant_label
    FROM app.vessels v
    LEFT JOIN app.vessel_occupancies o ON o.vessel_id = v.id AND o.to_at IS NULL
    LEFT JOIN app.batches ob ON o.occupant_kind = 'batch' AND ob.id = o.occupant_id
    LEFT JOIN app.lots ol ON o.occupant_kind = 'lot' AND ol.id = o.occupant_id
SQL;

/** Active vessels keyed by id with their open occupant; press vessels only when asked. */
function batches_vessel_catalog(PDO $pdo, bool $press = false): array
{
    $statement = $pdo->query(BATCH_VESSEL_SELECT . ' WHERE v.active AND ' . ($press ? "v.kind = 'press'" : "v.kind <> 'press'") . ' ORDER BY v.name');
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

/** Vessel select labels: "FV-1 (500.0 gal) — holds B-26-001". */
function batches_vessel_options(array $catalog): array
{
    $options = [];
    foreach ($catalog as $id => $vessel) {
        $options[$id] = $vessel['name'] . ' (' . fmt_qty($vessel['capacity_l'], 'L') . ')' . ($vessel['occupant_label'] ? ' — holds ' . $vessel['occupant_label'] : '');
    }
    return $options;
}

function find_vessel(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(BATCH_VESSEL_SELECT . ' WHERE v.id = :id' . ($lock ? ' FOR UPDATE OF v' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_vessel_occupancy(PDO $pdo, int $vesselId, string $occupantKind, int $occupantId, float $volumeL, string $fromAt): int
{
    $statement = $pdo->prepare('INSERT INTO app.vessel_occupancies (vessel_id, occupant_kind, occupant_id, volume_l, from_at) VALUES (:v, :k, :o, :vol, :at) RETURNING id');
    $statement->execute(['v' => $vesselId, 'k' => $occupantKind, 'o' => $occupantId, 'vol' => round($volumeL, 3), 'at' => $fromAt]);
    return (int) $statement->fetchColumn();
}

function set_occupancy_volume(PDO $pdo, int $occupancyId, float $volumeL): void
{
    $pdo->prepare('UPDATE app.vessel_occupancies SET volume_l = :vol WHERE id = :id')->execute(['vol' => round(max(0.0, $volumeL), 3), 'id' => $occupancyId]);
}

/** Close an occupancy; to_at never precedes from_at (form times have minute precision). */
function close_occupancy(PDO $pdo, int $occupancyId, string $at, ?float $volumeL = null): void
{
    $pdo->prepare('UPDATE app.vessel_occupancies SET to_at = GREATEST(:at::timestamptz, from_at), volume_l = COALESCE(:vol::numeric, volume_l) WHERE id = :id AND to_at IS NULL')
        ->execute(['at' => $at, 'vol' => $volumeL === null ? null : round($volumeL, 3), 'id' => $occupancyId]);
}

function set_vessel_status(PDO $pdo, int $vesselId, string $status): void
{
    $pdo->prepare('UPDATE app.vessels SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $vesselId]);
}

/** Open occupancies of a batch, largest first. */
function find_batch_vessels(PDO $pdo, int $batchId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.id AS occupancy_id, o.vessel_id, o.volume_l, o.from_at, v.name AS vessel_name, v.kind AS vessel_kind, v.capacity_l, v.location_id
        FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
        WHERE o.occupant_kind = 'batch' AND o.occupant_id = :id AND o.to_at IS NULL
        ORDER BY o.volume_l DESC, o.id
    SQL);
    $statement->execute(['id' => $batchId]);
    return $statement->fetchAll();
}

/**
 * Take liters out of a batch's open occupancies: the preferred vessel first, then the
 * largest. An occupancy that reaches 0 closes and its vessel becomes empty. Returns the
 * vessel ids that were emptied.
 */
function batches_reduce_occupancies(PDO $pdo, int $batchId, float $liters, string $at, ?int $preferVesselId = null): array
{
    $occupancies = find_batch_vessels($pdo, $batchId);
    usort($occupancies, static fn($a, $b) => ((int) $b['vessel_id'] === $preferVesselId) <=> ((int) $a['vessel_id'] === $preferVesselId));
    $emptied = [];
    $left = round($liters, 3);
    foreach ($occupancies as $occupancy) {
        if ($left <= 0) {
            break;
        }
        $take = min($left, (float) $occupancy['volume_l']);
        $remaining = round((float) $occupancy['volume_l'] - $take, 3);
        if ($remaining <= 0.0005) {
            close_occupancy($pdo, (int) $occupancy['occupancy_id'], $at, 0.0);
            set_vessel_status($pdo, (int) $occupancy['vessel_id'], 'empty');
            $emptied[] = (int) $occupancy['vessel_id'];
        } else {
            set_occupancy_volume($pdo, (int) $occupancy['occupancy_id'], $remaining);
        }
        $left = round($left - $take, 3);
    }
    return $emptied;
}

/** Add liters to the batch's largest open occupancy. */
function batches_add_to_occupancy(PDO $pdo, int $batchId, float $liters): void
{
    $occupancies = find_batch_vessels($pdo, $batchId);
    if ($occupancies !== []) {
        set_occupancy_volume($pdo, (int) $occupancies[0]['occupancy_id'], (float) $occupancies[0]['volume_l'] + $liters);
    }
}

/** The open occupancy of a lot (juice in a vessel), or null. */
function find_lot_occupancy(PDO $pdo, int $lotId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.id AS occupancy_id, o.vessel_id, o.volume_l, o.from_at, v.name AS vessel_name, v.location_id
        FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
        WHERE o.occupant_kind = 'lot' AND o.occupant_id = :id AND o.to_at IS NULL
    SQL);
    $statement->execute(['id' => $lotId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Take liters of a lot out of its vessel: close the occupancy when emptied (vessel becomes empty unless $keepVesselId). */
function batches_draw_lot_from_vessel(PDO $pdo, int $lotId, float $liters, string $at, ?int $keepVesselId = null): void
{
    $occupancy = find_lot_occupancy($pdo, $lotId);
    if ($occupancy === null) {
        return;
    }
    $remaining = round((float) $occupancy['volume_l'] - $liters, 3);
    if ($remaining <= 0.0005) {
        close_occupancy($pdo, (int) $occupancy['occupancy_id'], $at);
        if ((int) $occupancy['vessel_id'] !== $keepVesselId) {
            set_vessel_status($pdo, (int) $occupancy['vessel_id'], 'empty');
        }
    } else {
        set_occupancy_volume($pdo, (int) $occupancy['occupancy_id'], $remaining);
    }
}

// Lookups ------------------------------------------------------------------------------------

function batches_product_options(PDO $pdo): array
{
    return $pdo->query("SELECT id, name FROM app.products WHERE status = 'active' AND beverage_type = 'cider' ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Recipe versions of a product (active first), id => "v2 (active)". */
function batches_recipe_options(PDO $pdo, ?int $productId): array
{
    if ($productId === null) {
        return [];
    }
    $statement = $pdo->prepare("SELECT id, 'v' || version_no || ' (' || status || ')' FROM app.recipe_versions WHERE product_id = :p AND status IN ('active', 'draft') ORDER BY (status = 'active') DESC, version_no DESC");
    $statement->execute(['p' => $productId]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

function batches_active_recipe_id(PDO $pdo, int $productId): ?int
{
    $statement = $pdo->prepare("SELECT id FROM app.recipe_versions WHERE product_id = :p AND status = 'active'");
    $statement->execute(['p' => $productId]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Released production orders of a product, id => number. */
function batches_production_order_options(PDO $pdo, ?int $productId): array
{
    if ($productId === null) {
        return [];
    }
    $statement = $pdo->prepare("SELECT id, number FROM app.production_orders WHERE product_id = :p AND status = 'released' ORDER BY number");
    $statement->execute(['p' => $productId]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Cider stages, code => row (name, display_order, is_terminal). */
function batches_stage_catalog(PDO $pdo): array
{
    $rows = [];
    foreach ($pdo->query("SELECT code, name, display_order, is_terminal FROM app.stages WHERE 'cider' = ANY(beverage_types) ORDER BY display_order") as $row) {
        $rows[$row['code']] = $row;
    }
    return $rows;
}

function batches_measurement_catalog(PDO $pdo): array
{
    $rows = [];
    foreach ($pdo->query('SELECT code, name, unit, decimals, min_valid, max_valid FROM app.measurement_types ORDER BY name') as $row) {
        $rows[$row['code']] = $row;
    }
    return $rows;
}

/** Reason codes keyed by id for the given applies_to values (active only). */
function batches_reason_catalog(PDO $pdo, array $appliesTo): array
{
    $marks = implode(', ', array_map(static fn($i) => ':a' . $i, array_keys($appliesTo)));
    $statement = $pdo->prepare('SELECT id, code, name, applies_to, ttb_category, classification, requires_approval_above FROM app.reason_codes WHERE active AND applies_to IN (' . $marks . ') ORDER BY name');
    foreach (array_values($appliesTo) as $i => $value) {
        $statement->bindValue('a' . $i, $value);
    }
    $statement->execute();
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

function find_reason_code_by_code(PDO $pdo, string $code): ?array
{
    $statement = $pdo->prepare('SELECT id, code, name, applies_to, ttb_category, classification, requires_approval_above FROM app.reason_codes WHERE code = :c');
    $statement->execute(['c' => $code]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Released juice/intermediate lots in a vessel or with stock: lot_id => row with available_l and location_id. */
function find_juice_lot_options(PDO $pdo): array
{
    $statement = $pdo->query(<<<'SQL'
        SELECT l.id AS lot_id, l.lot_number, l.item_id, i.code AS item_code, l.unit_cost_base, l.premises_id,
               o.id AS occupancy_id, o.vessel_id, v.name AS vessel_name, o.volume_l AS occupancy_volume_l, v.location_id AS vessel_location_id,
               (SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id) AS on_hand,
               (SELECT a.value_num FROM app.lot_attributes a WHERE a.lot_id = l.id AND a.key = 'brix') AS brix,
               (SELECT a.value_num FROM app.lot_attributes a WHERE a.lot_id = l.id AND a.key = 'fruit_share_pct') AS fruit_share_pct
        FROM app.lots l
        JOIN app.items i ON i.id = l.item_id
        LEFT JOIN app.vessel_occupancies o ON o.occupant_kind = 'lot' AND o.occupant_id = l.id AND o.to_at IS NULL
        LEFT JOIN app.vessels v ON v.id = o.vessel_id
        WHERE i.item_class IN ('juice', 'intermediate') AND l.quality_status = 'released'
          AND (o.id IS NOT NULL OR (SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id) > 0)
        ORDER BY l.lot_number
    SQL);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $primary = $row['occupancy_id'] !== null ? null : find_lot_primary_location($pdo, (int) $row['lot_id']);
        $row['location_id'] = $row['occupancy_id'] !== null ? (int) $row['vessel_location_id'] : ($primary['location_id'] ?? null);
        $row['available_l'] = $row['occupancy_id'] !== null ? (float) $row['occupancy_volume_l'] : (float) ($primary['qty_on_hand'] ?? 0);
        $row['label'] = $row['lot_number'] . ' · ' . ($row['brix'] !== null ? round((float) $row['brix'], 1) . ' °Bx' : 'no brix') . ' · '
            . ($row['vessel_name'] ?? 'no vessel') . ' · ' . fmt_qty($row['available_l'], 'L');
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Released yeast lots with stock: lot_id => row with label "lot · strain · gen N". */
function find_yeast_lot_options(PDO $pdo): array
{
    $statement = $pdo->query(<<<'SQL'
        SELECT l.id AS lot_id, l.lot_number, l.item_id, i.code AS item_code, i.base_unit_code, l.unit_cost_base,
               (SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id) AS on_hand,
               (SELECT a.value_text FROM app.lot_attributes a WHERE a.lot_id = l.id AND a.key = 'strain') AS strain,
               (SELECT a.value_num FROM app.lot_attributes a WHERE a.lot_id = l.id AND a.key = 'generation') AS generation
        FROM app.lots l JOIN app.items i ON i.id = l.item_id
        WHERE i.item_class = 'yeast' AND l.quality_status = 'released'
          AND (SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id) > 0
        ORDER BY l.expires_on NULLS LAST, l.lot_number
    SQL);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $row['label'] = $row['lot_number'] . ' · ' . ($row['strain'] ?? $row['item_code']) . ' · gen ' . ($row['generation'] !== null ? (int) $row['generation'] : 1)
            . ' · ' . fmt_qty($row['on_hand'], $row['base_unit_code'], 3);
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Items that can be added to a batch, keyed by id, each with its units (code => [label, factor]). */
function batches_addition_item_catalog(PDO $pdo): array
{
    $items = [];
    foreach ($pdo->query("SELECT id, code, name, item_class, base_unit_code, lot_controlled, consumption_mode FROM app.items WHERE active AND item_class IN ('additive', 'yeast', 'juice', 'intermediate') ORDER BY code") as $row) {
        $row['units'] = [$row['base_unit_code'] => ['label' => $row['base_unit_code'], 'factor' => 1.0]];
        $items[(int) $row['id']] = $row;
    }
    foreach ($pdo->query('SELECT item_id, unit_code, unit_name, to_base_factor FROM app.item_units ORDER BY unit_code') as $unit) {
        if (isset($items[(int) $unit['item_id']])) {
            $items[(int) $unit['item_id']]['units'][$unit['unit_code']] = ['label' => $unit['unit_name'] . ' (' . $unit['unit_code'] . ')', 'factor' => (float) $unit['to_base_factor']];
        }
    }
    return $items;
}

/** Released lots of an item with stock, FEFO order: lot_id => row (location_id = primary location). */
function find_item_lot_options(PDO $pdo, int $itemId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT b.lot_id, b.lot_number, b.item_id, b.item_class, b.base_unit_code, b.expires_on, b.unit_cost_base, sum(b.qty_on_hand) AS on_hand
        FROM app.v_lot_balances b
        WHERE b.item_id = :item AND b.quality_status = 'released' AND b.qty_on_hand > 0
        GROUP BY b.lot_id, b.lot_number, b.item_id, b.item_class, b.base_unit_code, b.expires_on, b.received_on, b.unit_cost_base
        ORDER BY b.expires_on NULLS LAST, b.received_on NULLS LAST, b.lot_number
    SQL);
    $statement->execute(['item' => $itemId]);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $row['label'] = $row['lot_number'] . ' · ' . fmt_qty($row['on_hand'], $row['base_unit_code'], 3) . ' · ' . ($row['expires_on'] ? format_date($row['expires_on']) : 'no expiry');
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Active batches for the blend form: id => row with label "number · product · 120.0 gal". */
function batches_blend_input_options(PDO $pdo): array
{
    $rows = [];
    foreach ($pdo->query("SELECT b.id, b.number, b.product_id, p.name AS product_name, b.current_volume_l, b.fruit_share_pct, b.premises_id FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.status = 'active' AND b.current_volume_l > 0 ORDER BY b.number") as $row) {
        $row['label'] = $row['number'] . ' · ' . $row['product_name'] . ' · ' . fmt_qty($row['current_volume_l'], 'L');
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

/** Locations of the given kinds, id => name. */
function batches_location_options(PDO $pdo, array $kinds = []): array
{
    $sql = 'SELECT id, name FROM app.locations WHERE active';
    if ($kinds !== []) {
        $sql .= ' AND kind IN (' . implode(', ', array_map(static fn($i) => ':k' . $i, array_keys($kinds))) . ')';
    }
    $statement = $pdo->prepare($sql . ' ORDER BY name');
    foreach (array_values($kinds) as $i => $kind) {
        $statement->bindValue('k' . $i, $kind);
    }
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Batch reads -------------------------------------------------------------------------------

function batches_list_filter(string $search, array $statusFilter): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(b.number ILIKE :s OR p.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($statusFilter !== []) {
        $marks = [];
        foreach (array_values($statusFilter) as $i => $status) {
            $marks[] = ':st' . $i;
            $params['st' . $i] = $status;
        }
        $where[] = 'b.status IN (' . implode(', ', $marks) . ')';
    }
    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
}

function find_batches(PDO $pdo, string $search = '', array $statusFilter = ['active'], string $sort = '-started_at', int $page = 1): array
{
    [$where, $params] = batches_list_filter($search, $statusFilter);
    $from = ' FROM app.batches b JOIN app.products p ON p.id = b.product_id JOIN app.stages st ON st.code = b.current_stage_code';
    return paged_query($pdo,
        "SELECT b.id, b.number, b.status, b.current_stage_code, st.name AS stage_name, b.current_volume_l, b.started_at,
                COALESCE(b.tax_class_override, b.tax_class_derived) AS tax_class, p.name AS product_name,
                (SELECT string_agg(v.name, ', ' ORDER BY v.name) FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
                  WHERE o.occupant_kind = 'batch' AND o.occupant_id = b.id AND o.to_at IS NULL) AS vessels"
            . $from . $where . ' ORDER BY ' . order_by($sort, BATCH_SORTS, '-started_at') . ', b.id DESC',
        'SELECT count(*)' . $from . $where, $params, $page);
}

function count_batches(PDO $pdo, string $search = '', array $statusFilter = []): int
{
    [$where, $params] = batches_list_filter($search, $statusFilter);
    $statement = $pdo->prepare('SELECT count(*) FROM app.batches b JOIN app.products p ON p.id = b.product_id' . $where);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function find_batch(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT b.*, p.name AS product_name, p.code AS product_code, p.beverage_type, p.contains_other_fruit, p.contains_flavoring,
               rv.version_no, po.number AS po_number, po.status AS po_status, st.name AS stage_name, st.display_order AS stage_order,
               rc.name AS override_reason_name, uo.display_name AS override_by_name,
               se.id AS stage_event_id, se.entered_at AS stage_entered_at,
               COALESCE(b.tax_class_override, b.tax_class_derived) AS tax_class,
               (current_date - COALESCE(se.entered_at, b.started_at)::date) AS days_in_stage
        FROM app.batches b
        JOIN app.products p ON p.id = b.product_id
        JOIN app.stages st ON st.code = b.current_stage_code
        LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
        LEFT JOIN app.production_orders po ON po.id = b.production_order_id
        LEFT JOIN app.reason_codes rc ON rc.id = b.tax_class_override_reason_code_id
        LEFT JOIN app.users uo ON uo.id = b.tax_class_override_by
        LEFT JOIN LATERAL (SELECT s.id, s.entered_at FROM app.stage_events s WHERE s.batch_id = b.id AND s.left_at IS NULL ORDER BY s.entered_at DESC, s.id DESC LIMIT 1) se ON true
        WHERE b.id = :id
    SQL . ($lock ? ' FOR UPDATE OF b' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_batch_readings(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.*, m.name AS measurement_name, m.unit, m.decimals, st.name AS stage_name, u.display_name AS analyst_name, sp.min_value, sp.max_value
        FROM app.readings r
        JOIN app.measurement_types m ON m.code = r.measurement_type_code
        LEFT JOIN app.stages st ON st.code = r.stage_code
        LEFT JOIN app.users u ON u.id = r.analyst_id
        LEFT JOIN app.specs sp ON sp.id = r.spec_id
        WHERE r.target_kind = 'batch' AND r.target_id = :id
        ORDER BY r.taken_at DESC, r.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_batch_consumptions(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT c.*, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.lot_number, st.name AS stage_name, u.display_name AS actor_name
        FROM app.consumptions c
        JOIN app.items i ON i.id = c.item_id
        JOIN app.lots l ON l.id = c.lot_id
        LEFT JOIN app.stages st ON st.code = c.stage_code
        LEFT JOIN app.users u ON u.id = c.actor_id
        WHERE c.batch_id = :id ORDER BY c.consumed_at DESC, c.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_batch_transfers(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.*, vf.name AS from_vessel_name, vt.name AS to_vessel_name, u.display_name AS actor_name
        FROM app.batch_transfers t JOIN app.vessels vf ON vf.id = t.from_vessel_id JOIN app.vessels vt ON vt.id = t.to_vessel_id
        LEFT JOIN app.users u ON u.id = t.actor_id
        WHERE t.batch_id = :id ORDER BY t.transferred_at DESC, t.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_batch_losses(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT le.*, st.name AS stage_name, rc.code AS reason_code, rc.name AS reason_name, rc.requires_approval_above,
               u.display_name AS actor_name, ua.display_name AS approved_by_name,
               (rc.requires_approval_above IS NOT NULL AND le.qty_base > rc.requires_approval_above) AS needs_approval
        FROM app.loss_events le
        JOIN app.reason_codes rc ON rc.id = le.reason_code_id
        LEFT JOIN app.stages st ON st.code = le.stage_code
        LEFT JOIN app.users u ON u.id = le.actor_id
        LEFT JOIN app.users ua ON ua.id = le.approved_by
        WHERE le.target_kind = 'batch' AND le.target_id = :id ORDER BY le.occurred_at DESC, le.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_batch_loss(PDO $pdo, int $batchId, int $lossId, bool $lock = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT le.*, rc.code AS reason_code, rc.name AS reason_name, rc.requires_approval_above
        FROM app.loss_events le JOIN app.reason_codes rc ON rc.id = le.reason_code_id
        WHERE le.id = :id AND le.target_kind = 'batch' AND le.target_id = :batch
    SQL . ($lock ? ' FOR UPDATE OF le' : ''));
    $statement->execute(['id' => $lossId, 'batch' => $batchId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Parents and children from batch_lineage: ['parents' => [...], 'children' => [...]]. */
function find_batch_lineage(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT bl.*, 'parent' AS direction, b.id AS other_id, b.number AS other_number, b.status AS other_status
        FROM app.batch_lineage bl JOIN app.batches b ON b.id = bl.parent_batch_id WHERE bl.child_batch_id = :id
        UNION ALL
        SELECT bl.*, 'child', b.id, b.number, b.status
        FROM app.batch_lineage bl JOIN app.batches b ON b.id = bl.child_batch_id WHERE bl.parent_batch_id = :id
        ORDER BY created_at, id
    SQL);
    $statement->execute(['id' => $id]);
    $rows = $statement->fetchAll();
    return [
        'parents' => array_values(array_filter($rows, static fn($r) => $r['direction'] === 'parent')),
        'children' => array_values(array_filter($rows, static fn($r) => $r['direction'] === 'child')),
    ];
}

/** Juice and fruit lots upstream of the batch (app.trace_backward levels 1 and 2). */
function find_batch_trace(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare("SELECT level, kind, id, label, detail FROM app.trace_backward(:id) WHERE level IN (1, 2) AND kind IN ('lot', 'fruit_lot') ORDER BY level, label");
    $statement->execute(['id' => $id]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row['detail'] = json_decode((string) $row['detail'], true) ?: [];
    }
    return $rows;
}

function find_batch_cost(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_batch_costs WHERE batch_id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_batch_stage_yields(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_batch_stage_yields WHERE batch_id = :id ORDER BY entered_at, stage_code');
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Everything batch-view shows. */
function batch_view_data(PDO $pdo, array $batch): array
{
    $id = (int) $batch['id'];
    return [
        'batch' => $batch, 'vessels' => find_batch_vessels($pdo, $id), 'readings' => find_batch_readings($pdo, $id),
        'consumptions' => find_batch_consumptions($pdo, $id), 'transfers' => find_batch_transfers($pdo, $id),
        'losses' => find_batch_losses($pdo, $id), 'lineage' => find_batch_lineage($pdo, $id), 'trace' => find_batch_trace($pdo, $id),
        'cost' => find_batch_cost($pdo, $id), 'yields' => find_batch_stage_yields($pdo, $id),
    ];
}

/** Starting volume: the largest volume that entered a stage (as app.v_batch_costs), else the current volume. */
function batch_starting_volume(PDO $pdo, int $id): float
{
    $statement = $pdo->prepare('SELECT COALESCE((SELECT max(volume_in_l) FROM app.stage_events WHERE batch_id = :id), (SELECT current_volume_l FROM app.batches WHERE id = :id))');
    $statement->execute(['id' => $id]);
    return (float) $statement->fetchColumn();
}

// Batch writes ------------------------------------------------------------------------------

function insert_batch(PDO $pdo, array $b): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.batches (number, premises_id, product_id, recipe_version_id, production_order_id, origin_kind, started_at, current_stage_code,
                                 status, current_volume_l, fruit_share_pct, tax_class_derived, notes, created_by)
        VALUES (app.next_number('batch'), :premises, :product, :recipe, :po, :origin, :started, :stage, 'active', :volume, :share, :tax, :notes, :by)
        RETURNING id, number, premises_id, product_id, origin_kind, started_at, current_stage_code, status, current_volume_l, fruit_share_pct
    SQL);
    $statement->execute([
        'premises' => $b['premises_id'], 'product' => $b['product_id'], 'recipe' => $b['recipe_version_id'] ?? null, 'po' => $b['production_order_id'] ?? null,
        'origin' => $b['origin_kind'], 'started' => $b['started_at'], 'stage' => $b['current_stage_code'], 'volume' => round((float) $b['current_volume_l'], 3),
        'share' => $b['fruit_share_pct'] === null ? null : round((float) $b['fruit_share_pct'], 2), 'tax' => $b['tax_class_derived'] ?? null,
        'notes' => $b['notes'] ?? null, 'by' => $b['created_by'],
    ]);
    return $statement->fetch();
}

function set_batch_volume(PDO $pdo, int $id, float $volumeL): void
{
    $pdo->prepare('UPDATE app.batches SET current_volume_l = :v WHERE id = :id')->execute(['v' => round(max(0.0, $volumeL), 3), 'id' => $id]);
}

function close_batch(PDO $pdo, int $id, string $status, string $at, ?string $note): void
{
    $pdo->prepare("UPDATE app.batches SET status = :s, current_volume_l = 0, closed_at = :at, notes = CASE WHEN :note::text IS NULL THEN notes ELSE concat_ws(E'\n', notes, :note::text) END WHERE id = :id")
        ->execute(['s' => $status, 'at' => $at, 'note' => $note, 'id' => $id]);
}

function insert_stage_event(PDO $pdo, int $batchId, string $stageCode, string $enteredAt, ?float $volumeInL, int $actorId, ?string $note = null): int
{
    $statement = $pdo->prepare('INSERT INTO app.stage_events (batch_id, stage_code, entered_at, volume_in_l, actor_id, note) VALUES (:b, :s, :at, :vol, :by, :note) RETURNING id');
    $statement->execute(['b' => $batchId, 's' => $stageCode, 'at' => $enteredAt, 'vol' => $volumeInL === null ? null : round($volumeInL, 3), 'by' => $actorId, 'note' => $note]);
    return (int) $statement->fetchColumn();
}

/** Close the batch's open stage event(s). */
function close_stage_event(PDO $pdo, int $batchId, string $leftAt, ?float $volumeOutL): void
{
    $pdo->prepare('UPDATE app.stage_events SET left_at = :at, volume_out_l = :vol WHERE batch_id = :b AND left_at IS NULL')
        ->execute(['at' => $leftAt, 'vol' => $volumeOutL === null ? null : round($volumeOutL, 3), 'b' => $batchId]);
}

function insert_consumption(PDO $pdo, ?int $batchId, ?int $pressRunId, int $itemId, int $lotId, float $qtyBase, string $purpose, ?string $stageCode, ?float $plannedQtyBase, string $consumedAt, int $actorId, string $groupId, ?string $note = null): int
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.consumptions (batch_id, press_run_id, item_id, lot_id, qty_base, purpose, stage_code, planned_qty_base, consumed_at, actor_id, ledger_group_id, note)
        VALUES (:b, :pr, :item, :lot, :qty, :purpose, :stage, :planned, :at, :by, :grp, :note) RETURNING id
    SQL);
    $statement->execute(['b' => $batchId, 'pr' => $pressRunId, 'item' => $itemId, 'lot' => $lotId, 'qty' => round($qtyBase, 4), 'purpose' => $purpose, 'stage' => $stageCode,
        'planned' => $plannedQtyBase === null ? null : round($plannedQtyBase, 4), 'at' => $consumedAt, 'by' => $actorId, 'grp' => $groupId, 'note' => $note]);
    return (int) $statement->fetchColumn();
}

/** Issue a lot into a batch: one consumption row and its ledger issue (reference batch_consumption). Returns the consumption id. */
function batches_consume_lot(PDO $pdo, array $batch, int $itemId, int $lotId, int $locationId, float $qtyBase, float $unitCost, string $purpose, ?string $stageCode, ?float $plannedQtyBase, string $at, int $actorId, string $groupId, ?string $note = null): int
{
    $consumptionId = insert_consumption($pdo, (int) $batch['id'], null, $itemId, $lotId, $qtyBase, $purpose, $stageCode, $plannedQtyBase, $at, $actorId, $groupId, $note);
    insert_inventory_transaction($pdo, $groupId, 'issue', $itemId, $lotId, $locationId, (int) $batch['premises_id'], -round($qtyBase, 4), $unitCost,
        'batch', (int) $batch['id'], null, 'used_in_production', 'batch_consumption', $consumptionId, 'batch_consumption:' . $consumptionId . ':1', $at, $actorId, $note);
    return $consumptionId;
}

function insert_loss_event(PDO $pdo, int $batchId, int $premisesId, ?string $stageCode, float $qtyL, array $reason, string $classification, string $occurredAt, int $actorId, ?string $note): int
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.loss_events (target_kind, target_id, premises_id, stage_code, qty_base, unit_code, reason_code_id, ttb_category, reportable, classification, occurred_at, actor_id, note)
        VALUES ('batch', :b, :premises, :stage, :qty, 'L', :reason, :ttb, :reportable, :class, :at, :by, :note) RETURNING id
    SQL);
    $statement->execute(['b' => $batchId, 'premises' => $premisesId, 'stage' => $stageCode, 'qty' => round($qtyL, 4), 'reason' => $reason['id'],
        'ttb' => $reason['ttb_category'], 'reportable' => $reason['ttb_category'] !== 'none' ? 't' : 'f', 'class' => $classification, 'at' => $occurredAt, 'by' => $actorId, 'note' => $note]);
    return (int) $statement->fetchColumn();
}

/** tax_class_derived from the latest ABV and CO2 readings, fruit share and the product flags. */
function recompute_batch_tax_class(PDO $pdo, int $id): void
{
    $pdo->prepare(<<<'SQL'
        UPDATE app.batches b SET tax_class_derived = app.derive_tax_class('cider',
            (SELECT r.value FROM app.readings r WHERE r.target_kind = 'batch' AND r.target_id = b.id AND r.measurement_type_code = 'abv' ORDER BY r.taken_at DESC, r.id DESC LIMIT 1),
            (SELECT r.value FROM app.readings r WHERE r.target_kind = 'batch' AND r.target_id = b.id AND r.measurement_type_code = 'co2' ORDER BY r.taken_at DESC, r.id DESC LIMIT 1),
            b.fruit_share_pct, p.contains_other_fruit, p.contains_flavoring)
        FROM app.products p WHERE p.id = b.product_id AND b.id = :id
    SQL)->execute(['id' => $id]);
}

/** Batch-level weighted fruit share. */
function batches_weighted_share(array $parts): ?float
{
    $volume = 0.0;
    $weighted = 0.0;
    foreach ($parts as [$liters, $share]) {
        $volume += $liters;
        $weighted += $liters * ($share ?? 100.0);
    }
    return $volume > 0 ? $weighted / $volume : null;
}

/**
 * Pitch: create the batch from juice lots and a yeast lot (spec steps 1 to 6). Caller owns
 * the transaction, validation and the activity row. $input: product_id, recipe_version_id,
 * production_order_id, vessel (catalog row), juice (list of [lot option row, volume_l]),
 * yeast (lot option row), yeast_qty, started_at (ATOM), notes.
 */
function pitch_batch(PDO $pdo, array $input, int $userId): array
{
    $vessel = $input['vessel'];
    $at = $input['started_at'];
    $volume = round(array_sum(array_map(static fn($j) => $j[1], $input['juice'])), 3);
    $share = batches_weighted_share(array_map(static fn($j) => [$j[1], $j[0]['fruit_share_pct'] === null ? null : (float) $j[0]['fruit_share_pct']], $input['juice']));
    $batch = insert_batch($pdo, [
        'premises_id' => (int) $vessel['premises_id'], 'product_id' => $input['product_id'], 'recipe_version_id' => $input['recipe_version_id'],
        'production_order_id' => $input['production_order_id'], 'origin_kind' => 'pitch', 'started_at' => $at, 'current_stage_code' => 'pitch',
        'current_volume_l' => $volume, 'fruit_share_pct' => $share, 'notes' => $input['notes'], 'created_by' => $userId,
    ]);
    $group = new_group_id();
    $consumed = [];
    foreach ($input['juice'] as [$lot, $liters]) {
        batches_consume_lot($pdo, $batch, (int) $lot['item_id'], (int) $lot['lot_id'], (int) $lot['location_id'], $liters, (float) $lot['unit_cost_base'],
            'base_juice', 'pitch', null, $at, $userId, $group);
        batches_draw_lot_from_vessel($pdo, (int) $lot['lot_id'], $liters, $at, (int) $vessel['id']);
        $consumed[(int) $lot['item_id']] = ($consumed[(int) $lot['item_id']] ?? 0) + $liters;
    }
    $yeast = $input['yeast'];
    $yeastLocation = find_lot_primary_location($pdo, (int) $yeast['lot_id']);
    batches_consume_lot($pdo, $batch, (int) $yeast['item_id'], (int) $yeast['lot_id'], (int) ($yeastLocation['location_id'] ?? 0), (float) $input['yeast_qty'],
        (float) $yeast['unit_cost_base'], 'yeast', 'pitch', null, $at, $userId, $group);
    $consumed[(int) $yeast['item_id']] = ($consumed[(int) $yeast['item_id']] ?? 0) + (float) $input['yeast_qty'];
    insert_vessel_occupancy($pdo, (int) $vessel['id'], 'batch', (int) $batch['id'], $volume, $at);
    set_vessel_status($pdo, (int) $vessel['id'], 'in_use');
    insert_stage_event($pdo, (int) $batch['id'], 'pitch', $at, $volume, $userId);
    if ($input['production_order_id'] !== null) {
        $pdo->prepare("UPDATE app.production_orders SET status = 'in_progress' WHERE id = :id AND status = 'released'")->execute(['id' => $input['production_order_id']]);
        batches_release_allocations($pdo, (int) $input['production_order_id'], $consumed);
    }
    return $batch + ['ledger_group_id' => $group];
}

/** Mark an order's open allocations released, item by item, up to the consumed quantity. */
function batches_release_allocations(PDO $pdo, int $productionOrderId, array $consumedByItem): void
{
    $statement = $pdo->prepare('SELECT id, item_id, qty_base FROM app.allocations WHERE production_order_id = :id AND released_at IS NULL ORDER BY id FOR UPDATE');
    $statement->execute(['id' => $productionOrderId]);
    $release = $pdo->prepare('UPDATE app.allocations SET released_at = now() WHERE id = :id');
    foreach ($statement->fetchAll() as $allocation) {
        $item = (int) $allocation['item_id'];
        if (($consumedByItem[$item] ?? 0) > 0) {
            $release->execute(['id' => $allocation['id']]);
            $consumedByItem[$item] -= (float) $allocation['qty_base'];
        }
    }
}

function update_batch(PDO $pdo, int $id, ?string $notes, ?string $taxClassOverride, ?int $reasonCodeId, int $userId, bool $overrideChanged): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.batches SET notes = :notes,
               tax_class_override = CASE WHEN :changed THEN :override ELSE tax_class_override END,
               tax_class_override_reason_code_id = CASE WHEN :changed THEN :reason::bigint ELSE tax_class_override_reason_code_id END,
               tax_class_override_by = CASE WHEN :changed THEN :by::bigint ELSE tax_class_override_by END,
               tax_class_override_at = CASE WHEN :changed THEN now() ELSE tax_class_override_at END
        WHERE id = :id
        RETURNING id, number, notes, tax_class_override, tax_class_override_reason_code_id
    SQL);
    $statement->bindValue('notes', $notes);
    $statement->bindValue('changed', $overrideChanged, PDO::PARAM_BOOL);
    $statement->bindValue('override', $taxClassOverride);
    $statement->bindValue('reason', $taxClassOverride === null ? null : $reasonCodeId);
    $statement->bindValue('by', $taxClassOverride === null ? null : $userId);
    $statement->bindValue('id', $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetch();
}

/** Insert a batch reading; the 010 trigger sets spec_id/spec_result. Returns the row with the spec range. */
function record_batch_reading(PDO $pdo, int $batchId, string $measurement, float $value, string $takenAt, ?string $stageCode, ?string $method, ?string $note, int $userId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        WITH r AS (
            INSERT INTO app.readings (target_kind, target_id, measurement_type_code, value, taken_at, stage_code, method, is_lab, analyst_id, note)
            VALUES ('batch', :b, :m, :v, :at, :stage, :method, false, :by, :note)
            RETURNING id, measurement_type_code, value, taken_at, stage_code, spec_id, spec_result
        )
        SELECT r.*, sp.min_value, sp.max_value FROM r LEFT JOIN app.specs sp ON sp.id = r.spec_id
    SQL);
    $statement->execute(['b' => $batchId, 'm' => $measurement, 'v' => $value, 'at' => $takenAt, 'stage' => $stageCode, 'method' => $method, 'by' => $userId, 'note' => $note]);
    $row = $statement->fetch();
    recompute_batch_tax_class($pdo, $batchId);
    return $row;
}

/** Planned quantity from the recipe line with the same item and stage, scaled to the starting volume. */
function batches_planned_qty(PDO $pdo, array $batch, int $itemId, string $stageCode): ?float
{
    if ($batch['recipe_version_id'] === null) {
        return null;
    }
    $statement = $pdo->prepare('SELECT qty_per_batch_base, qty_per_l FROM app.recipe_lines WHERE recipe_version_id = :rv AND item_id = :item AND stage_code = :stage ORDER BY seq LIMIT 1');
    $statement->execute(['rv' => $batch['recipe_version_id'], 'item' => $itemId, 'stage' => $stageCode]);
    $line = $statement->fetch();
    if ($line === false) {
        return null;
    }
    return $line['qty_per_batch_base'] !== null ? (float) $line['qty_per_batch_base'] : (float) $line['qty_per_l'] * batch_starting_volume($pdo, (int) $batch['id']);
}

/**
 * Addition: consume a lot into the batch. Juice/intermediate liters join the batch volume;
 * juice or a sweetener measured in liters recomputes the fruit share and tax class.
 */
function record_batch_addition(PDO $pdo, array $batch, array $item, array $lot, float $qtyBase, string $purpose, string $stageCode, string $addedAt, ?string $note, int $userId): array
{
    $group = new_group_id();
    $planned = batches_planned_qty($pdo, $batch, (int) $item['id'], $stageCode);
    $location = find_lot_occupancy($pdo, (int) $lot['lot_id']);
    $locationId = $location !== null ? (int) $location['location_id'] : (int) (find_lot_primary_location($pdo, (int) $lot['lot_id'])['location_id'] ?? 0);
    $consumptionId = batches_consume_lot($pdo, $batch, (int) $item['id'], (int) $lot['lot_id'], $locationId, $qtyBase, (float) $lot['unit_cost_base'],
        $purpose, $stageCode, $planned, $addedAt, $userId, $group, $note);
    $isLiquid = in_array($item['item_class'], ['juice', 'intermediate'], true);
    $volume = (float) $batch['current_volume_l'];
    $share = $batch['fruit_share_pct'] === null ? null : (float) $batch['fruit_share_pct'];
    if ($isLiquid) {
        batches_draw_lot_from_vessel($pdo, (int) $lot['lot_id'], $qtyBase, $addedAt);
        $lotShare = find_lot_attribute($pdo, (int) $lot['lot_id'], 'fruit_share_pct');
        $share = batches_weighted_share([[$volume, $share], [$qtyBase, $lotShare !== null ? (float) $lotShare['value_num'] : 100.0]]);
        $volume += $qtyBase;
        set_batch_volume($pdo, (int) $batch['id'], $volume);
        batches_add_to_occupancy($pdo, (int) $batch['id'], $qtyBase);
    } elseif ($purpose === 'sweetener' && $item['base_unit_code'] === 'L') {
        $share = batches_weighted_share([[$volume, $share], [$qtyBase, 0.0]]);
    }
    if ($isLiquid || $purpose === 'sweetener') {
        $pdo->prepare('UPDATE app.batches SET fruit_share_pct = :s WHERE id = :id')->execute(['s' => $share === null ? null : round($share, 2), 'id' => $batch['id']]);
        recompute_batch_tax_class($pdo, (int) $batch['id']);
    }
    return ['consumption_id' => $consumptionId, 'planned_qty_base' => $planned, 'volume_l' => round($volume, 3), 'fruit_share_pct' => $share === null ? null : round($share, 2), 'ledger_group_id' => $group];
}

/** Stage move (spec): close the open stage event, open the next, record the expected loss of any volume left behind. */
function move_batch_stage(PDO $pdo, array $batch, string $toStage, string $movedAt, float $volumeOutL, ?string $note, int $userId): array
{
    $id = (int) $batch['id'];
    $from = (string) $batch['current_stage_code'];
    $lossL = round((float) $batch['current_volume_l'] - $volumeOutL, 3);
    close_stage_event($pdo, $id, $movedAt, $volumeOutL);
    $eventId = insert_stage_event($pdo, $id, $toStage, $movedAt, $volumeOutL, $userId, $note);
    $lossId = null;
    if ($lossL > 0) {
        $reason = find_reason_code_by_code($pdo, BATCH_STAGE_LOSS_REASONS[$from] ?? 'RACK') ?? throw new RuntimeException('Reason code RACK is missing.');
        $lossId = insert_loss_event($pdo, $id, (int) $batch['premises_id'], $from, $lossL, $reason, 'expected', $movedAt, $userId, 'Stage move ' . $from . ' → ' . $toStage);
        batches_reduce_occupancies($pdo, $id, $lossL, $movedAt);
    }
    $pdo->prepare('UPDATE app.batches SET current_stage_code = :s, current_volume_l = :v WHERE id = :id')->execute(['s' => $toStage, 'v' => round($volumeOutL, 3), 'id' => $id]);
    return ['stage_event_id' => $eventId, 'loss_event_id' => $lossId, 'loss_l' => max(0.0, $lossL)];
}

/** Vessel to vessel. The receiving vessel gets volume − loss; the loss is an expected RACK loss. */
function transfer_batch(PDO $pdo, array $batch, array $fromOccupancy, array $toVessel, float $volumeL, float $lossL, string $at, ?string $note, int $userId): array
{
    $id = (int) $batch['id'];
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.batch_transfers (batch_id, from_vessel_id, to_vessel_id, volume_l, loss_l, transferred_at, actor_id, note)
        VALUES (:b, :from, :to, :vol, :loss, :at, :by, :note) RETURNING id
    SQL);
    $statement->execute(['b' => $id, 'from' => $fromOccupancy['vessel_id'], 'to' => $toVessel['id'], 'vol' => round($volumeL, 3), 'loss' => round($lossL, 3), 'at' => $at, 'by' => $userId, 'note' => $note]);
    $transferId = (int) $statement->fetchColumn();
    $remaining = round((float) $fromOccupancy['volume_l'] - $volumeL, 3);
    if ($remaining <= 0.0005) {
        close_occupancy($pdo, (int) $fromOccupancy['occupancy_id'], $at, 0.0);
        set_vessel_status($pdo, (int) $fromOccupancy['vessel_id'], 'empty');
    } else {
        set_occupancy_volume($pdo, (int) $fromOccupancy['occupancy_id'], $remaining);
    }
    $arriving = round($volumeL - $lossL, 3);
    if ($toVessel['occupancy_id'] !== null && $toVessel['occupant_kind'] === 'batch' && (int) $toVessel['occupant_id'] === $id) {
        set_occupancy_volume($pdo, (int) $toVessel['occupancy_id'], (float) $toVessel['occupancy_volume_l'] + $arriving);
    } elseif ($arriving > 0) {
        insert_vessel_occupancy($pdo, (int) $toVessel['id'], 'batch', $id, $arriving, $at);
    }
    set_vessel_status($pdo, (int) $toVessel['id'], 'in_use');
    $lossId = null;
    if ($lossL > 0) {
        $reason = find_reason_code_by_code($pdo, 'RACK') ?? throw new RuntimeException('Reason code RACK is missing.');
        $lossId = insert_loss_event($pdo, $id, (int) $batch['premises_id'], (string) $batch['current_stage_code'], $lossL, $reason, 'expected', $at, $userId, 'Transfer loss');
        set_batch_volume($pdo, $id, (float) $batch['current_volume_l'] - $lossL);
    }
    return ['transfer_id' => $transferId, 'loss_event_id' => $lossId];
}

/** Split into child batches (one per output row: vessel row + volume_l). */
function split_batch(PDO $pdo, array $batch, array $outputs, string $at, ?string $note, int $userId): array
{
    $id = (int) $batch['id'];
    $before = (float) $batch['current_volume_l'];
    $statement = $pdo->prepare('INSERT INTO app.batch_splits (source_batch_id, split_at, actor_id, note) VALUES (:b, :at, :by, :note) RETURNING id');
    $statement->execute(['b' => $id, 'at' => $at, 'by' => $userId, 'note' => $note]);
    $splitId = (int) $statement->fetchColumn();
    $total = round(array_sum(array_column($outputs, 'volume_l')), 3);
    // Free the source vessels first so a child may take over the source vessel when it is emptied.
    batches_reduce_occupancies($pdo, $id, $total, $at);
    $children = [];
    $output = $pdo->prepare('INSERT INTO app.batch_split_outputs (split_id, child_batch_id, vessel_id, volume_l) VALUES (:s, :c, :v, :vol)');
    foreach ($outputs as $row) {
        $child = insert_batch($pdo, [
            'premises_id' => (int) $batch['premises_id'], 'product_id' => (int) $batch['product_id'], 'recipe_version_id' => $batch['recipe_version_id'],
            'production_order_id' => $batch['production_order_id'], 'origin_kind' => 'split', 'started_at' => $at, 'current_stage_code' => $batch['current_stage_code'],
            'current_volume_l' => $row['volume_l'], 'fruit_share_pct' => $batch['fruit_share_pct'], 'tax_class_derived' => $batch['tax_class_derived'], 'created_by' => $userId,
        ]);
        $output->execute(['s' => $splitId, 'c' => $child['id'], 'v' => $row['vessel']['id'], 'vol' => round($row['volume_l'], 3)]);
        insert_batch_lineage($pdo, (int) $child['id'], $id, 'split', $splitId, $row['volume_l'], $row['volume_l'] / $before);
        insert_stage_event($pdo, (int) $child['id'], (string) $batch['current_stage_code'], $at, $row['volume_l'], $userId);
        insert_vessel_occupancy($pdo, (int) $row['vessel']['id'], 'batch', (int) $child['id'], $row['volume_l'], $at);
        set_vessel_status($pdo, (int) $row['vessel']['id'], 'in_use');
        $children[] = ['id' => (int) $child['id'], 'number' => $child['number'], 'vessel' => $row['vessel']['name'], 'volume_l' => round($row['volume_l'], 3)];
    }
    $remaining = round($before - $total, 3);
    if ($remaining <= 0.0005) {
        close_stage_event($pdo, $id, $at, $total);
        close_batch($pdo, $id, 'closed', $at, 'split into ' . implode(', ', array_column($children, 'number')));
    } else {
        set_batch_volume($pdo, $id, $remaining);
    }
    return ['split_id' => $splitId, 'children' => $children, 'remaining_l' => max(0.0, $remaining)];
}

function insert_batch_lineage(PDO $pdo, int $childId, int $parentId, string $eventKind, int $eventId, float $volumeL, float $fraction): void
{
    $pdo->prepare('INSERT INTO app.batch_lineage (child_batch_id, parent_batch_id, event_kind, event_id, volume_l, fraction) VALUES (:c, :p, :k, :e, :v, :f)')
        ->execute(['c' => $childId, 'p' => $parentId, 'k' => $eventKind, 'e' => $eventId, 'v' => round($volumeL, 3), 'f' => round(min(1.0, $fraction), 6)]);
}

/**
 * Blend: a new batch from several inputs ([batch row, volume_l]) in one vessel. Inputs that
 * reach 0 close (status closed, stage event closed). Throws when the vessel still holds
 * something after the inputs have been drawn (I3).
 */
function blend_batches(PDO $pdo, array $inputs, array $vessel, int $productId, ?int $recipeVersionId, string $at, ?string $note, int $userId): array
{
    $total = round(array_sum(array_map(static fn($i) => $i[1], $inputs)), 3);
    $share = batches_weighted_share(array_map(static fn($i) => [$i[1], $i[0]['fruit_share_pct'] === null ? null : (float) $i[0]['fruit_share_pct']], $inputs));
    $result = insert_batch($pdo, [
        'premises_id' => (int) $vessel['premises_id'], 'product_id' => $productId, 'recipe_version_id' => $recipeVersionId, 'production_order_id' => null,
        'origin_kind' => 'blend', 'started_at' => $at, 'current_stage_code' => 'blend', 'current_volume_l' => $total, 'fruit_share_pct' => $share, 'notes' => $note, 'created_by' => $userId,
    ]);
    $statement = $pdo->prepare('INSERT INTO app.batch_blends (result_batch_id, vessel_id, volume_out_l, blended_at, actor_id, note) VALUES (:r, :v, :vol, :at, :by, :note) RETURNING id');
    $statement->execute(['r' => $result['id'], 'v' => $vessel['id'], 'vol' => $total, 'at' => $at, 'by' => $userId, 'note' => $note]);
    $blendId = (int) $statement->fetchColumn();
    $inputRow = $pdo->prepare('INSERT INTO app.batch_blend_inputs (blend_id, source_batch_id, volume_l) VALUES (:b, :s, :v)');
    $logged = [];
    foreach ($inputs as [$input, $liters]) {
        $inputId = (int) $input['id'];
        $inputRow->execute(['b' => $blendId, 's' => $inputId, 'v' => round($liters, 3)]);
        insert_batch_lineage($pdo, (int) $result['id'], $inputId, 'blend', $blendId, $liters, $liters / $total);
        batches_reduce_occupancies($pdo, $inputId, $liters, $at, (int) $vessel['id']);
        $remaining = round((float) $input['current_volume_l'] - $liters, 3);
        if ($remaining <= 0.0005) {
            close_stage_event($pdo, $inputId, $at, $liters);
            close_batch($pdo, $inputId, 'closed', $at, 'blended into ' . $result['number']);
        } else {
            set_batch_volume($pdo, $inputId, $remaining);
        }
        $logged[] = ['batch' => $input['number'], 'volume_l' => round($liters, 3), 'closed' => $remaining <= 0.0005];
    }
    $now = find_vessel($pdo, (int) $vessel['id'], true);
    if ($now !== null && $now['occupancy_id'] !== null) {
        throw new RuntimeException(batches_occupied_message($now) . ' Blend the whole volume it holds or choose another vessel.');
    }
    insert_stage_event($pdo, (int) $result['id'], 'blend', $at, $total, $userId);
    insert_vessel_occupancy($pdo, (int) $vessel['id'], 'batch', (int) $result['id'], $total, $at);
    set_vessel_status($pdo, (int) $vessel['id'], 'in_use');
    recompute_batch_tax_class($pdo, (int) $result['id']);
    return ['blend_id' => $blendId, 'result' => $result, 'inputs' => $logged];
}

/** Loss with a reason code: reduces the batch volume and its occupancy. */
function record_batch_loss(PDO $pdo, array $batch, float $qtyL, array $reason, ?string $stageCode, string $occurredAt, ?string $note, int $userId): array
{
    $lossId = insert_loss_event($pdo, (int) $batch['id'], (int) $batch['premises_id'], $stageCode, $qtyL, $reason, (string) $reason['classification'], $occurredAt, $userId, $note);
    set_batch_volume($pdo, (int) $batch['id'], (float) $batch['current_volume_l'] - $qtyL);
    batches_reduce_occupancies($pdo, (int) $batch['id'], $qtyL, $occurredAt);
    $needsApproval = $reason['requires_approval_above'] !== null && $qtyL > (float) $reason['requires_approval_above'];
    return ['loss_event_id' => $lossId, 'needs_approval' => $needsApproval, 'volume_l' => round(max(0.0, (float) $batch['current_volume_l'] - $qtyL), 3)];
}

function approve_batch_loss(PDO $pdo, int $lossId, int $userId): array
{
    $statement = $pdo->prepare('UPDATE app.loss_events SET approved_by = :by, approved_at = now() WHERE id = :id AND approved_at IS NULL RETURNING id, qty_base, approved_by, approved_at');
    $statement->execute(['by' => $userId, 'id' => $lossId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('That loss is already approved.');
    }
    return $row;
}

/** Dump the whole batch: exceptional loss, vessels to cleaning, status dumped, order complete when it was the last active batch. */
function dump_batch(PDO $pdo, array $batch, array $reason, string $dumpedAt, string $note, int $userId): array
{
    $id = (int) $batch['id'];
    $volume = (float) $batch['current_volume_l'];
    $lossId = $volume > 0 ? insert_loss_event($pdo, $id, (int) $batch['premises_id'], (string) $batch['current_stage_code'], $volume, $reason, 'exceptional', $dumpedAt, $userId, $note) : null;
    foreach (find_batch_vessels($pdo, $id) as $occupancy) {
        close_occupancy($pdo, (int) $occupancy['occupancy_id'], $dumpedAt, 0.0);
        set_vessel_status($pdo, (int) $occupancy['vessel_id'], 'cleaning');
    }
    close_stage_event($pdo, $id, $dumpedAt, 0.0);
    close_batch($pdo, $id, 'dumped', $dumpedAt, null);
    $orderCompleted = false;
    if ($batch['production_order_id'] !== null) {
        $statement = $pdo->prepare(<<<'SQL'
            UPDATE app.production_orders po SET status = 'complete'
            WHERE po.id = :po AND po.status IN ('released', 'in_progress')
              AND NOT EXISTS (SELECT 1 FROM app.batches b WHERE b.production_order_id = po.id AND b.status = 'active' AND b.id <> :id)
            RETURNING id
        SQL);
        $statement->execute(['po' => $batch['production_order_id'], 'id' => $id]);
        $orderCompleted = $statement->fetchColumn() !== false;
    }
    return ['loss_event_id' => $lossId, 'volume_l' => round($volume, 3), 'order_completed' => $orderCompleted];
}

/** The batch's pitched yeast: item_id, lot_id, strain and generation attributes, or null. */
function find_batch_yeast(PDO $pdo, int $batchId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT c.item_id, c.lot_id, i.base_unit_code,
               (SELECT a.value_text FROM app.lot_attributes a WHERE a.lot_id = c.lot_id AND a.key = 'strain') AS strain,
               (SELECT a.value_num FROM app.lot_attributes a WHERE a.lot_id = c.lot_id AND a.key = 'generation') AS generation
        FROM app.consumptions c JOIN app.items i ON i.id = c.item_id
        WHERE c.batch_id = :id AND c.purpose = 'yeast' ORDER BY c.consumed_at, c.id LIMIT 1
    SQL);
    $statement->execute(['id' => $batchId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Yeast items keyed by id (base unit and shelf life). */
function batches_yeast_item_catalog(PDO $pdo): array
{
    $rows = [];
    foreach ($pdo->query("SELECT id, code, name, base_unit_code, shelf_life_days FROM app.items WHERE active AND item_class = 'yeast' ORDER BY code") as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

/** Harvest yeast from a batch into a new released lot with a production_output ledger row. */
function record_yeast_harvest(PDO $pdo, array $batch, array $item, int $generation, float $volumeL, ?float $cellCount, ?float $viability, string $harvestedAt, int $locationId, ?string $note, ?array $pitched, int $userId): array
{
    $producedOn = (new DateTimeImmutable($harvestedAt))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d');
    $expires = $item['shelf_life_days'] ? (new DateTimeImmutable($producedOn))->modify('+' . (int) $item['shelf_life_days'] . ' days')->format('Y-m-d') : null;
    $lot = insert_lot($pdo, '', (int) $item['id'], (int) $batch['premises_id'], null, null, null, $expires, 'released', 0.0, 'yeast_harvest', 0, $userId, $producedOn);
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.yeast_harvests (source_batch_id, lot_id, generation, harvested_at, volume_l, cell_count, viability_pct, actor_id, note)
        VALUES (:b, :lot, :gen, :at, :vol, :cells, :via, :by, :note) RETURNING id
    SQL);
    $statement->execute(['b' => $batch['id'], 'lot' => $lot['id'], 'gen' => $generation, 'at' => $harvestedAt, 'vol' => round($volumeL, 3), 'cells' => $cellCount, 'via' => $viability, 'by' => $userId, 'note' => $note]);
    $harvestId = (int) $statement->fetchColumn();
    $pdo->prepare('UPDATE app.lots SET source_id = :h WHERE id = :id')->execute(['h' => $harvestId, 'id' => $lot['id']]);
    if (!empty($pitched['strain'])) {
        set_lot_attribute($pdo, (int) $lot['id'], 'strain', null, (string) $pitched['strain'], null, 'derived', $userId);
    }
    set_lot_attribute($pdo, (int) $lot['id'], 'generation', (float) $generation, null, null, 'derived', $userId);
    set_lot_attribute($pdo, (int) $lot['id'], 'source_batch', null, (string) $batch['number'], null, 'derived', $userId);
    if ($viability !== null) {
        set_lot_attribute($pdo, (int) $lot['id'], 'viability_pct', $viability, null, '%', 'derived', $userId);
    }
    $group = new_group_id();
    insert_inventory_transaction($pdo, $group, 'production_output', (int) $item['id'], (int) $lot['id'], $locationId, (int) $batch['premises_id'], round($volumeL, 3), 0.0,
        'batch', (int) $batch['id'], null, 'none', 'yeast_harvest', $harvestId, 'yeast_harvest:' . $harvestId . ':1', $harvestedAt, $userId, $note);
    return ['harvest_id' => $harvestId, 'lot_id' => (int) $lot['id'], 'lot_number' => $lot['lot_number'], 'generation' => $generation, 'ledger_group_id' => $group];
}

/** Everything the pitch form selects from, for the chosen product. */
function batches_pitch_catalogs(PDO $pdo, ?int $productId): array
{
    $vesselCatalog = batches_vessel_catalog($pdo);
    return [
        'products' => batches_product_options($pdo), 'recipes' => batches_recipe_options($pdo, $productId), 'orders' => batches_production_order_options($pdo, $productId),
        'vesselCatalog' => $vesselCatalog, 'vessels' => batches_vessel_options($vesselCatalog), 'juiceLots' => find_juice_lot_options($pdo), 'yeastLots' => find_yeast_lot_options($pdo),
    ];
}

/** Stages a batch can move to: cider stages after the current one, never terminal (package is slice 7). */
function batches_next_stage_options(PDO $pdo, array $batch): array
{
    $options = [];
    foreach (batches_stage_catalog($pdo) as $code => $stage) {
        if ((int) $stage['display_order'] > (int) $batch['stage_order'] && !$stage['is_terminal']) {
            $options[$code] = $stage['name'];
        }
    }
    return $options;
}

/** The batch's vessels for a "from" select: vessel_id => "FV-1 (120.0 gal)". */
function batches_occupancy_options(array $occupancies): array
{
    $options = [];
    foreach ($occupancies as $occupancy) {
        $options[(int) $occupancy['vessel_id']] = $occupancy['vessel_name'] . ' (' . fmt_qty($occupancy['volume_l'], 'L') . ')';
    }
    return $options;
}

/** Recipe versions of every active cider product: id => "Product · v2 (active)", with product ids. */
function batches_all_recipe_options(PDO $pdo): array
{
    $rows = $pdo->query("SELECT rv.id, rv.product_id, p.name || ' · v' || rv.version_no || ' (' || rv.status || ')' AS label FROM app.recipe_versions rv JOIN app.products p ON p.id = rv.product_id WHERE p.status = 'active' AND rv.status IN ('active', 'draft') ORDER BY p.name, rv.version_no DESC")->fetchAll();
    return ['labels' => array_column($rows, 'label', 'id'), 'products' => array_map('intval', array_column($rows, 'product_id', 'id'))];
}

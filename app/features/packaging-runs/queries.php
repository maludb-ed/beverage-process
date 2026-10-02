<?php
declare(strict_types=1);

require_once __DIR__ . '/../lots/queries.php';
require_once __DIR__ . '/../inventory/ledger.php';

const PACKAGING_RUN_STATUSES = ['draft' => 'Draft', 'posted' => 'Posted', 'all' => 'All, including cancelled'];
const PACKAGING_RUN_SORTS = ['run_on' => 'pr.run_on', 'number' => 'pr.number', 'status' => 'pr.status'];
/** Stages a batch may be packaged from (build spec 07). */
const PACKAGING_BATCH_STAGES = ['carbonate', 'package', 'maturation', 'blend', 'back_sweeten'];

// Lists and single records ----------------------------------------------------------------

function find_packaging_runs(PDO $pdo, string $search = '', string $status = '', string $sort = '-run_on', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(pr.number ILIKE :s OR b.number ILIKE :s OR p.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status === '') {
        $where[] = "pr.status <> 'cancelled'";
    } elseif ($status !== 'all') {
        $where[] = 'pr.status = :status';
        $params['status'] = $status;
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.packaging_runs pr JOIN app.batches b ON b.id = pr.batch_id JOIN app.products p ON p.id = b.product_id
              JOIN app.packaging_configurations pc ON pc.id = pr.packaging_configuration_id';
    return paged_query($pdo,
        'SELECT pr.id, pr.number, pr.status, pr.run_on, pr.volume_in_l, pr.units_out, pr.loss_l,
                CASE WHEN pr.volume_in_l > 0 AND pr.loss_l IS NOT NULL THEN pr.loss_l / pr.volume_in_l * 100 END AS loss_pct,
                pr.batch_id, b.number AS batch_number, p.name AS product_name, pc.name AS configuration_name'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, PACKAGING_RUN_SORTS, '-run_on') . ', pr.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

function find_packaging_run(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pr.*, b.number AS batch_number, b.product_id, b.status AS batch_status, p.name AS product_name,
               pc.name AS configuration_name, pc.package_kind, pc.fill_volume_l, pc.expected_loss_pct, pc.finished_item_id,
               fi.code AS finished_item_code, fi.name AS finished_item_name,
               v.name AS source_vessel_name, loc.name AS output_location_name, loc.tax_state AS output_tax_state,
               fl.lot_id AS finished_lot_id, l.lot_number AS finished_lot_number, fl.tax_class AS finished_tax_class, fl.tax_class_source,
               uc.display_name AS created_by_name, up.display_name AS posted_by_name
        FROM app.packaging_runs pr
        JOIN app.batches b ON b.id = pr.batch_id
        JOIN app.products p ON p.id = b.product_id
        JOIN app.packaging_configurations pc ON pc.id = pr.packaging_configuration_id
        JOIN app.items fi ON fi.id = pc.finished_item_id
        JOIN app.locations loc ON loc.id = pr.output_location_id
        LEFT JOIN app.vessels v ON v.id = pr.source_vessel_id
        LEFT JOIN app.finished_lots fl ON fl.packaging_run_id = pr.id
        LEFT JOIN app.lots l ON l.id = fl.lot_id
        LEFT JOIN app.users uc ON uc.id = pr.created_by
        LEFT JOIN app.users up ON up.id = pr.posted_by
        WHERE pr.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF pr' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_packaging_run_materials(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT m.*, i.code AS item_code, i.name AS item_name, i.base_unit_code, i.item_class, l.lot_number
        FROM app.packaging_run_materials m
        JOIN app.items i ON i.id = m.item_id
        LEFT JOIN app.lots l ON l.id = m.lot_id
        WHERE m.packaging_run_id = :id ORDER BY i.code
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_bom_for_configuration(PDO $pdo, int $configurationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT bl.item_id, bl.qty_per_unit_base, i.code AS item_code, i.name AS item_name, i.base_unit_code, i.item_class,
               i.consumption_mode, COALESCE(i.standard_cost_per_base, 0) AS standard_cost_per_base
        FROM app.packaging_bom_lines bl JOIN app.items i ON i.id = bl.item_id
        WHERE bl.configuration_id = :c ORDER BY i.code
    SQL);
    $statement->execute(['c' => $configurationId]);
    return $statement->fetchAll();
}

/** Open occupancies of a batch (the vessels holding it), largest first. Prefixed: slice 6 owns find_batch_vessels. */
function find_packaging_batch_vessels(PDO $pdo, int $batchId, bool $lock = false): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.id AS occupancy_id, o.vessel_id, o.volume_l, v.name AS vessel_name, v.kind AS vessel_kind
        FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
        WHERE o.occupant_kind = 'batch' AND o.occupant_id = :id AND o.to_at IS NULL
        ORDER BY o.volume_l DESC, o.id
    SQL . ($lock ? ' FOR UPDATE OF o' : ''));
    $statement->execute(['id' => $batchId]);
    return $statement->fetchAll();
}

/** A batch with what packaging needs: product flags, stage entry, fruit share. */
function find_packaging_batch(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT b.id, b.number, b.premises_id, b.product_id, b.status, b.current_stage_code, b.current_volume_l, b.fruit_share_pct, b.started_at,
               p.name AS product_name, p.beverage_type, p.contains_other_fruit, p.contains_flavoring,
               COALESCE(se.entered_at, b.started_at) AS stage_entered_at
        FROM app.batches b
        JOIN app.products p ON p.id = b.product_id
        LEFT JOIN LATERAL (SELECT s.entered_at FROM app.stage_events s WHERE s.batch_id = b.id AND s.left_at IS NULL ORDER BY s.entered_at DESC, s.id DESC LIMIT 1) se ON true
        WHERE b.id = :id
    SQL . ($lock ? ' FOR UPDATE OF b' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_packaging_batch_by_number(PDO $pdo, string $number): ?array
{
    $statement = $pdo->prepare('SELECT id FROM app.batches WHERE lower(number) = lower(:n)');
    $statement->execute(['n' => $number]);
    $id = $statement->fetchColumn();
    return $id === false ? null : find_packaging_batch($pdo, (int) $id);
}

/** Active batches that may be packaged: id => "B-26-001 — Product — Tank 1"; $keepId stays listed when editing. */
function packaging_batch_options(PDO $pdo, ?int $keepId = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT b.id, b.number || ' — ' || p.name || COALESCE(' — ' || (
                   SELECT string_agg(v.name, ', ' ORDER BY v.name) FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
                   WHERE o.occupant_kind = 'batch' AND o.occupant_id = b.id AND o.to_at IS NULL), '') AS label
        FROM app.batches b JOIN app.products p ON p.id = b.product_id
        WHERE (b.status = 'active' AND b.current_stage_code = ANY(string_to_array(:stages, ','))) OR b.id = :keep
        ORDER BY b.number
    SQL);
    $statement->execute(['stages' => implode(',', PACKAGING_BATCH_STAGES), 'keep' => $keepId]);
    return array_column($statement->fetchAll(), 'label', 'id');
}

function packaging_configuration_rows(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare('SELECT id, name, package_kind, fill_volume_l, expected_loss_pct FROM app.packaging_configurations WHERE product_id = :p AND active ORDER BY name');
    $statement->execute(['p' => $productId]);
    return $statement->fetchAll();
}

/** Active configurations of the batch's product, id => name. */
function packaging_configuration_options(PDO $pdo, ?int $productId, ?int $keepId = null): array
{
    if ($productId === null) {
        return [];
    }
    $statement = $pdo->prepare('SELECT id, name FROM app.packaging_configurations WHERE product_id = :p AND (active OR id = :keep) ORDER BY name');
    $statement->execute(['p' => $productId, 'keep' => $keepId]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

function find_packaging_configuration(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.packaging_configurations WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Vessels holding the batch now, vessel id => "Tank 1 — 120.0 gal". */
function packaging_vessel_options(PDO $pdo, ?int $batchId): array
{
    if ($batchId === null) {
        return [];
    }
    $options = [];
    foreach (find_packaging_batch_vessels($pdo, $batchId) as $row) {
        $options[(int) $row['vessel_id']] = $row['vessel_name'] . ' — ' . fmt_qty($row['volume_l'], 'L');
    }
    return $options;
}

/** Packaged-goods locations of a premises, id => name. */
function packaging_output_location_options(PDO $pdo, ?int $premisesId): array
{
    if ($premisesId === null) {
        return [];
    }
    $statement = $pdo->prepare("SELECT id, name FROM app.locations WHERE active AND kind = 'packaged_goods' AND premises_id = :p ORDER BY name");
    $statement->execute(['p' => $premisesId]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

/** Released lots with stock of one item in a premises, lot id => "L-261001-001 — 4,000 ea at Dry store". */
function packaging_material_lot_options(PDO $pdo, int $itemId, ?int $premisesId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT vb.lot_id, vb.lot_number, vb.location_name, vb.qty_available, vb.base_unit_code, vb.expires_on
        FROM app.v_lot_balances vb
        WHERE vb.item_id = :item AND vb.quality_status = 'released' AND vb.qty_available > 0 AND (:p::bigint IS NULL OR vb.premises_id = :p::bigint)
        ORDER BY COALESCE(vb.produced_on, vb.received_on) NULLS LAST, vb.expires_on NULLS LAST, vb.lot_number, vb.location_name
    SQL);
    $statement->execute(['item' => $itemId, 'p' => $premisesId]);
    $options = [];
    foreach ($statement->fetchAll() as $row) {
        $options[(int) $row['lot_id']] = ($options[(int) $row['lot_id']] ?? $row['lot_number']) . ' — ' . fmt_qty($row['qty_available'], $row['base_unit_code']) . ' at ' . $row['location_name'];
    }
    return $options;
}

/** The tax class the rules give; null when no rule exists for the beverage type. */
function packaging_derive_tax_class(PDO $pdo, string $beverageType, ?float $abv, ?float $co2, ?float $fruitSharePct, bool $otherFruit, bool $flavoring): ?string
{
    $statement = $pdo->prepare('SELECT app.derive_tax_class(:t, :abv, :co2, :fruit, :other, :flav)');
    $statement->bindValue('t', $beverageType);
    $statement->bindValue('abv', $abv);
    $statement->bindValue('co2', $co2);
    $statement->bindValue('fruit', $fruitSharePct);
    $statement->bindValue('other', $otherFruit, PDO::PARAM_BOOL);
    $statement->bindValue('flav', $flavoring, PDO::PARAM_BOOL);
    $statement->execute();
    $value = $statement->fetchColumn();
    return $value === false || $value === null ? null : (string) $value;
}

/** Derived readback: volume out, loss, loss % against the configuration's expectation, derived tax class. */
function packaging_readback(PDO $pdo, ?array $batch, ?array $configuration, ?float $volumeInL, ?int $unitsOut, ?float $abv, ?float $co2): array
{
    $out = ['volume_out_l' => null, 'loss_l' => null, 'loss_pct' => null, 'expected_loss_pct' => null, 'tax_class' => null];
    if ($configuration !== null) {
        $out['expected_loss_pct'] = (float) $configuration['expected_loss_pct'];
        if ($unitsOut !== null && $unitsOut > 0) {
            $out['volume_out_l'] = round($unitsOut * (float) $configuration['fill_volume_l'], 3);
            if ($volumeInL !== null && $volumeInL > 0) {
                $out['loss_l'] = round($volumeInL - $out['volume_out_l'], 3);
                $out['loss_pct'] = round($out['loss_l'] / $volumeInL * 100, 2);
            }
        }
    }
    if ($batch !== null) {
        $out['tax_class'] = packaging_derive_tax_class($pdo, (string) $batch['beverage_type'], $abv, $co2,
            $batch['fruit_share_pct'] === null ? null : (float) $batch['fruit_share_pct'], (bool) $batch['contains_other_fruit'], (bool) $batch['contains_flavoring']);
    }
    return $out;
}

/**
 * Material rows for the form: one per BOM line of the configuration. $saved is item_id => ['qty' => display qty, 'lot_id' => ?int]
 * (posted or stored values); without it the quantity is qty_per_unit x units_out. Quantities are in display units.
 */
function packaging_material_rows(PDO $pdo, ?int $configurationId, ?int $unitsOut, ?int $premisesId, array $saved = []): array
{
    if ($configurationId === null) {
        return [];
    }
    $rows = [];
    foreach (find_bom_for_configuration($pdo, $configurationId) as $line) {
        $itemId = (int) $line['item_id'];
        $kind = $line['item_class'] === 'fruit' ? 'fruit' : 'default';
        if (isset($saved[$itemId])) {
            $qty = $saved[$itemId]['qty'];
            $lotId = $saved[$itemId]['lot_id'];
        } else {
            $qty = $unitsOut !== null && $unitsOut > 0 ? round((float) to_display((float) $line['qty_per_unit_base'] * $unitsOut, $line['base_unit_code'], $kind), 4) : '';
            $lotId = null;
        }
        $rows[$itemId] = $line + ['qty' => $qty, 'lot_id' => $lotId, 'unit' => display_unit($line['base_unit_code'], $kind), 'kind' => $kind,
            'lots' => packaging_material_lot_options($pdo, $itemId, $premisesId)];
    }
    return $rows;
}

/**
 * Validate posted material rows against the BOM. Returns [lines for replace_packaging_run_materials, per-item errors].
 * $posted: item_id => ['qty' => string, 'lot_id' => string].
 */
function packaging_validate_materials(PDO $pdo, ?int $configurationId, ?int $premisesId, array $posted): array
{
    $lines = [];
    $errors = [];
    if ($configurationId === null) {
        return [$lines, $errors];
    }
    foreach (find_bom_for_configuration($pdo, $configurationId) as $bom) {
        $itemId = (int) $bom['item_id'];
        $raw = is_array($posted[$itemId] ?? null) ? $posted[$itemId] : [];
        $qtyRaw = trim(str_replace(',', '', (string) ($raw['qty'] ?? '')));
        $lotId = (int) ($raw['lot_id'] ?? 0) ?: null;
        if ($qtyRaw === '') {
            continue;
        }
        $kind = $bom['item_class'] === 'fruit' ? 'fruit' : 'default';
        if (!is_numeric($qtyRaw) || (float) $qtyRaw < 0) {
            $errors[$itemId] = $bom['item_code'] . ': enter a quantity of zero or more.';
            continue;
        }
        $qtyBase = round((float) from_display((float) $qtyRaw, $bom['base_unit_code'], $kind), 4);
        if ($qtyBase <= 0) {
            continue;
        }
        if ($lotId !== null && !isset(packaging_material_lot_options($pdo, $itemId, $premisesId)[$lotId])) {
            $errors[$itemId] = $bom['item_code'] . ': that lot is not released with stock in this premises.';
            continue;
        }
        if ($bom['consumption_mode'] === 'explicit' && $lotId === null) {
            $errors[$itemId] = $bom['item_code'] . ' is issued from a named lot; choose one.';
            continue;
        }
        $lines[$itemId] = ['item_id' => $itemId, 'qty_base' => $qtyBase, 'lot_id' => $lotId, 'mode' => $bom['consumption_mode']];
    }
    return [$lines, $errors];
}

/**
 * Everything the packaging run form needs around the entered values: option lists that depend on the batch,
 * the material rows, and the derived readback. $run uses the form's field names (volume_in_gal in gallons).
 */
function packaging_form_context(PDO $pdo, array $run, array $materialValues = []): array
{
    $batchId = ($run['batch_id'] ?? '') !== '' && $run['batch_id'] !== null ? (int) $run['batch_id'] : null;
    $batch = $batchId !== null ? find_packaging_batch($pdo, $batchId) : null;
    $configId = ($run['packaging_configuration_id'] ?? '') !== '' && $run['packaging_configuration_id'] !== null ? (int) $run['packaging_configuration_id'] : null;
    $configuration = $configId !== null ? find_packaging_configuration($pdo, $configId) : null;
    if ($batch !== null && $configuration !== null && (int) $configuration['product_id'] !== (int) $batch['product_id']) {
        $configuration = null;
    }
    $premisesId = $batch !== null ? (int) $batch['premises_id'] : null;
    $unitsOut = ($run['units_out'] ?? '') !== '' && $run['units_out'] !== null ? (int) $run['units_out'] : null;
    $volumeInL = ($run['volume_in_gal'] ?? '') !== '' && is_numeric($run['volume_in_gal']) ? gal_to_liters((float) $run['volume_in_gal']) : null;
    $abv = ($run['abv_at_packaging'] ?? '') !== '' && is_numeric($run['abv_at_packaging']) ? (float) $run['abv_at_packaging'] : null;
    $co2 = ($run['co2_g_100ml'] ?? '') !== '' && is_numeric($run['co2_g_100ml']) ? (float) $run['co2_g_100ml'] : null;
    return [
        'batch' => $batch,
        'configuration' => $configuration,
        'batches' => packaging_batch_options($pdo, $batchId),
        'configurations' => packaging_configuration_options($pdo, $batch['product_id'] ?? null, $configId),
        'vessels' => packaging_vessel_options($pdo, $batchId),
        'locations' => packaging_output_location_options($pdo, $premisesId),
        'materials' => packaging_material_rows($pdo, $configuration['id'] ?? null, $unitsOut, $premisesId, $materialValues),
        'readback' => packaging_readback($pdo, $batch, $configuration, $volumeInL, $unitsOut, $abv, $co2),
    ];
}

// Writes ---------------------------------------------------------------------------------

function insert_packaging_run(PDO $pdo, int $premisesId, int $batchId, int $configurationId, ?int $sourceVesselId, int $outputLocationId, string $runOn, ?float $volumeInL, ?int $unitsOut, ?float $abv, ?float $co2, ?string $startedAt, ?string $finishedAt, ?string $notes, int $createdBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.packaging_runs (number, premises_id, batch_id, packaging_configuration_id, source_vessel_id, output_location_id, run_on,
                                        volume_in_l, units_out, abv_at_packaging, co2_g_100ml, started_at, finished_at, notes, created_by)
        VALUES (app.next_number('packaging_run'), :premises, :batch, :config, :vessel, :loc, :run_on, :vol, :units, :abv, :co2, :started, :finished, :notes, :by)
        RETURNING id, number, status, batch_id, packaging_configuration_id, run_on, volume_in_l, units_out
    SQL);
    $statement->execute(['premises' => $premisesId, 'batch' => $batchId, 'config' => $configurationId, 'vessel' => $sourceVesselId, 'loc' => $outputLocationId,
        'run_on' => $runOn, 'vol' => $volumeInL === null ? null : round($volumeInL, 3), 'units' => $unitsOut, 'abv' => $abv, 'co2' => $co2,
        'started' => $startedAt, 'finished' => $finishedAt, 'notes' => $notes, 'by' => $createdBy]);
    return $statement->fetch();
}

function update_packaging_run(PDO $pdo, int $id, int $premisesId, int $batchId, int $configurationId, ?int $sourceVesselId, int $outputLocationId, string $runOn, ?float $volumeInL, ?int $unitsOut, ?float $abv, ?float $co2, ?string $startedAt, ?string $finishedAt, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.packaging_runs SET premises_id = :premises, batch_id = :batch, packaging_configuration_id = :config, source_vessel_id = :vessel,
               output_location_id = :loc, run_on = :run_on, volume_in_l = :vol, units_out = :units, abv_at_packaging = :abv, co2_g_100ml = :co2,
               started_at = :started, finished_at = :finished, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, batch_id, packaging_configuration_id, run_on, volume_in_l, units_out
    SQL);
    $statement->execute(['id' => $id, 'premises' => $premisesId, 'batch' => $batchId, 'config' => $configurationId, 'vessel' => $sourceVesselId, 'loc' => $outputLocationId,
        'run_on' => $runOn, 'vol' => $volumeInL === null ? null : round($volumeInL, 3), 'units' => $unitsOut, 'abv' => $abv, 'co2' => $co2,
        'started' => $startedAt, 'finished' => $finishedAt, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft packaging runs can be edited.');
    }
    return $row;
}

/** Draft only. Each line: item_id, qty_base, lot_id (nullable), mode. */
function replace_packaging_run_materials(PDO $pdo, int $id, array $lines): void
{
    $pdo->prepare('DELETE FROM app.packaging_run_materials WHERE packaging_run_id = :id')->execute(['id' => $id]);
    $insert = $pdo->prepare('INSERT INTO app.packaging_run_materials (packaging_run_id, item_id, lot_id, qty_base, mode) VALUES (:r, :item, :lot, :qty, :mode)');
    foreach ($lines as $line) {
        $insert->execute(['r' => $id, 'item' => $line['item_id'], 'lot' => $line['lot_id'], 'qty' => $line['qty_base'], 'mode' => $line['mode']]);
    }
}

function delete_packaging_run(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare("DELETE FROM app.packaging_runs WHERE id = :id AND status = 'draft'");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/** The first FEFO lot/location with enough available stock (or the named lot); null when there is none. */
function packaging_resolve_material_stock(PDO $pdo, int $itemId, ?int $lotId, float $qtyBase, int $premisesId, string $taxState): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT vb.lot_id, vb.location_id, vb.unit_cost_base, vb.lot_number
        FROM app.v_lot_balances vb
        WHERE vb.item_id = :item AND vb.quality_status = 'released' AND vb.premises_id = :p AND vb.tax_state = :tax
          AND vb.qty_available >= :qty AND (:lot::bigint IS NULL OR vb.lot_id = :lot::bigint)
        ORDER BY vb.expires_on NULLS LAST, vb.received_on NULLS LAST, vb.lot_id, vb.qty_available DESC
        LIMIT 1
    SQL);
    $statement->execute(['item' => $itemId, 'p' => $premisesId, 'tax' => $taxState, 'qty' => $qtyBase, 'lot' => $lotId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** True when the batch's LATEST release decision, made after its current stage entry, is "released". */
function packaging_release_gate(PDO $pdo, array $batch): bool
{
    $statement = $pdo->prepare("SELECT to_status FROM app.release_decisions WHERE target_kind = 'batch' AND target_id = :b AND decided_at > :since ORDER BY decided_at DESC, id DESC LIMIT 1");
    $statement->execute(['b' => $batch['id'], 'since' => $batch['stage_entered_at']]);
    return $statement->fetchColumn() === 'released';
}

function packaging_requires_release(PDO $pdo): bool
{
    return (string) $pdo->query("SELECT settings->>'require_batch_release' FROM app.client_settings WHERE id = 1")->fetchColumn() === 'true';
}

/**
 * Post a draft packaging run (build spec 07, steps 1 to 10). The caller owns the transaction and the
 * activity row; a RuntimeException means nothing may be kept. Returns what the activity row needs.
 */
function post_packaging_run(PDO $pdo, int $id, int $actorId): array
{
    $run = find_packaging_run($pdo, $id, true) ?? throw new RuntimeException('That packaging run does not exist.');
    if ($run['status'] !== 'draft') {
        throw new RuntimeException('Packaging run ' . $run['number'] . ' is already ' . $run['status'] . '.');
    }
    $batch = find_packaging_batch($pdo, (int) $run['batch_id'], true) ?? throw new RuntimeException('The batch no longer exists.');
    $unitsOut = (int) ($run['units_out'] ?? 0);
    $volumeIn = (float) ($run['volume_in_l'] ?? 0);
    $fill = (float) $run['fill_volume_l'];
    if ($batch['status'] !== 'active' || !in_array($batch['current_stage_code'], PACKAGING_BATCH_STAGES, true)) {
        throw new RuntimeException('Batch ' . $batch['number'] . ' is not ready for packaging.');
    }
    if ($unitsOut <= 0) { throw new RuntimeException('Enter the units packaged before posting.'); }
    if ($volumeIn <= 0) { throw new RuntimeException('Enter the volume taken from the batch before posting.'); }
    if ($run['abv_at_packaging'] === null || (float) $run['abv_at_packaging'] < 0 || (float) $run['abv_at_packaging'] > 25) { throw new RuntimeException('Enter the ABV at packaging (0 to 25) before posting.'); }
    if ($run['co2_g_100ml'] === null || (float) $run['co2_g_100ml'] < 0 || (float) $run['co2_g_100ml'] > 2) { throw new RuntimeException('Enter the CO2 in g/100 mL (0 to 2) before posting.'); }
    if ($run['source_vessel_id'] === null) { throw new RuntimeException('Choose the source vessel before posting.'); }
    $volumeOut = round($unitsOut * $fill, 3);
    $lossL = round($volumeIn - $volumeOut, 3);
    if ($lossL < 0) {
        throw new RuntimeException('The units packaged hold more than the volume taken from the batch.');
    }
    $occupancy = null;
    foreach (find_packaging_batch_vessels($pdo, (int) $batch['id'], true) as $candidate) {
        if ((int) $candidate['vessel_id'] === (int) $run['source_vessel_id']) { $occupancy = $candidate; }
    }
    if ($occupancy === null) { throw new RuntimeException('Batch ' . $batch['number'] . ' is no longer in ' . $run['source_vessel_name'] . '.'); }
    if ($volumeIn > (float) $occupancy['volume_l'] + 0.0005) {
        throw new RuntimeException($run['source_vessel_name'] . ' holds only ' . fmt_qty($occupancy['volume_l'], 'L', 1) . '.');
    }

    // Release gate (decided 2026-10-01): enforced only once slice 8 sets require_batch_release.
    $releaseMissing = !packaging_release_gate($pdo, $batch);
    if ($releaseMissing && packaging_requires_release($pdo)) {
        throw new RuntimeException('Batch ' . $batch['number'] . ' has not been released for packaging.');
    }

    $premisesId = (int) $run['premises_id'];
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $groupId = new_group_id();
    $bom = find_bom_for_configuration($pdo, (int) $run['packaging_configuration_id']);
    $materials = find_packaging_run_materials($pdo, $id);

    // Cost per finished unit: liquid cost per liter x fill volume + standard cost of the packaging bill of materials.
    $liquid = $pdo->prepare('SELECT COALESCE(liquid_cost_per_l, 0) FROM app.v_batch_costs WHERE batch_id = :b');
    $liquid->execute(['b' => $batch['id']]);
    $liquidPerL = (float) ($liquid->fetchColumn() ?: 0);
    $packagingPerUnit = array_sum(array_map(static fn($l) => (float) $l['qty_per_unit_base'] * (float) $l['standard_cost_per_base'], $bom));
    $unitCost = round($liquidPerL * $fill + $packagingPerUnit, 6);

    // (1) the batch gives up the volume
    $pdo->prepare('UPDATE app.batches SET current_volume_l = GREATEST(0, current_volume_l - :v) WHERE id = :id')->execute(['v' => $volumeIn, 'id' => $batch['id']]);

    // (2) the lot, (3) its finished-goods extension, (4) its attributes
    $shelf = $pdo->prepare('SELECT shelf_life_days FROM app.items WHERE id = :id');
    $shelf->execute(['id' => $run['finished_item_id']]);
    $shelfDays = $shelf->fetchColumn();
    $lot = insert_lot($pdo, '', (int) $run['finished_item_id'], $premisesId, null, null, null, null, 'released', $unitCost, 'packaging_run', $id, $actorId, (string) $run['run_on']);
    $taxClass = packaging_derive_tax_class($pdo, (string) $batch['beverage_type'], (float) $run['abv_at_packaging'], (float) $run['co2_g_100ml'],
        $batch['fruit_share_pct'] === null ? null : (float) $batch['fruit_share_pct'], (bool) $batch['contains_other_fruit'], (bool) $batch['contains_flavoring'])
        ?? throw new RuntimeException('There are no tax class rules for ' . $batch['beverage_type'] . '.');
    $approval = $pdo->prepare("SELECT id FROM app.product_approvals WHERE product_id = :p AND kind = 'label' AND packaging_configuration_id = :c AND status = 'approved' ORDER BY approved_on DESC NULLS LAST, id DESC LIMIT 1");
    $approval->execute(['p' => $batch['product_id'], 'c' => $run['packaging_configuration_id']]);
    $approvalId = $approval->fetchColumn();
    $pdo->prepare(<<<'SQL'
        INSERT INTO app.finished_lots (lot_id, batch_id, packaging_run_id, packaging_configuration_id, packaged_on, units_packaged, unit_volume_l, abv, co2_g_100ml,
                                       fruit_share_pct, tax_class, tax_class_source, label_approval_id, best_before_on, unit_cost)
        VALUES (:lot, :batch, :run, :config, :run_on, :units, :uvol, :abv, :co2, :fruit, :tax, 'derived', :approval,
                CASE WHEN :shelf::int IS NULL THEN NULL ELSE (:run_on::date + :shelf::int) END, :unit_cost)
    SQL)->execute(['unit_cost' => $unitCost, 'lot' => $lot['id'], 'batch' => $batch['id'], 'run' => $id, 'config' => $run['packaging_configuration_id'], 'run_on' => $run['run_on'], 'units' => $unitsOut,
        'uvol' => $fill, 'abv' => $run['abv_at_packaging'], 'co2' => $run['co2_g_100ml'], 'fruit' => $batch['fruit_share_pct'], 'tax' => $taxClass,
        'approval' => $approvalId === false ? null : $approvalId, 'shelf' => $shelfDays === false ? null : $shelfDays]);
    set_lot_attribute($pdo, (int) $lot['id'], 'abv', (float) $run['abv_at_packaging'], null, '%', 'packaging_run', $actorId);
    set_lot_attribute($pdo, (int) $lot['id'], 'co2_g_100ml', (float) $run['co2_g_100ml'], null, 'g/100mL', 'packaging_run', $actorId);
    if ($batch['fruit_share_pct'] !== null) {
        set_lot_attribute($pdo, (int) $lot['id'], 'fruit_share_pct', (float) $batch['fruit_share_pct'], null, '%', 'packaging_run', $actorId);
    }
    set_lot_attribute($pdo, (int) $lot['id'], 'tax_class', null, $taxClass, null, 'packaging_run', $actorId);

    // (5) materials: backflush rows resolve first-expired-first-out; the ledger issues them
    $update = $pdo->prepare('UPDATE app.packaging_run_materials SET lot_id = :lot WHERE id = :id');
    foreach ($materials as $material) {
        $stock = packaging_resolve_material_stock($pdo, (int) $material['item_id'], $material['lot_id'] === null ? null : (int) $material['lot_id'],
            (float) $material['qty_base'], $premisesId, (string) $run['output_tax_state']);
        if ($stock === null) {
            throw new RuntimeException('Not enough released stock of ' . $material['item_code'] . ($material['lot_number'] ? ' lot ' . $material['lot_number'] : '')
                . ' (' . fmt_qty($material['qty_base'], $material['base_unit_code'], 2) . ' needed in one lot at a ' . str_replace('_', '-', (string) $run['output_tax_state']) . ' location).');
        }
        $update->execute(['lot' => $stock['lot_id'], 'id' => $material['id']]);
        insert_inventory_transaction($pdo, $groupId, 'issue', (int) $material['item_id'], (int) $stock['lot_id'], (int) $stock['location_id'], $premisesId,
            -(float) $material['qty_base'], (float) $stock['unit_cost_base'], 'packaging_run', $id, null, 'none', 'packaging_run', $id,
            'pkg:' . $id . ':mat:' . $material['id'], $now, $actorId);
    }

    // (6) the finished units arrive
    insert_inventory_transaction($pdo, $groupId, 'packaging_output', (int) $run['finished_item_id'], (int) $lot['id'], (int) $run['output_location_id'], $premisesId,
        (float) $unitsOut, $unitCost, 'batch', (int) $batch['id'], null, 'bottled', 'packaging_run', $id, 'pkg:' . $id . ':out', $now, $actorId);

    // (7) the loss
    $lossPct = $volumeIn > 0 ? round($lossL / $volumeIn * 100, 2) : 0.0;
    $classification = null;
    if ($lossL > 0) {
        $reason = $pdo->query("SELECT id, ttb_category FROM app.reason_codes WHERE code = 'PKG'")->fetch();
        if ($reason === false) { throw new RuntimeException('Reason code PKG is missing.'); }
        $classification = $lossPct <= (float) $run['expected_loss_pct'] ? 'expected' : 'exceptional';
        $pdo->prepare(<<<'SQL'
            INSERT INTO app.loss_events (target_kind, target_id, premises_id, stage_code, qty_base, unit_code, reason_code_id, ttb_category, reportable, classification, occurred_at, actor_id, ledger_group_id, note)
            VALUES ('batch', :b, :p, 'package', :qty, 'L', :reason, :ttb, :reportable, :class, :at, :by, :grp, :note)
        SQL)->execute(['b' => $batch['id'], 'p' => $premisesId, 'qty' => $lossL, 'reason' => $reason['id'], 'ttb' => $reason['ttb_category'],
            'reportable' => $reason['ttb_category'] !== 'none' ? 't' : 'f', 'class' => $classification, 'at' => $now, 'by' => $actorId, 'grp' => $groupId, 'note' => 'Packaging run ' . $run['number']]);
    }

    // (8) the package stage event
    $pdo->prepare("INSERT INTO app.stage_events (batch_id, stage_code, entered_at, left_at, volume_in_l, volume_out_l, actor_id, note) VALUES (:b, 'package', :at, :at, :vin, :vout, :by, :note)")
        ->execute(['b' => $batch['id'], 'at' => $now, 'vin' => $volumeIn, 'vout' => $volumeOut, 'by' => $actorId, 'note' => 'Packaging run ' . $run['number']]);

    // (9) the vessel and the batch
    $batchPackaged = false;
    $emptied = round((float) $occupancy['volume_l'] - $volumeIn, 3) <= 0;
    if ($emptied) {
        $pdo->prepare('UPDATE app.vessel_occupancies SET to_at = GREATEST(:at::timestamptz, from_at), volume_l = 0 WHERE id = :id AND to_at IS NULL')->execute(['at' => $now, 'id' => $occupancy['occupancy_id']]);
        $pdo->prepare("UPDATE app.vessels SET status = 'empty' WHERE id = :id")->execute(['id' => $occupancy['vessel_id']]);
        $remaining = $pdo->prepare('SELECT current_volume_l FROM app.batches WHERE id = :id');
        $remaining->execute(['id' => $batch['id']]);
        if ((float) $remaining->fetchColumn() <= 0) {
            $pdo->prepare("UPDATE app.stage_events SET left_at = GREATEST(:at::timestamptz, entered_at) WHERE batch_id = :b AND left_at IS NULL")->execute(['at' => $now, 'b' => $batch['id']]);
            $pdo->prepare("UPDATE app.batches SET status = 'packaged', current_stage_code = 'package' WHERE id = :id")->execute(['id' => $batch['id']]);
            $batchPackaged = true;
        }
    } else {
        $pdo->prepare('UPDATE app.vessel_occupancies SET volume_l = :v WHERE id = :id')->execute(['v' => round((float) $occupancy['volume_l'] - $volumeIn, 3), 'id' => $occupancy['occupancy_id']]);
    }

    // (10) the run itself
    $pdo->prepare("UPDATE app.packaging_runs SET status = 'posted', volume_out_l = :vout, loss_l = :loss, posted_by = :by, posted_at = now() WHERE id = :id AND status = 'draft'")
        ->execute(['vout' => $volumeOut, 'loss' => $lossL, 'by' => $actorId, 'id' => $id]);

    return ['finished_lot' => $lot['lot_number'], 'finished_lot_id' => (int) $lot['id'], 'units_out' => $unitsOut, 'loss_l' => $lossL, 'loss_pct' => $lossPct,
        'loss_classification' => $classification, 'tax_class' => $taxClass, 'batch_packaged' => $batchPackaged, 'release_missing' => $releaseMissing, 'unit_cost' => $unitCost];
}

/**
 * Reverse a posted run: ledger reversal, volume back to the batch, vessel occupancy restored, finished lot rejected, run cancelled.
 * Allowed only while every packaged unit is still on hand at the output location and no keg holds the lot. Caller owns the transaction.
 */
function reverse_packaging_run(PDO $pdo, int $id, int $actorId): array
{
    $run = find_packaging_run($pdo, $id, true) ?? throw new RuntimeException('That packaging run does not exist.');
    if ($run['status'] !== 'posted' || $run['finished_lot_id'] === null) {
        throw new RuntimeException('Only a posted packaging run can be reversed.');
    }
    $lotId = (int) $run['finished_lot_id'];
    $units = (int) $run['units_out'];
    $total = $pdo->prepare('SELECT COALESCE(sum(qty_on_hand), 0) FROM app.inventory_balances WHERE lot_id = :l');
    $total->execute(['l' => $lotId]);
    if (lot_on_hand($pdo, $lotId, (int) $run['output_location_id']) < $units || (float) $total->fetchColumn() < $units) {
        throw new RuntimeException('Lot ' . $run['finished_lot_number'] . ' no longer has all ' . $units . ' units at ' . $run['output_location_name'] . '; it cannot be reversed.');
    }
    $kegs = $pdo->prepare('SELECT count(*) FROM app.kegs WHERE current_lot_id = :l');
    $kegs->execute(['l' => $lotId]);
    if ((int) $kegs->fetchColumn() > 0) {
        throw new RuntimeException('Kegs hold lot ' . $run['finished_lot_number'] . '; it cannot be reversed.');
    }
    $batch = find_packaging_batch($pdo, (int) $run['batch_id'], true) ?? throw new RuntimeException('The batch no longer exists.');
    $volumeIn = (float) $run['volume_in_l'];
    $rows = reverse_document_ledger($pdo, 'pkg', 'packaging_run', $id, $actorId);

    $pdo->prepare("UPDATE app.batches SET current_volume_l = current_volume_l + :v, status = CASE WHEN status = 'packaged' THEN 'active' ELSE status END WHERE id = :id")
        ->execute(['v' => $volumeIn, 'id' => $batch['id']]);
    $vesselId = (int) $run['source_vessel_id'];
    $open = $pdo->prepare('SELECT id, occupant_kind, occupant_id FROM app.vessel_occupancies WHERE vessel_id = :v AND to_at IS NULL FOR UPDATE');
    $open->execute(['v' => $vesselId]);
    $current = $open->fetch();
    if ($current === false) {
        $pdo->prepare("INSERT INTO app.vessel_occupancies (vessel_id, occupant_kind, occupant_id, volume_l) VALUES (:v, 'batch', :b, :vol)")->execute(['v' => $vesselId, 'b' => $batch['id'], 'vol' => $volumeIn]);
    } elseif ($current['occupant_kind'] === 'batch' && (int) $current['occupant_id'] === (int) $batch['id']) {
        $pdo->prepare('UPDATE app.vessel_occupancies SET volume_l = volume_l + :vol WHERE id = :id')->execute(['vol' => $volumeIn, 'id' => $current['id']]);
    } else {
        throw new RuntimeException($run['source_vessel_name'] . ' now holds something else; empty it before reversing.');
    }
    $pdo->prepare("UPDATE app.vessels SET status = 'in_use' WHERE id = :id")->execute(['id' => $vesselId]);
    $pdo->prepare("UPDATE app.lots SET quality_status = 'rejected' WHERE id = :id")->execute(['id' => $lotId]);
    // The run's line loss never happened once the run is undone: keep the event as history, out of TTB reporting.
    $pdo->prepare(<<<'SQL'
        UPDATE app.loss_events SET reportable = false, note = COALESCE(note, '') || ' (reversed)'
        WHERE target_kind = 'batch' AND target_id = :b AND stage_code = 'package' AND note = :note AND reportable
    SQL)->execute(['b' => $run['batch_id'], 'note' => 'Packaging run ' . $run['number']]);
    $pdo->prepare("UPDATE app.packaging_runs SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
    return ['finished_lot' => $run['finished_lot_number'], 'volume_in_l' => $volumeIn, 'units_out' => $units, 'ledger_rows' => count($rows)];
}

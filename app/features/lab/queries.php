<?php
declare(strict_types=1);

const READING_SORTS = ['taken_at' => 'r.taken_at', 'measurement_type_code' => 'r.measurement_type_code', 'spec_result' => 'r.spec_result'];
const READING_PAGE_SIZE = 50;
const READING_DAY_OPTIONS = ['7' => 'Last 7 days', '90' => 'Last 90 days', '365' => 'Last year', '0' => 'All time'];

const READING_FROM = <<<'SQL'
    FROM app.readings r
    JOIN app.measurement_types mt ON mt.code = r.measurement_type_code
    LEFT JOIN app.stages st ON st.code = r.stage_code
    LEFT JOIN app.users u ON u.id = r.analyst_id
    LEFT JOIN app.specs sp ON sp.id = r.spec_id
    LEFT JOIN app.batches b ON r.target_kind = 'batch' AND b.id = r.target_id
    LEFT JOIN app.products bp ON bp.id = b.product_id
    LEFT JOIN app.lots l ON r.target_kind = 'lot' AND l.id = r.target_id
    LEFT JOIN app.items li ON li.id = l.item_id
SQL;
const READING_COLUMNS = <<<'SQL'
    r.id, r.target_kind, r.target_id, r.measurement_type_code, r.value, r.taken_at, r.stage_code, r.method, r.is_lab, r.analyst_id,
    r.spec_id, r.spec_result, r.note, mt.name AS measurement_name, mt.unit AS measurement_unit, mt.decimals AS measurement_decimals,
    st.name AS stage_name, u.display_name AS analyst_name, sp.min_value AS spec_min, sp.max_value AS spec_max,
    COALESCE(b.number, l.lot_number) AS target_number, COALESCE(bp.name, li.name) AS target_product,
    (r.taken_at::date = current_date) AS is_today
SQL;

/** $filters: spec_result ('fail'|''), measurement_type_code, days (0 = all time). */
function find_readings(PDO $pdo, string $search = '', array $filters = [], string $sort = '-taken_at', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(b.number ILIKE :s OR l.lot_number ILIKE :s OR mt.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (($filters['spec_result'] ?? '') === 'fail') { $where[] = "r.spec_result = 'fail'"; }
    if (($filters['measurement_type_code'] ?? '') !== '') { $where[] = 'r.measurement_type_code = :mt'; $params['mt'] = $filters['measurement_type_code']; }
    $days = (int) ($filters['days'] ?? 30);
    if ($days > 0) { $where[] = "r.taken_at >= now() - make_interval(days => :days)"; $params['days'] = $days; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo, 'SELECT ' . READING_COLUMNS . READING_FROM . $whereSql . ' ORDER BY ' . order_by($sort, READING_SORTS, '-taken_at') . ', r.id DESC',
        'SELECT count(*) ' . READING_FROM . $whereSql, $params, $page, READING_PAGE_SIZE);
}

function find_reading(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . READING_COLUMNS . READING_FROM . ' WHERE r.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** code => row (name, unit, decimals, min_valid, max_valid), ordered by name. */
function find_measurement_types(PDO $pdo): array
{
    $rows = $pdo->query('SELECT code, name, unit, decimals, min_valid, max_valid FROM app.measurement_types ORDER BY name')->fetchAll();
    return array_column($rows, null, 'code');
}

/** id => label. Batches: active ones as "number — product". Lots: lots with stock as "lot_number — item". */
function find_reading_targets(PDO $pdo, string $kind): array
{
    if ($kind === 'batch') {
        $rows = $pdo->query("SELECT b.id, b.number || ' — ' || p.name AS label FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.status = 'active' ORDER BY b.number")->fetchAll();
    } else {
        $rows = $pdo->query(<<<'SQL'
            SELECT l.id, l.lot_number || ' — ' || i.name AS label FROM app.lots l JOIN app.items i ON i.id = l.item_id
            WHERE COALESCE((SELECT sum(qty_on_hand) FROM app.inventory_balances ib WHERE ib.lot_id = l.id), 0) > 0 ORDER BY l.lot_number
        SQL)->fetchAll();
    }
    return array_column($rows, 'label', 'id');
}

/** A batch for the reading form: product, beverage type, current stage. */
function find_reading_batch(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare("SELECT b.id, b.number, b.status, b.current_stage_code, p.name AS product_name, p.beverage_type FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.id = :id");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_reading_lot(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT l.id, l.lot_number, i.name AS item_name FROM app.lots l JOIN app.items i ON i.id = l.item_id WHERE l.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Stages used by a beverage type: code => name. */
function find_stages_for_beverage(PDO $pdo, string $beverageType): array
{
    $statement = $pdo->prepare('SELECT code, name FROM app.stages WHERE :bt = ANY (beverage_types) ORDER BY display_order');
    $statement->execute(['bt' => $beverageType]);
    return array_column($statement->fetchAll(), 'name', 'code');
}

/** The trigger readings_evaluate_spec fills spec_id and spec_result; this never computes them. */
function insert_reading(PDO $pdo, string $targetKind, int $targetId, string $measurementTypeCode, float $value, string $takenAt, ?string $stageCode, ?string $method, bool $isLab, int $analystId, ?string $note): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.readings (target_kind, target_id, measurement_type_code, value, taken_at, stage_code, method, is_lab, analyst_id, note)
        VALUES (:kind, :id, :mt, :value, :taken, :stage, :method, :lab, :analyst, :note)
        RETURNING id, spec_id, spec_result
    SQL);
    $statement->execute(['kind' => $targetKind, 'id' => $targetId, 'mt' => $measurementTypeCode, 'value' => $value, 'taken' => $takenAt, 'stage' => $stageCode,
        'method' => $method, 'lab' => $isLab ? 't' : 'f', 'analyst' => $analystId, 'note' => $note]);
    return $statement->fetch();
}

/** Only the analyst's own reading, taken today. */
function delete_reading(PDO $pdo, int $id, int $actorId): bool
{
    $statement = $pdo->prepare('DELETE FROM app.readings WHERE id = :id AND analyst_id = :actor AND taken_at::date = current_date');
    $statement->execute(['id' => $id, 'actor' => $actorId]);
    return $statement->rowCount() === 1;
}

/** Plain-English spec outcome for a reading row from find_reading(). */
function reading_spec_sentence(array $reading): string
{
    $decimals = (int) $reading['measurement_decimals'];
    $value = number_format((float) $reading['value'], $decimals, '.', '');
    $unit = in_array($reading['measurement_unit'], ['pH', 'SG'], true) ? '' : ' ' . $reading['measurement_unit'];
    $head = $reading['measurement_name'] . ' ' . $value . $unit;
    if ($reading['spec_result'] === 'none') { return $head . ', no spec to check'; }
    if ($reading['spec_result'] === 'pass') { return $head . ', in spec'; }
    $min = $reading['spec_min'] === null ? null : number_format((float) $reading['spec_min'], $decimals, '.', '');
    $max = $reading['spec_max'] === null ? null : number_format((float) $reading['spec_max'], $decimals, '.', '');
    $below = $min !== null && (float) $reading['value'] < (float) $reading['spec_min'];
    $range = $min !== null && $max !== null ? $min . ' to ' . $max : ($min !== null ? 'minimum ' . $min : 'maximum ' . $max);
    return $head . ', ' . ($below ? 'below' : 'above') . ' spec ' . $range;
}

/** View data for the reading form: option lists resolved from the posted or prefilled input. */
function lab_form_data(PDO $pdo, array $input, array $errors): array
{
    $kind = $input['target_kind'] === 'lot' ? 'lot' : 'batch';
    $stages = [];
    $stageDefault = $input['stage_code'] ?? '';
    if ($kind === 'batch' && !empty($input['target_id']) && ($batch = find_reading_batch($pdo, (int) $input['target_id'])) !== null) {
        $stages = find_stages_for_beverage($pdo, $batch['beverage_type']);
        $stageDefault = $stageDefault !== '' ? $stageDefault : $batch['current_stage_code'];
    }
    return ['input' => $input, 'errors' => $errors, 'types' => find_measurement_types($pdo), 'targets' => find_reading_targets($pdo, $kind), 'stages' => $stages, 'stageDefault' => $stageDefault];
}

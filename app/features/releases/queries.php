<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lots/queries.php'; // insert_release_decision, find_release_decisions, override_reason_options, RELEASE_BASES, LOT_STATUSES

const RELEASE_QUEUE_SORTS = ['number' => 'b.number', 'entered_at' => 'COALESCE(cur.entered_at, b.started_at)'];
const RELEASE_QUEUE_STAGES = ['carbonate', 'back_sweeten', 'blend', 'maturation'];
const RELEASE_QUEUE_PAGE_SIZE = 25;
const RELEASE_QUEUE_MEASUREMENTS = ['abv', 'co2', 'free_so2', 'ph'];
const BATCH_RELEASE_STATUSES = ['released' => 'Released', 'hold' => 'Hold', 'rejected' => 'Rejected'];
const BATCH_RELEASE_BASES = ['readings' => 'Readings', 'sensory' => 'Sensory', 'inspection' => 'Inspection', 'override' => 'Override', 'other' => 'Other'];

/** Where the batch release screen lives. The coordinator flips this to '/batches/{id}/release' once slice 6 owns html/batches. */
function batch_release_url(int $batchId): string
{
    return '/batches/' . $batchId . '/release';
}

const RELEASE_BATCH_FROM = <<<'SQL'
    FROM app.batches b
    JOIN app.products p ON p.id = b.product_id
    JOIN app.stages st ON st.code = b.current_stage_code
    LEFT JOIN LATERAL (SELECT max(se.entered_at) AS entered_at FROM app.stage_events se WHERE se.batch_id = b.id AND se.stage_code = b.current_stage_code) cur ON true
SQL;

function release_batch_where(string $search, array &$params): string
{
    $where = "b.status = 'active' AND b.current_stage_code IN ('carbonate','back_sweeten','blend','maturation')
        AND NOT EXISTS (SELECT 1 FROM app.release_decisions d WHERE d.target_kind = 'batch' AND d.target_id = b.id AND d.to_status = 'released'
                        AND d.decided_at > COALESCE(cur.entered_at, b.started_at))";
    if ($search !== '') {
        $where .= ' AND (b.number ILIKE :s OR p.name ILIKE :s OR p.code ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    return ' WHERE ' . $where;
}

/** Batches awaiting release, each with latest readings (abv, co2, free_so2, ph), failing count since stage entry, and latest sensory verdict. */
function find_release_queue_batches(PDO $pdo, string $search = '', string $sort = 'number', int $page = 1): array
{
    $params = [];
    $whereSql = release_batch_where($search, $params);
    $result = paged_query($pdo, <<<SQL
        SELECT b.id, b.number, b.current_stage_code, st.name AS stage_name, b.current_volume_l, p.name AS product_name,
               COALESCE(cur.entered_at, b.started_at) AS entered_at,
               (SELECT count(*) FROM app.readings r WHERE r.target_kind = 'batch' AND r.target_id = b.id AND r.spec_result = 'fail'
                  AND r.taken_at >= COALESCE(cur.entered_at, b.started_at)) AS failing_count,
               (SELECT sr.verdict FROM app.sensory_records sr WHERE sr.target_kind = 'batch' AND sr.target_id = b.id ORDER BY sr.panel_on DESC, sr.id DESC LIMIT 1) AS sensory_verdict
        SQL . RELEASE_BATCH_FROM . $whereSql . ' ORDER BY ' . order_by($sort, RELEASE_QUEUE_SORTS, 'number') . ', b.id',
        'SELECT count(*) ' . RELEASE_BATCH_FROM . $whereSql, $params, $page, RELEASE_QUEUE_PAGE_SIZE);
    foreach ($result['rows'] as &$row) { $row['latest'] = []; }
    unset($row);
    if ($result['rows'] !== []) {
        $ids = array_map(static fn($r) => (int) $r['id'], $result['rows']);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("SELECT DISTINCT ON (r.target_id, r.measurement_type_code) r.target_id, r.measurement_type_code, r.value, r.spec_result, mt.decimals, mt.unit
            FROM app.readings r JOIN app.measurement_types mt ON mt.code = r.measurement_type_code
            WHERE r.target_kind = 'batch' AND r.target_id IN ($in) AND r.measurement_type_code IN ('abv','co2','free_so2','ph')
            ORDER BY r.target_id, r.measurement_type_code, r.taken_at DESC, r.id DESC");
        $statement->execute($ids);
        $latest = [];
        foreach ($statement->fetchAll() as $reading) { $latest[(int) $reading['target_id']][$reading['measurement_type_code']] = $reading; }
        foreach ($result['rows'] as &$row) { $row['latest'] = $latest[(int) $row['id']] ?? []; }
        unset($row);
    }
    return $result;
}

/** Lots in quarantine or hold that still have stock. */
function find_release_queue_lots(PDO $pdo, string $search = '', int $page = 1): array
{
    $from = ' FROM app.lots l JOIN app.items i ON i.id = l.item_id';
    $where = " WHERE l.quality_status IN ('quarantine','hold') AND COALESCE((SELECT sum(ib.qty_on_hand) FROM app.inventory_balances ib WHERE ib.lot_id = l.id), 0) > 0";
    $params = [];
    if ($search !== '') {
        $where .= ' AND (l.lot_number ILIKE :s OR i.code ILIKE :s OR i.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query($pdo, 'SELECT l.id, l.lot_number, i.code AS item_code, i.name AS item_name, COALESCE(l.received_on, l.produced_on) AS received_on, l.quality_status,
            EXISTS (SELECT 1 FROM app.certificates_of_analysis c WHERE c.lot_id = l.id) AS has_coa' . $from . $where . ' ORDER BY COALESCE(l.received_on, l.produced_on) NULLS LAST, l.id',
        'SELECT count(*)' . $from . $where, $params, $page, RELEASE_QUEUE_PAGE_SIZE);
}

/** Everything the batch release screen shows: batch, readings and sensory since stage entry, failing count, QC targets, last decision. */
function find_batch_release_context(PDO $pdo, int $batchId): array
{
    $statement = $pdo->prepare("SELECT b.id, b.number, b.status, b.current_stage_code, st.name AS stage_name, b.current_volume_l, b.recipe_version_id, p.id AS product_id, p.name AS product_name,
            COALESCE(cur.entered_at, b.started_at) AS entered_at " . RELEASE_BATCH_FROM . ' WHERE b.id = :id');
    $statement->execute(['id' => $batchId]);
    $batch = $statement->fetch();
    if ($batch === false) {
        return [];
    }
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.id, r.measurement_type_code, mt.name AS measurement_name, mt.unit, mt.decimals, r.value, r.taken_at, r.is_lab, r.spec_result, sp.min_value, sp.max_value
        FROM app.readings r JOIN app.measurement_types mt ON mt.code = r.measurement_type_code LEFT JOIN app.specs sp ON sp.id = r.spec_id
        WHERE r.target_kind = 'batch' AND r.target_id = :id AND r.taken_at >= :since ORDER BY r.taken_at DESC, r.id DESC
    SQL);
    $statement->execute(['id' => $batchId, 'since' => $batch['entered_at']]);
    $readings = $statement->fetchAll();
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.id, s.panel_on, COALESCE(u.display_name, s.panelist_name) AS panelist, s.sample_code, s.verdict, s.faults, s.comment
        FROM app.sensory_records s LEFT JOIN app.users u ON u.id = s.panelist_id
        WHERE s.target_kind = 'batch' AND s.target_id = :id AND s.panel_on >= (:since::timestamptz)::date ORDER BY s.panel_on DESC, s.id DESC
    SQL);
    $statement->execute(['id' => $batchId, 'since' => $batch['entered_at']]);
    $statement2 = $pdo->prepare(<<<'SQL'
        SELECT sp.measurement_type_code, mt.name AS measurement_name, mt.unit, mt.decimals, sp.min_value, sp.max_value, sp.target_value
        FROM app.specs sp JOIN app.measurement_types mt ON mt.code = sp.measurement_type_code
        WHERE sp.product_id = :product AND sp.stage_code = :stage AND sp.active ORDER BY mt.name
    SQL);
    $statement2->execute(['product' => $batch['product_id'], 'stage' => $batch['current_stage_code']]);
    $instructions = null;
    if ($batch['recipe_version_id'] !== null) {
        $statement3 = $pdo->prepare('SELECT instructions FROM app.recipe_stages WHERE recipe_version_id = :rv AND stage_code = :stage ORDER BY seq LIMIT 1');
        $statement3->execute(['rv' => $batch['recipe_version_id'], 'stage' => $batch['current_stage_code']]);
        $instructions = $statement3->fetchColumn() ?: null;
    }
    return [
        'batch' => $batch, 'readings' => $readings, 'sensory' => $statement->fetchAll(), 'qc_targets' => $statement2->fetchAll(), 'stage_instructions' => $instructions,
        'failing_count' => count(array_filter($readings, static fn($r) => $r['spec_result'] === 'fail')), 'last_decision' => find_batch_last_release($pdo, $batchId),
    ];
}

/** The most recent release decision for a batch (any outcome), or null. Packaging checks it. */
function find_batch_last_release(PDO $pdo, int $batchId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT d.*, u.display_name AS decided_by_name, rc.name AS reason_name
        FROM app.release_decisions d JOIN app.users u ON u.id = d.decided_by LEFT JOIN app.reason_codes rc ON rc.id = d.reason_code_id
        WHERE d.target_kind = 'batch' AND d.target_id = :id ORDER BY d.decided_at DESC, d.id DESC LIMIT 1
    SQL);
    $statement->execute(['id' => $batchId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Records the decision only; a rejection changes nothing else (dumping is a slice 6 action). */
function insert_batch_release_decision(PDO $pdo, int $batchId, string $fromStatus, string $toStatus, string $basis, bool $isOverride, ?int $reasonCodeId, ?string $note, int $decidedBy): array
{
    return insert_release_decision($pdo, 'batch', $batchId, $fromStatus, $toStatus, $basis, $isOverride, $reasonCodeId, $note, $decidedBy);
}

/** Id of the SPECOVR reason code, the default override reason. */
function releases_default_reason_id(PDO $pdo): ?int
{
    $id = $pdo->query("SELECT id FROM app.reason_codes WHERE code = 'SPECOVR' AND active")->fetchColumn();
    return $id === false ? null : (int) $id;
}

// Read-side entry points for the GET controllers.
function quality_queue_batches(PDO $pdo, string $search = '', string $sort = 'number', int $page = 1): array { return find_release_queue_batches($pdo, $search, $sort, $page); }
function quality_queue_lots(PDO $pdo, string $search = '', int $page = 1): array { return find_release_queue_lots($pdo, $search, $page); }
function quality_batch_context(PDO $pdo, int $batchId): array { return find_batch_release_context($pdo, $batchId); }

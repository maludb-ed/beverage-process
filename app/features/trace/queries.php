<?php
declare(strict_types=1);

// Recall trace: forward from a lot (app.trace_forward), backward from a batch (app.trace_backward).
// find_lot_by_number(PDO, string) is the lots feature's function (same signature), reused here.

require_once __DIR__ . '/../lots/queries.php';
require_once __DIR__ . '/../removals/queries.php';

const TRACE_KIND_COLORS = ['batch' => 'info', 'finished_lot' => 'success', 'removal' => 'warning', 'lot' => 'secondary', 'fruit_lot' => 'dark'];
const TRACE_KIND_LABELS = ['batch' => 'Batch', 'finished_lot' => 'Finished lot', 'removal' => 'Removal', 'lot' => 'Lot', 'fruit_lot' => 'Fruit lot'];
const TRACE_KIND_URLS = ['batch' => '/batches/', 'finished_lot' => '/finished-lots/', 'removal' => '/removals/', 'lot' => '/lots/', 'fruit_lot' => '/lots/'];

function trace_decode_rows(array $rows): array
{
    foreach ($rows as &$row) {
        $row['detail'] = json_decode((string) $row['detail'], true) ?: [];
    }
    return $rows;
}

function find_trace_forward(PDO $pdo, int $lot_id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.level, t.kind, t.id, t.label, t.detail, c.name AS customer_name
        FROM app.trace_forward(:lot_id) t
        LEFT JOIN app.customers c ON t.kind = 'removal' AND c.id = NULLIF(t.detail->>'customer_id', '')::bigint
        ORDER BY t.level, t.kind, t.label
    SQL);
    $statement->execute(['lot_id' => $lot_id]);
    return trace_decode_rows($statement->fetchAll());
}

function find_trace_backward(PDO $pdo, int $batch_id): array
{
    $statement = $pdo->prepare('SELECT t.level, t.kind, t.id, t.label, t.detail, NULL::text AS customer_name FROM app.trace_backward(:batch_id) t ORDER BY t.level, t.kind, t.label');
    $statement->execute(['batch_id' => $batch_id]);
    return trace_decode_rows($statement->fetchAll());
}

function find_batch_by_number(PDO $pdo, string $batch_number): ?array
{
    $statement = $pdo->prepare('SELECT id, number, status, current_stage_code FROM app.batches WHERE lower(number) = lower(:n)');
    $statement->execute(['n' => $batch_number]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** The batch a finished lot was packaged from, or null when the lot is not a finished lot. */
function trace_finished_lot_batch(PDO $pdo, int $lotId): ?array
{
    $statement = $pdo->prepare('SELECT b.id, b.number FROM app.finished_lots fl JOIN app.batches b ON b.id = fl.batch_id WHERE fl.lot_id = :id');
    $statement->execute(['id' => $lotId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Tiles: batches, finished lots, units on hand across those finished lots (v_lot_balances), distinct customers. */
function find_trace_summary(PDO $pdo, array $rows): array
{
    $ids = static fn(string $kind): array => array_values(array_unique(array_map(static fn($r) => (int) $r['id'], array_filter($rows, static fn($r) => $r['kind'] === $kind))));
    $finished = $ids('finished_lot');
    $units = 0.0;
    if ($finished !== []) {
        $statement = $pdo->prepare('SELECT COALESCE(sum(qty_on_hand), 0) FROM app.v_lot_balances WHERE lot_id = ANY(:ids::bigint[])');
        $statement->execute(['ids' => '{' . implode(',', $finished) . '}']);
        $units = (float) $statement->fetchColumn();
    }
    $customers = array_unique(array_filter(array_map(static fn($r) => $r['kind'] === 'removal' ? ($r['detail']['customer_id'] ?? null) : null, $rows)));
    return ['batches' => count($ids('batch')), 'finished_lots' => count($finished), 'units_on_hand' => $units, 'customers' => count($customers)];
}

function suggest_lot_numbers(PDO $pdo, string $q, int $limit = 10): array
{
    $statement = $pdo->prepare('SELECT lot_number FROM app.lots WHERE lot_number ILIKE :q ORDER BY lot_number DESC LIMIT :limit');
    $statement->bindValue('q', '%' . $q . '%');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function suggest_batch_numbers(PDO $pdo, string $q, int $limit = 10): array
{
    $statement = $pdo->prepare('SELECT number FROM app.batches WHERE number ILIKE :q ORDER BY number DESC LIMIT :limit');
    $statement->bindValue('q', '%' . $q . '%');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

/** One line of key values from a trace row's detail, by kind. */
function trace_detail_text(array $row): string
{
    $d = $row['detail'];
    return match ($row['kind']) {
        'batch' => implode(' · ', array_filter([humanize($d['status'] ?? null), isset($d['stage']) ? 'stage ' . humanize($d['stage']) : null])),
        'finished_lot' => implode(' · ', array_filter([isset($d['packaged_on']) ? 'packaged ' . format_date($d['packaged_on']) : null, isset($d['units']) ? $d['units'] . ' units' : null])),
        'removal' => implode(' · ', array_filter([REMOVAL_DESTINATIONS[$d['destination'] ?? ''] ?? humanize($d['destination'] ?? null), $row['customer_name'] ?? null,
            isset($d['removed_at']) ? format_date($d['removed_at']) : null, isset($d['units']) ? $d['units'] . ' units' : null])),
        'lot' => implode(' · ', array_filter([$d['item'] ?? null, !empty($d['supplier_lot']) ? 'supplier lot ' . $d['supplier_lot'] : null, humanize($d['purpose'] ?? null),
            isset($d['qty']) ? format_qty($d['qty'], 3) : null])),
        'fruit_lot' => implode(' · ', array_filter([$d['item'] ?? null, $d['press_run'] ?? null, isset($d['kg']) ? fmt_qty($d['kg'], 'kg', 1, 'fruit') : null])),
        default => '',
    };
}


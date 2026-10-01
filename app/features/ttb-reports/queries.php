<?php
declare(strict_types=1);

// TTB F 5120.17 period reports, derived from the ledger, loss events, removals, stage events,
// vessel occupancies and goods receipts through app.ttb_line_map. Generation is derivation,
// never data entry: every non-zero cell keeps the ids of the rows behind it (source_ids).

require_once __DIR__ . '/../removals/queries.php';

const PERIOD_REPORT_STATUSES = ['draft' => 'Draft', 'final' => 'Final', 'filed' => 'Filed', 'amended' => 'Amended'];
const PERIOD_REPORT_SORTS = ['period_start' => 'r.period_start', 'status' => 'r.status'];
const PERIOD_KINDS = ['month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'];
const PERIOD_KIND_BY_FREQUENCY = ['monthly' => 'month', 'quarterly' => 'quarter', 'annual' => 'year'];
const TTB_SECTIONS = ['A' => 'Part I, Section A: bulk wine', 'B' => 'Part I, Section B: bottled wine', 'IV' => 'Part IV: materials received'];
/** Column order of the report; '' is the bucket for rows whose tax class cannot be determined. */
const TTB_TAX_CLASS_ORDER = ['hard_cider', 'still_wine', 'artificially_carbonated_wine', 'sparkling_wine', ''];
const TTB_UNIT_LABELS = ['gal' => 'wine gal', 'ton' => 'tons', 'lb' => 'lb'];
/** Ledger reference kinds whose reference_id is the document's id, and the document's screen. */
const TTB_REFERENCE_URLS = [
    'goods_receipt' => '/receipts/', 'transfer' => '/transfers/', 'adjustment' => '/adjustments/', 'count' => '/counts/',
    'press_run' => '/press-runs/', 'packaging_run' => '/packaging-runs/', 'removal' => '/removals/', 'return' => '/removals/',
];
const TTB_BATCH_CLASS_SQL = 'COALESCE(b.tax_class_override, b.tax_class_derived, p.intended_tax_class)';

function find_period_reports(PDO $pdo, string $search = '', string $sort = '-period_start', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE r.number ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    $from = ' FROM app.period_reports r JOIN app.premises p ON p.id = r.premises_id';
    return paged_query($pdo,
        'SELECT r.id, r.number, r.premises_id, p.name AS premises_name, r.form_code, r.period_start, r.period_end, r.status, r.generated_at, r.filed_at'
            . $from . $where . ' ORDER BY ' . order_by($sort, PERIOD_REPORT_SORTS, '-period_start') . ', r.id DESC',
        'SELECT count(*)' . $from . $where, $params, $page);
}

function find_period_report(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.*, p.name AS premises_name, p.registry_number, p.cbma_tier, ug.display_name AS generated_by_name, uf.display_name AS filed_by_name
        FROM app.period_reports r
        JOIN app.premises p ON p.id = r.premises_id
        LEFT JOIN app.users ug ON ug.id = r.generated_by
        LEFT JOIN app.users uf ON uf.id = r.filed_by
        WHERE r.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF r' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $row['totals'] = json_decode((string) $row['totals'], true) ?: [];
    return $row;
}

/** Report lines with their map row (display order, source), ordered by section and display order. */
function find_period_report_lines(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.report_id, l.section, l.line_code, l.label, l.tax_class, l.value, l.unit, l.source_ids, l.is_adjustment, m.display_order, m.source
        FROM app.period_report_lines l
        JOIN app.period_reports r ON r.id = l.report_id
        LEFT JOIN app.ttb_line_map m ON m.form_code = r.form_code AND m.section = l.section AND m.line_code = l.line_code
        WHERE l.report_id = :id
        ORDER BY l.section, m.display_order, l.tax_class NULLS LAST
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** The form's map rows, grouped by section in display order. */
function ttb_reports_line_map(PDO $pdo, string $formCode): array
{
    $statement = $pdo->prepare('SELECT * FROM app.ttb_line_map WHERE form_code = :f ORDER BY section, display_order');
    $statement->execute(['f' => $formCode]);
    $map = [];
    foreach ($statement->fetchAll() as $row) {
        $row['match'] = json_decode((string) $row['match'], true) ?: [];
        $map[$row['section']][] = $row;
    }
    return $map;
}

function find_existing_period_report(PDO $pdo, int $premises_id, string $form_code, string $period_start, string $period_end): ?array
{
    $statement = $pdo->prepare('SELECT id, number, status FROM app.period_reports WHERE premises_id = :p AND form_code = :f AND period_start = :s AND period_end = :e');
    $statement->execute(['p' => $premises_id, 'f' => $form_code, 's' => $period_start, 'e' => $period_end]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_period_report(PDO $pdo, int $premises_id, string $form_code, string $period_start, string $period_end, int $generated_by): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.period_reports (number, premises_id, form_code, period_start, period_end, generated_by)
        VALUES (app.next_number('period_report'), :p, :f, :s, :e, :by)
        RETURNING id, number, premises_id, form_code, period_start, period_end, status
    SQL);
    $statement->execute(['p' => $premises_id, 'f' => $form_code, 's' => $period_start, 'e' => $period_end, 'by' => $generated_by]);
    return $statement->fetch();
}

/** Premises that file 5120.17, id => name. */
function ttb_reports_premises_options(PDO $pdo): array
{
    return array_column($pdo->query("SELECT id, name FROM app.premises WHERE active AND report_form = '5120.17' ORDER BY name")->fetchAll(), 'name', 'id');
}

function ttb_reports_premises_frequency(PDO $pdo, int $premisesId): string
{
    $statement = $pdo->prepare('SELECT filing_frequency FROM app.premises WHERE id = :id');
    $statement->execute(['id' => $premisesId]);
    return (string) ($statement->fetchColumn() ?: 'monthly');
}

/** [period_start, period_end] (Y-m-d) for 'YYYY-MM', 'YYYY-Qn' or 'YYYY', or null when malformed. */
function ttb_reports_period_bounds(string $kind, string $period): ?array
{
    if ($kind === 'month' && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m)) {
        $start = new DateTimeImmutable($m[1] . '-' . $m[2] . '-01');
        return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
    }
    if ($kind === 'quarter' && preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
        $start = new DateTimeImmutable(sprintf('%s-%02d-01', $m[1], ((int) $m[2] - 1) * 3 + 1));
        return [$start->format('Y-m-d'), $start->modify('+2 months')->modify('last day of this month')->format('Y-m-d')];
    }
    if ($kind === 'year' && preg_match('/^(\d{4})$/', $period, $m)) {
        return [$m[1] . '-01-01', $m[1] . '-12-31'];
    }
    return null;
}

/** The period kind a prefill value implies ('2026-09' month, '2026-Q3' quarter, '2026' year), or null. */
function ttb_reports_period_kind_of(string $period): ?string
{
    return match (true) {
        (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) => 'month',
        (bool) preg_match('/^\d{4}-Q[1-4]$/', $period) => 'quarter',
        (bool) preg_match('/^\d{4}$/', $period) => 'year',
        default => null,
    };
}

/** The last complete period of a kind before $today, as the form's period value. */
function ttb_reports_previous_period(string $kind, string $today): string
{
    $d = new DateTimeImmutable($today);
    return match ($kind) {
        'quarter' => (function () use ($d): string {
            $q = intdiv((int) $d->format('n') - 1, 3) + 1;
            $y = (int) $d->format('Y');
            return $q === 1 ? ($y - 1) . '-Q4' : $y . '-Q' . ($q - 1);
        })(),
        'year' => (string) ((int) $d->format('Y') - 1),
        default => $d->modify('first day of last month')->format('Y-m'),
    };
}

/** Quarter choices: the last eight quarters through the current one, newest first. */
function ttb_reports_quarter_options(string $today): array
{
    $d = new DateTimeImmutable($today);
    $y = (int) $d->format('Y');
    $q = intdiv((int) $d->format('n') - 1, 3) + 1;
    $options = [];
    for ($i = 0; $i < 8; $i++) {
        $options[$y . '-Q' . $q] = 'Q' . $q . ' ' . $y;
        if (--$q === 0) { $q = 4; $y--; }
    }
    return $options;
}

/** [start, end-exclusive] timestamps (ATOM) of a report period at local midnight. */
function ttb_reports_bounds(string $periodStart, string $periodEnd): array
{
    $tz = new DateTimeZone((string) config('app.timezone'));
    $start = new DateTimeImmutable($periodStart . ' 00:00:00', $tz);
    $end = (new DateTimeImmutable($periodEnd . ' 00:00:00', $tz))->modify('+1 day');
    return [$start->format(DATE_ATOM), $end->format(DATE_ATOM)];
}

/** +1 for lines that add to the balance, -1 for lines that take from it, 0 for balances and Part IV (form structure). */
function ttb_reports_line_direction(string $section, string $lineCode): int
{
    $n = (int) $lineCode;
    return match ($section) {
        'A' => in_array($n, [1, 31], true) ? 0 : ($n < 13 ? 1 : -1),
        'B' => in_array($n, [1, 18], true) ? 0 : ($n < 8 ? 1 : -1),
        default => 0,
    };
}

/** Liters of a quantity in a unit: L and volume units by factor, 'ea' of a finished lot by unit volume, otherwise null. */
function ttb_reports_liters(float $qty, string $unit, ?float $unitVolumeL): ?float
{
    if ($unit === 'ea') {
        return $unitVolumeL === null ? null : $qty * $unitVolumeL;
    }
    $units = unit_table();
    if (($units[$unit]['dimension'] ?? null) === 'volume') {
        return $qty * (float) $units[$unit]['to_base_factor'];
    }
    return null;
}

/** Bulk wine on hand at $at: batch occupancies open at that instant. Returns class => [liters, ids]. */
function ttb_reports_bulk_balance(PDO $pdo, int $premisesId, string $at): array
{
    $statement = $pdo->prepare('SELECT o.id, o.volume_l, ' . TTB_BATCH_CLASS_SQL . ' AS tax_class
        FROM app.vessel_occupancies o JOIN app.batches b ON o.occupant_kind = \'batch\' AND b.id = o.occupant_id JOIN app.products p ON p.id = b.product_id
        WHERE b.premises_id = :p AND o.from_at < :at AND (o.to_at IS NULL OR o.to_at >= :at)');
    $statement->execute(['p' => $premisesId, 'at' => $at]);
    $out = [];
    foreach ($statement->fetchAll() as $row) {
        $class = (string) ($row['tax_class'] ?? '');
        $out[$class] ??= ['liters' => 0.0, 'ids' => []];
        $out[$class]['liters'] += (float) $row['volume_l'];
        $out[$class]['ids'][] = (int) $row['id'];
    }
    return $out;
}

/** Bottled wine on hand at $at: ledger units of finished lots at bonded locations before $at. Returns class => [liters, ids (lot ids)]. */
function ttb_reports_bottled_balance(PDO $pdo, int $premisesId, string $at): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.lot_id, fl.tax_class, fl.unit_volume_l, sum(t.qty_base) AS units
        FROM app.inventory_transactions t JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
        WHERE t.premises_id = :p AND t.tax_state = 'bonded' AND t.occurred_at < :at
        GROUP BY t.lot_id, fl.tax_class, fl.unit_volume_l HAVING sum(t.qty_base) <> 0
    SQL);
    $statement->execute(['p' => $premisesId, 'at' => $at]);
    $out = [];
    foreach ($statement->fetchAll() as $row) {
        $class = (string) $row['tax_class'];
        $out[$class] ??= ['liters' => 0.0, 'ids' => []];
        $out[$class]['liters'] += (float) $row['units'] * (float) $row['unit_volume_l'];
        $out[$class]['ids'][] = (int) $row['lot_id'];
    }
    return $out;
}

/**
 * Ledger rows matching a map row in the period. bulk=true matches bulk wine held as lots (item class intermediate),
 * bulk=false matches finished goods; no bulk key matches any item whose quantity converts to a volume.
 * in_bond selects the tax state; purpose joins the consumption that wrote the row. Returns rows [id, liters, tax_class].
 */
function ttb_reports_ledger_rows(PDO $pdo, int $premisesId, array $match, string $start, string $end, bool $bulkSide = false): array
{
    $where = ['t.premises_id = :p', 't.occurred_at >= :s', 't.occurred_at < :e', 't.ttb_category = :cat'];
    $params = ['p' => $premisesId, 's' => $start, 'e' => $end, 'cat' => (string) ($match['ttb_category'] ?? '')];
    if (array_key_exists('bulk', $match)) {
        $where[] = $match['bulk'] ? "i.item_class = 'intermediate'" : "i.item_class = 'finished_good'";
    }
    if (array_key_exists('in_bond', $match)) {
        $where[] = $match['in_bond'] ? "t.tax_state = 'bonded'" : "t.tax_state = 'tax_paid'";
    }
    $consumptionJoin = '';
    if (isset($match['purpose'])) {
        $consumptionJoin = ' JOIN app.consumptions c ON c.ledger_group_id = t.group_id AND c.lot_id = t.lot_id AND c.purpose = :purpose';
        $params['purpose'] = (string) $match['purpose'];
        $batchExpr = 'c.batch_id';
    } else {
        $batchExpr = "CASE WHEN t.counterparty_kind = 'batch' THEN t.counterparty_id END";
    }
    $statement = $pdo->prepare('SELECT t.id, t.qty_base, i.base_unit_code, fl.unit_volume_l, fl.tax_class AS lot_class, ' . TTB_BATCH_CLASS_SQL . ' AS batch_class
        FROM app.inventory_transactions t JOIN app.items i ON i.id = t.item_id' . $consumptionJoin . '
        LEFT JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
        LEFT JOIN app.batches b ON b.id = ' . $batchExpr . '
        LEFT JOIN app.products p ON p.id = b.product_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY t.occurred_at, t.id');
    $statement->execute($params);
    // Issues are negative in the ledger; the report line counts them as a positive quantity used.
    $natural = ($match['ttb_category'] ?? '') === 'used_in_production' ? -1 : 1;
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $liters = ttb_reports_liters((float) $row['qty_base'], (string) $row['base_unit_code'], $row['unit_volume_l'] === null ? null : (float) $row['unit_volume_l']);
        if ($liters === null) {
            continue;
        }
        // Section A counts wine leaving bulk in the batch's class; Section B counts it in the finished lot's class.
        $class = $bulkSide ? ($row['batch_class'] ?? $row['lot_class']) : ($row['lot_class'] ?? $row['batch_class']);
        $rows[] = ['id' => (int) $row['id'], 'liters' => $natural * $liters, 'tax_class' => (string) ($class ?? '')];
    }
    return $rows;
}

/** Loss events of a category in the period; bulk = batch targets, bottled = lot targets. */
function ttb_reports_loss_rows(PDO $pdo, int $premisesId, array $match, string $start, string $end): array
{
    $statement = $pdo->prepare('SELECT le.id, le.qty_base, le.unit_code, fl.unit_volume_l,
            CASE WHEN le.target_kind = \'batch\' THEN ' . TTB_BATCH_CLASS_SQL . ' ELSE fl.tax_class END AS tax_class
        FROM app.loss_events le
        LEFT JOIN app.batches b ON le.target_kind = \'batch\' AND b.id = le.target_id
        LEFT JOIN app.products p ON p.id = b.product_id
        LEFT JOIN app.finished_lots fl ON le.target_kind = \'lot\' AND fl.lot_id = le.target_id
        WHERE le.reportable AND le.premises_id = :p AND le.occurred_at >= :s AND le.occurred_at < :e AND le.ttb_category = :cat AND le.target_kind = :tk
        ORDER BY le.occurred_at, le.id');
    $statement->execute(['p' => $premisesId, 's' => $start, 'e' => $end, 'cat' => (string) ($match['ttb_category'] ?? ''),
        'tk' => array_key_exists('bulk', $match) && !$match['bulk'] ? 'lot' : 'batch']);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $liters = ttb_reports_liters((float) $row['qty_base'], (string) $row['unit_code'], $row['unit_volume_l'] === null ? null : (float) $row['unit_volume_l']);
        if ($liters !== null) {
            $rows[] = ['id' => (int) $row['id'], 'liters' => $liters, 'tax_class' => (string) ($row['tax_class'] ?? '')];
        }
    }
    return $rows;
}

/**
 * Removals of a destination in the period, per removal and line tax class. Every removal that was posted counts,
 * including one reversed later: its reversal is a separate posted document (a return or a removal) in its own period.
 */
function ttb_reports_removal_rows(PDO $pdo, int $premisesId, array $match, string $start, string $end): array
{
    if (!empty($match['bulk'])) {
        return [];   // every removal line is a finished lot in v1
    }
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.id, l.tax_class, sum(l.volume_l) AS liters
        FROM app.removals r JOIN app.removal_lines l ON l.removal_id = r.id
        WHERE r.premises_id = :p AND r.posted_at IS NOT NULL AND r.destination_kind = :d AND r.removed_at >= :s AND r.removed_at < :e
        GROUP BY r.id, l.tax_class ORDER BY r.id
    SQL);
    $statement->execute(['p' => $premisesId, 'd' => (string) ($match['destination_kind'] ?? ''), 's' => $start, 'e' => $end]);
    return array_map(static fn($r) => ['id' => (int) $r['id'], 'liters' => (float) $r['liters'], 'tax_class' => (string) ($r['tax_class'] ?? '')], $statement->fetchAll());
}

/** Wine produced by fermentation: stage 'pitch' entered in the period, whole volume in. */
function ttb_reports_pitch_rows(PDO $pdo, int $premisesId, string $start, string $end): array
{
    $statement = $pdo->prepare('SELECT s.id, s.volume_in_l, ' . TTB_BATCH_CLASS_SQL . ' AS tax_class
        FROM app.stage_events s JOIN app.batches b ON b.id = s.batch_id JOIN app.products p ON p.id = b.product_id
        WHERE s.stage_code = \'pitch\' AND b.premises_id = :p AND s.entered_at >= :s AND s.entered_at < :e AND s.volume_in_l IS NOT NULL
        ORDER BY s.entered_at, s.id');
    $statement->execute(['p' => $premisesId, 's' => $start, 'e' => $end]);
    return array_map(static fn($r) => ['id' => (int) $r['id'], 'liters' => (float) $r['volume_in_l'], 'tax_class' => (string) ($r['tax_class'] ?? '')], $statement->fetchAll());
}

/** Materials received (posted receipts) by TTB material category: fruit in tons, juice in gallons, sugar in pounds. Returns [unit, rows [id, value]]. */
function ttb_reports_material_rows(PDO $pdo, int $premisesId, array $match, string $start, string $end): array
{
    $category = (string) ($match['ttb_material_category'] ?? '');
    $statement = $pdo->prepare(<<<'SQL'
        SELECT gl.id, gl.qty_base, i.base_unit_code
        FROM app.goods_receipt_lines gl JOIN app.goods_receipts g ON g.id = gl.goods_receipt_id JOIN app.items i ON i.id = gl.item_id
        WHERE g.status = 'posted' AND g.premises_id = :p AND g.received_at >= :s AND g.received_at < :e AND i.ttb_material_category = :cat
        ORDER BY g.received_at, gl.id
    SQL);
    $statement->execute(['p' => $premisesId, 's' => $start, 'e' => $end, 'cat' => $category]);
    [$unit, $base, $divisor] = match ($category) {
        'fruit' => ['ton', 'kg', KG_PER_TON],
        'sugar' => ['lb', 'kg', KG_PER_POUND],
        default => ['gal', 'L', LITERS_PER_GALLON],
    };
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        if ($row['base_unit_code'] === $base) {
            $rows[] = ['id' => (int) $row['id'], 'value' => (float) $row['qty_base'] / $divisor];
        }
    }
    return [$unit, $rows];
}

/** Rate and credit for a report tax class: the cider rules when they define the class, else the wine rules. */
function ttb_reports_tax_rate(PDO $pdo, string $taxClass, string $cbmaTier, string $asOf): array
{
    foreach (['cider', 'wine'] as $beverage) {
        $rate = removals_tax_rate($pdo, $beverage, $taxClass, $cbmaTier, $asOf);
        if ($rate['rate'] !== null) {
            return $rate;
        }
    }
    return ['rate' => null, 'credit' => 0.0];
}

/**
 * Generate (or regenerate) a draft report: balances, flows per map row and tax class, closing balances with a
 * physical reconciliation, tax totals; replaces the lines and keeps the previous ones in totals->previous_lines.
 * Caller owns the transaction and the activity log.
 */
function generate_period_report(PDO $pdo, int $id, int $actor_id): array
{
    $report = find_period_report($pdo, $id, true) ?? throw new RuntimeException('That report does not exist.');
    if ($report['status'] !== 'draft') {
        throw new RuntimeException($report['number'] . ' is ' . $report['status'] . '; only a draft can be regenerated.');
    }
    $premisesId = (int) $report['premises_id'];
    [$start, $end] = ttb_reports_bounds((string) $report['period_start'], (string) $report['period_end']);
    $map = ttb_reports_line_map($pdo, (string) $report['form_code']);
    $cells = [];      // section|code => class => ['liters'|'value', 'ids', 'unit']
    $add = static function (string $section, string $code, string $class, float $amount, array $ids, string $unit = 'gal') use (&$cells): void {
        $key = $section . '|' . $code;
        $cells[$key][$class] ??= ['amount' => 0.0, 'ids' => [], 'unit' => $unit];
        $cells[$key][$class]['amount'] += $amount;
        $cells[$key][$class]['ids'] = array_values(array_unique([...$cells[$key][$class]['ids'], ...$ids]));
    };

    $balances = [
        'A' => ['start' => ttb_reports_bulk_balance($pdo, $premisesId, $start), 'end' => ttb_reports_bulk_balance($pdo, $premisesId, $end)],
        'B' => ['start' => ttb_reports_bottled_balance($pdo, $premisesId, $start), 'end' => ttb_reports_bottled_balance($pdo, $premisesId, $end)],
    ];
    foreach ($map as $section => $rows) {
        foreach ($rows as $row) {
            $code = (string) $row['line_code'];
            $match = $row['match'];
            $flow = match ($row['source']) {
                'ledger' => $section === 'A' && $code === '2' ? ttb_reports_pitch_rows($pdo, $premisesId, $start, $end) : ttb_reports_ledger_rows($pdo, $premisesId, $match, $start, $end, $section === 'A'),
                'loss' => ttb_reports_loss_rows($pdo, $premisesId, $match, $start, $end),
                'removal' => ttb_reports_removal_rows($pdo, $premisesId, $match, $start, $end),
                default => null,
            };
            if ($flow !== null) {
                foreach ($flow as $f) {
                    $add($section, $code, $f['tax_class'], $f['liters'] * (int) $row['sign'], [$f['id']]);
                }
            } elseif ($row['source'] === 'materials') {
                [$unit, $materials] = ttb_reports_material_rows($pdo, $premisesId, $match, $start, $end);
                foreach ($materials as $m) {
                    $add($section, $code, '', $m['value'], [$m['id']], $unit);
                }
            }
        }
    }

    // Classes present anywhere in Part I get balance rows in both sections.
    $classes = [];
    foreach (['A', 'B'] as $section) {
        foreach ($balances[$section] as $b) { $classes += array_fill_keys(array_keys($b), true); }
    }
    foreach ($cells as $key => $byClass) {
        if (!str_starts_with($key, 'IV|')) { $classes += array_fill_keys(array_keys($byClass), true); }
    }
    $classes = array_values(array_filter(TTB_TAX_CLASS_ORDER, static fn($c) => isset($classes[$c])));

    $reconciliation = [];
    $hasDifference = false;
    foreach (['A' => ['1', '31'], 'B' => ['1', '18']] as $section => [$openCode, $closeCode]) {
        foreach ($classes as $class) {
            $open = $balances[$section]['start'][$class] ?? ['liters' => 0.0, 'ids' => []];
            $physical = $balances[$section]['end'][$class] ?? ['liters' => 0.0, 'ids' => []];
            $add($section, $openCode, $class, $open['liters'], $open['ids']);
            $computed = $open['liters'];
            foreach ($map[$section] ?? [] as $row) {
                $direction = ttb_reports_line_direction($section, (string) $row['line_code']);
                $computed += $direction * ($cells[$section . '|' . $row['line_code']][$class]['amount'] ?? 0.0);
            }
            $add($section, $closeCode, $class, $computed, $physical['ids']);
            $diff = round(($physical['liters'] - $computed) / LITERS_PER_GALLON, 4);
            $hasDifference = $hasDifference || abs($diff) >= 0.005;
            $reconciliation[$section][$class === '' ? 'unclassified' : $class] = [
                'computed_gal' => round($computed / LITERS_PER_GALLON, 4) + 0.0, 'physical_gal' => round($physical['liters'] / LITERS_PER_GALLON, 4) + 0.0, 'difference_gal' => $diff + 0.0,
            ];
        }
    }

    // Tax totals: tax-determined gallons = B8 + B8t + B12.
    $asOf = (string) $report['period_end'];
    $taxTotals = [];
    $totalTax = 0.0;
    $totalGallons = 0.0;
    foreach ($classes as $class) {
        $liters = 0.0;
        foreach (['8', '8t', '12'] as $code) {
            $liters += $cells['B|' . $code][$class]['amount'] ?? 0.0;
        }
        if ($liters == 0.0 || $class === '') {
            continue;
        }
        $gallons = round($liters / LITERS_PER_GALLON, 4);
        $rate = ttb_reports_tax_rate($pdo, $class, (string) $report['cbma_tier'], $asOf);
        $tax = $rate['rate'] === null ? null : round($gallons * max(0.0, $rate['rate'] - $rate['credit']), 2);
        $taxTotals[$class] = ['gallons' => $gallons, 'rate' => $rate['rate'], 'credit' => $rate['credit'], 'tax' => $tax];
        $totalTax += (float) $tax;
        $totalGallons += $gallons;
    }

    // Replace the lines, keeping the previous set for restore_prior.
    $previous = array_map(static fn($l) => ['section' => $l['section'], 'line_code' => $l['line_code'], 'label' => $l['label'], 'tax_class' => $l['tax_class'],
        'value' => (float) $l['value'], 'unit' => $l['unit'], 'source_ids' => json_decode((string) $l['source_ids'], true)], find_period_report_lines($pdo, $id));
    $pdo->prepare('DELETE FROM app.period_report_lines WHERE report_id = :id')->execute(['id' => $id]);
    $insert = $pdo->prepare('INSERT INTO app.period_report_lines (report_id, section, line_code, label, tax_class, value, unit, source_ids) VALUES (:r, :s, :c, :l, :t, :v, :u, :ids)');
    $count = 0;
    foreach ($map as $section => $rows) {
        foreach ($rows as $row) {
            foreach ($cells[$section . '|' . $row['line_code']] ?? [] as $class => $cell) {
                $value = $cell['unit'] === 'gal' && $section !== 'IV' ? $cell['amount'] / LITERS_PER_GALLON : $cell['amount'];
                $isBalance = $row['source'] === 'balance';
                if (!$isBalance && abs($value) < 0.00005 && $cell['ids'] === []) {
                    continue;
                }
                $insert->execute(['r' => $id, 's' => $section, 'c' => $row['line_code'], 'l' => $row['label'], 't' => $class === '' ? null : $class,
                    'v' => round($value, 4), 'u' => $cell['unit'], 'ids' => json_encode($cell['ids'], JSON_THROW_ON_ERROR)]);
                $count++;
            }
        }
    }
    $totals = [
        'tax_class' => $taxTotals, 'total_gallons' => round($totalGallons, 4), 'total_tax' => round($totalTax, 2), 'cbma_tier' => $report['cbma_tier'],
        'classes' => $classes, 'reconciliation' => $reconciliation, 'has_difference' => $hasDifference, 'line_count' => $count,
    ];
    $stored = $totals + ($previous !== [] ? ['previous_lines' => $previous, 'previous_generated_at' => $report['generated_at']] : []);
    $statement = $pdo->prepare('UPDATE app.period_reports SET totals = :t, generated_at = now(), generated_by = :by WHERE id = :id RETURNING id, number, status, generated_at');
    $statement->execute(['t' => json_encode($stored, JSON_THROW_ON_ERROR), 'by' => $actor_id, 'id' => $id]);
    return ['report' => $statement->fetch(), 'totals' => $totals, 'regenerated' => $previous !== []];
}

function finalize_period_report(PDO $pdo, int $id, int $actor_id): array
{
    $statement = $pdo->prepare("UPDATE app.period_reports SET status = 'final', finalized_at = now() WHERE id = :id AND status = 'draft' RETURNING id, number, status, finalized_at");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft report can be finalized.');
    }
    return $row;
}

function mark_period_report_filed(PDO $pdo, int $id, string $filed_at, int $actor_id): array
{
    $statement = $pdo->prepare("UPDATE app.period_reports SET status = 'filed', filed_at = :at, filed_by = :by WHERE id = :id AND status = 'final' RETURNING id, number, status, filed_at, filed_by");
    $statement->execute(['id' => $id, 'at' => $filed_at, 'by' => $actor_id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a final report can be marked filed.');
    }
    return $row;
}

/** One report line with its report and map row, or null. */
function find_period_report_line(PDO $pdo, int $lineId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.*, r.premises_id, r.period_start, r.period_end, r.number AS report_number, r.form_code, m.source, m.match
        FROM app.period_report_lines l
        JOIN app.period_reports r ON r.id = l.report_id
        LEFT JOIN app.ttb_line_map m ON m.form_code = r.form_code AND m.section = l.section AND m.line_code = l.line_code
        WHERE l.id = :id
    SQL);
    $statement->execute(['id' => $lineId]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $row['match'] = json_decode((string) $row['match'], true) ?: [];
    $row['source_ids'] = array_map('intval', json_decode((string) $row['source_ids'], true) ?: []);
    return $row;
}

/**
 * The rows behind one report cell: ['kind', 'rows' => [date, document, document_url, subject, subject_url, qty, unit]].
 * Kinds: ledger, stage (fermentation), loss, removal, materials, bulk_balance (occupancies), bottled_balance (finished lots).
 */
function find_period_report_line_sources(PDO $pdo, int $line_id): array
{
    $line = find_period_report_line($pdo, $line_id);
    if ($line === null) {
        return ['kind' => 'none', 'rows' => []];
    }
    $ids = '{' . implode(',', $line['source_ids']) . '}';
    $class = $line['tax_class'];
    $classSql = static fn(string $expr): string => $class === null ? "($expr) IS NULL" : "($expr) = :class";
    $params = ['ids' => $ids] + ($class === null ? [] : ['class' => $class]);
    $section = (string) $line['section'];
    $code = (string) $line['line_code'];
    $rows = [];
    if ($line['source'] === 'balance') {
        [$start, $end] = ttb_reports_bounds((string) $line['period_start'], (string) $line['period_end']);
        $at = ($line['match']['when'] ?? 'start') === 'end' ? $end : $start;
        if ($section === 'A') {
            $kind = 'bulk_balance';
            $statement = $pdo->prepare('SELECT o.id, o.from_at, o.volume_l, v.name AS vessel_name, b.id AS batch_id, b.number AS batch_number
                FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id JOIN app.batches b ON b.id = o.occupant_id JOIN app.products p ON p.id = b.product_id
                WHERE o.id = ANY(:ids::bigint[]) AND ' . $classSql(TTB_BATCH_CLASS_SQL) . ' ORDER BY v.name');
            $statement->execute($params);
            foreach ($statement->fetchAll() as $r) {
                $rows[] = ['date' => $r['from_at'], 'document' => $r['vessel_name'], 'document_url' => null, 'subject' => $r['batch_number'], 'subject_url' => '/batches/' . (int) $r['batch_id'],
                    'qty' => (float) $r['volume_l'] / LITERS_PER_GALLON, 'unit' => 'gal'];
            }
        } else {
            $kind = 'bottled_balance';
            $statement = $pdo->prepare('SELECT fl.lot_id, l.lot_number, fl.packaged_on, b.id AS batch_id, b.number AS batch_number, fl.unit_volume_l,
                    (SELECT sum(t.qty_base) FROM app.inventory_transactions t WHERE t.lot_id = fl.lot_id AND t.tax_state = \'bonded\' AND t.premises_id = :p AND t.occurred_at < :at) AS units
                FROM app.finished_lots fl JOIN app.lots l ON l.id = fl.lot_id JOIN app.batches b ON b.id = fl.batch_id
                WHERE fl.lot_id = ANY(:ids::bigint[]) AND ' . $classSql('fl.tax_class') . ' ORDER BY l.lot_number');
            $statement->execute($params + ['p' => (int) $line['premises_id'], 'at' => $at]);
            foreach ($statement->fetchAll() as $r) {
                $rows[] = ['date' => $r['packaged_on'], 'document' => $r['lot_number'], 'document_url' => '/finished-lots/' . (int) $r['lot_id'], 'subject' => $r['batch_number'], 'subject_url' => '/batches/' . (int) $r['batch_id'],
                    'qty' => (float) $r['units'] * (float) $r['unit_volume_l'] / LITERS_PER_GALLON, 'unit' => 'gal', 'note' => format_qty($r['units'], 0) . ((float) $r['units'] === 1.0 ? ' unit' : ' units')];
            }
        }
        return ['kind' => $kind, 'rows' => $rows, 'line' => $line];
    }
    if ($line['source'] === 'ledger' && $section === 'A' && $code === '2') {
        $statement = $pdo->prepare('SELECT s.id, s.entered_at, s.volume_in_l, b.id AS batch_id, b.number AS batch_number FROM app.stage_events s JOIN app.batches b ON b.id = s.batch_id WHERE s.id = ANY(:ids::bigint[]) ORDER BY s.entered_at');
        $statement->execute(['ids' => $ids]);
        foreach ($statement->fetchAll() as $r) {
            $rows[] = ['date' => $r['entered_at'], 'document' => 'Pitch', 'document_url' => '/batches/' . (int) $r['batch_id'], 'subject' => $r['batch_number'], 'subject_url' => '/batches/' . (int) $r['batch_id'],
                'qty' => (float) $r['volume_in_l'] / LITERS_PER_GALLON, 'unit' => 'gal'];
        }
        return ['kind' => 'stage', 'rows' => $rows, 'line' => $line];
    }
    if ($line['source'] === 'ledger') {
        $natural = ($line['match']['ttb_category'] ?? '') === 'used_in_production' ? -1 : 1;
        $statement = $pdo->prepare(<<<'SQL'
            SELECT t.id, t.occurred_at, t.txn_type, t.qty_base, t.reference_kind, t.reference_id, i.base_unit_code, fl.unit_volume_l, l.id AS lot_id, l.lot_number, (fl.lot_id IS NOT NULL) AS is_finished,
                   COALESCE(gr.number, pr.number, pk.number, rm.number, tr.number, ad.number, ct.number) AS document_number
            FROM app.inventory_transactions t
            JOIN app.items i ON i.id = t.item_id JOIN app.lots l ON l.id = t.lot_id
            LEFT JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
            LEFT JOIN app.goods_receipts gr ON t.reference_kind = 'goods_receipt' AND gr.id = t.reference_id
            LEFT JOIN app.press_runs pr ON t.reference_kind = 'press_run' AND pr.id = t.reference_id
            LEFT JOIN app.packaging_runs pk ON t.reference_kind = 'packaging_run' AND pk.id = t.reference_id
            LEFT JOIN app.removals rm ON t.reference_kind IN ('removal', 'return') AND rm.id = t.reference_id
            LEFT JOIN app.inventory_transfers tr ON t.reference_kind = 'transfer' AND tr.id = t.reference_id
            LEFT JOIN app.inventory_adjustments ad ON t.reference_kind = 'adjustment' AND ad.id = t.reference_id
            LEFT JOIN app.inventory_counts ct ON t.reference_kind = 'count' AND ct.id = t.reference_id
            WHERE t.id = ANY(:ids::bigint[]) ORDER BY t.occurred_at, t.id
        SQL);
        $statement->execute(['ids' => $ids]);
        foreach ($statement->fetchAll() as $r) {
            $liters = ttb_reports_liters((float) $r['qty_base'], (string) $r['base_unit_code'], $r['unit_volume_l'] === null ? null : (float) $r['unit_volume_l']);
            $rows[] = ['date' => $r['occurred_at'], 'document' => $r['document_number'] ?? (humanize($r['reference_kind']) . ' #' . (int) $r['reference_id']),
                'document_url' => isset(TTB_REFERENCE_URLS[$r['reference_kind']]) ? TTB_REFERENCE_URLS[$r['reference_kind']] . (int) $r['reference_id'] : null,
                'subject' => $r['lot_number'], 'subject_url' => ($r['is_finished'] ? '/finished-lots/' : '/lots/') . (int) $r['lot_id'],
                'qty' => $natural * (float) $liters / LITERS_PER_GALLON, 'unit' => 'gal', 'note' => humanize($r['txn_type'])];
        }
        return ['kind' => 'ledger', 'rows' => $rows, 'line' => $line];
    }
    if ($line['source'] === 'loss') {
        $statement = $pdo->prepare(<<<'SQL'
            SELECT le.id, le.occurred_at, le.target_kind, le.target_id, le.qty_base, le.unit_code, rc.code AS reason_code, rc.name AS reason_name,
                   b.number AS batch_number, l.lot_number, fl.unit_volume_l
            FROM app.loss_events le
            LEFT JOIN app.reason_codes rc ON rc.id = le.reason_code_id
            LEFT JOIN app.batches b ON le.target_kind = 'batch' AND b.id = le.target_id
            LEFT JOIN app.lots l ON le.target_kind = 'lot' AND l.id = le.target_id
            LEFT JOIN app.finished_lots fl ON le.target_kind = 'lot' AND fl.lot_id = le.target_id
            WHERE le.id = ANY(:ids::bigint[]) ORDER BY le.occurred_at, le.id
        SQL);
        $statement->execute(['ids' => $ids]);
        foreach ($statement->fetchAll() as $r) {
            $liters = ttb_reports_liters((float) $r['qty_base'], (string) $r['unit_code'], $r['unit_volume_l'] === null ? null : (float) $r['unit_volume_l']);
            $isBatch = $r['target_kind'] === 'batch';
            $rows[] = ['date' => $r['occurred_at'], 'document' => trim(($r['reason_code'] ?? '') . ' ' . ($r['reason_name'] ?? '')) ?: 'Loss #' . (int) $r['id'], 'document_url' => null,
                'subject' => $isBatch ? $r['batch_number'] : $r['lot_number'], 'subject_url' => ($isBatch ? '/batches/' : '/finished-lots/') . (int) $r['target_id'],
                'qty' => (float) $liters / LITERS_PER_GALLON, 'unit' => 'gal'];
        }
        return ['kind' => 'loss', 'rows' => $rows, 'line' => $line];
    }
    if ($line['source'] === 'removal') {
        $statement = $pdo->prepare('SELECT r.id, r.number, r.removed_at, r.status, c.name AS customer_name, sum(l.volume_l) AS liters, string_agg(DISTINCT lot.lot_number, \', \') AS lots
            FROM app.removals r JOIN app.removal_lines l ON l.removal_id = r.id JOIN app.lots lot ON lot.id = l.lot_id LEFT JOIN app.customers c ON c.id = r.customer_id
            WHERE r.id = ANY(:ids::bigint[]) AND ' . $classSql('l.tax_class') . ' GROUP BY r.id, c.name ORDER BY r.removed_at, r.id');
        $statement->execute($params);
        foreach ($statement->fetchAll() as $r) {
            $rows[] = ['date' => $r['removed_at'], 'document' => $r['number'], 'document_url' => '/removals/' . (int) $r['id'], 'subject' => $r['lots'], 'subject_url' => null,
                'qty' => (float) $r['liters'] / LITERS_PER_GALLON, 'unit' => 'gal', 'note' => trim(($r['customer_name'] ?? '') . ($r['status'] === 'reversed' ? ' (reversed later)' : ''))];
        }
        return ['kind' => 'removal', 'rows' => $rows, 'line' => $line];
    }
    if ($line['source'] === 'materials') {
        [$unit, $base, $divisor] = match ($line['match']['ttb_material_category'] ?? '') {
            'fruit' => ['ton', 'kg', KG_PER_TON], 'sugar' => ['lb', 'kg', KG_PER_POUND], default => ['gal', 'L', LITERS_PER_GALLON],
        };
        $statement = $pdo->prepare('SELECT gl.id, gl.qty_base, g.id AS receipt_id, g.number, g.received_at, i.code AS item_code, l.id AS lot_id, l.lot_number
            FROM app.goods_receipt_lines gl JOIN app.goods_receipts g ON g.id = gl.goods_receipt_id JOIN app.items i ON i.id = gl.item_id LEFT JOIN app.lots l ON l.id = gl.lot_id
            WHERE gl.id = ANY(:ids::bigint[]) ORDER BY g.received_at, gl.id');
        $statement->execute(['ids' => $ids]);
        foreach ($statement->fetchAll() as $r) {
            $rows[] = ['date' => $r['received_at'], 'document' => $r['number'], 'document_url' => '/receipts/' . (int) $r['receipt_id'], 'subject' => $r['lot_number'] ?? $r['item_code'],
                'subject_url' => $r['lot_id'] ? '/lots/' . (int) $r['lot_id'] : null, 'qty' => (float) $r['qty_base'] / $divisor, 'unit' => $unit, 'note' => $r['item_code']];
        }
        return ['kind' => 'materials', 'rows' => $rows, 'line' => $line];
    }
    return ['kind' => 'none', 'rows' => [], 'line' => $line];
}

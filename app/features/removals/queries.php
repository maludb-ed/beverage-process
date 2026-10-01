<?php
declare(strict_types=1);

// Removals and returns of finished lots: the document, its lines (one row per keg
// for keg lots), tax determination in wine gallons, posting to the ledger (the only
// path across the bonded / tax-paid line), keg movements, and reversal.

require_once __DIR__ . '/../inventory/ledger.php';
require_once __DIR__ . '/../customers/queries.php';

const REMOVAL_DESTINATIONS = [
    'tax_paid_sale' => 'Tax-paid sale', 'taproom_transfer' => 'Taproom transfer', 'in_bond_transfer' => 'In-bond transfer', 'export' => 'Export',
    'sample_testing' => 'Samples and testing', 'destroyed' => 'Destroyed', 'breakage' => 'Breakage', 'family_use' => 'Family use',
    'return_from_customer' => 'Return from customer',
];
const REMOVAL_OUT_DESTINATIONS = [
    'tax_paid_sale' => 'Tax-paid sale', 'taproom_transfer' => 'Taproom transfer', 'in_bond_transfer' => 'In-bond transfer', 'export' => 'Export',
    'sample_testing' => 'Samples and testing', 'destroyed' => 'Destroyed', 'breakage' => 'Breakage', 'family_use' => 'Family use',
];
const REMOVAL_IN_DESTINATIONS = ['return_from_customer' => 'Return from customer'];
const REMOVAL_STATUSES = ['draft' => 'Draft', 'posted' => 'Posted', 'reversed' => 'Reversed', 'cancelled' => 'Cancelled'];
const REMOVAL_SORTS = ['removed_at' => 'r.removed_at', 'number' => 'r.number', 'status' => 'r.status', 'destination_kind' => 'r.destination_kind'];
/** Ledger ttb_category of the out row, by destination. */
const REMOVAL_TTB_CATEGORIES = [
    'tax_paid_sale' => 'removed_tax_paid', 'taproom_transfer' => 'removed_tax_paid', 'in_bond_transfer' => 'removed_in_bond', 'export' => 'export',
    'sample_testing' => 'testing', 'destroyed' => 'destroyed', 'breakage' => 'breakage', 'family_use' => 'removed_tax_paid',
    'return_from_customer' => 'returned',
];
const REMOVAL_CUSTOMER_REQUIRED = ['tax_paid_sale', 'in_bond_transfer', 'export', 'return_from_customer'];
const REMOVAL_DISPOSAL_DESTINATIONS = ['destroyed', 'breakage', 'sample_testing', 'family_use'];
const REMOVAL_TAX_DETERMINED = ['tax_paid_sale', 'taproom_transfer', 'family_use'];
/** Kegs on these removals are emptied on the premises rather than shipped. */
const REMOVAL_KEG_EMPTIED = ['destroyed', 'breakage', 'family_use', 'sample_testing'];
const TAX_CLASS_LABELS = [
    'hard_cider' => 'Hard cider', 'still_wine' => 'Still wine', 'artificially_carbonated_wine' => 'Artificially carbonated wine', 'sparkling_wine' => 'Sparkling wine',
];
const CBMA_TIER_INDEX = ['tier1' => 0, 'tier2' => 1, 'tier3' => 2];

function find_removals(PDO $pdo, string $search = '', array $filters = [], string $sort = '-removed_at', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(r.number ILIKE :s OR c.name ILIKE :s OR r.reference ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['destination_kind'])) { $where[] = 'r.destination_kind = :dest'; $params['dest'] = $filters['destination_kind']; }
    if (!empty($filters['customer_id'])) { $where[] = 'r.customer_id = :cid'; $params['cid'] = (int) $filters['customer_id']; }
    if (!empty($filters['status'])) { $where[] = 'r.status = :status'; $params['status'] = $filters['status']; }
    if (!empty($filters['date_from'])) { $where[] = 'r.removed_at >= :date_from::date'; $params['date_from'] = $filters['date_from']; }
    if (!empty($filters['date_to'])) { $where[] = "r.removed_at < :date_to::date + 1"; $params['date_to'] = $filters['date_to']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.removals r LEFT JOIN app.customers c ON c.id = r.customer_id';
    return paged_query($pdo,
        'SELECT r.id, r.number, r.direction, r.destination_kind, r.status, r.removed_at, r.reference, r.wine_gallons, r.tax_amount, r.tax_determined,
                r.customer_id, c.name AS customer_name,
                (SELECT COALESCE(sum(l.units), 0) FROM app.removal_lines l WHERE l.removal_id = r.id) AS units'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, REMOVAL_SORTS, '-removed_at') . ', r.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

function find_removal(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.*, c.name AS customer_name, c.permit_number AS customer_permit_number, c.default_destination AS customer_default_destination,
               p.name AS premises_name, p.cbma_tier, fl.name AS from_location_name, fl.tax_state AS from_tax_state,
               tl.name AS to_location_name, tl.tax_state AS to_tax_state,
               uc.display_name AS created_by_name, up.display_name AS posted_by_name,
               rv.number AS reversed_by_number,
               (SELECT o.id FROM app.removals o WHERE o.reversed_by_id = r.id LIMIT 1) AS reversal_of_id,
               (SELECT o.number FROM app.removals o WHERE o.reversed_by_id = r.id LIMIT 1) AS reversal_of_number
        FROM app.removals r
        JOIN app.premises p ON p.id = r.premises_id
        LEFT JOIN app.customers c ON c.id = r.customer_id
        LEFT JOIN app.locations fl ON fl.id = r.from_location_id
        LEFT JOIN app.locations tl ON tl.id = r.to_location_id
        LEFT JOIN app.users uc ON uc.id = r.created_by
        LEFT JOIN app.users up ON up.id = r.posted_by
        LEFT JOIN app.removals rv ON rv.id = r.reversed_by_id
        WHERE r.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF r' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_removal_lines(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.*, lot.lot_number, lot.item_id, lot.unit_cost_base, lot.quality_status, fl.unit_volume_l, fl.batch_id, b.number AS batch_number,
               p.name AS product_name, p.beverage_type, pc.name AS package_name, pc.package_kind,
               k.serial AS keg_serial, k.state AS keg_state, k.current_holder_kind AS keg_holder_kind, k.current_holder_id AS keg_holder_id,
               k.current_lot_id AS keg_lot_id
        FROM app.removal_lines l
        JOIN app.lots lot ON lot.id = l.lot_id
        LEFT JOIN app.finished_lots fl ON fl.lot_id = l.lot_id
        LEFT JOIN app.batches b ON b.id = fl.batch_id
        LEFT JOIN app.products p ON p.id = b.product_id
        LEFT JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
        LEFT JOIN app.kegs k ON k.id = l.keg_id
        WHERE l.removal_id = :id ORDER BY l.id
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Finished lots with available units at a location, keyed by lot_id. */
function find_removable_finished_lots(PDO $pdo, int $location_id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.lot_id, s.lot_number, s.batch_number, s.product_name, s.package_name, s.package_kind, s.tax_class, s.units_available,
               fl.unit_volume_l, l.quality_status
        FROM app.v_finished_stock s
        JOIN app.finished_lots fl ON fl.lot_id = s.lot_id
        JOIN app.lots l ON l.id = s.lot_id
        WHERE s.location_id = :loc AND s.units_available > 0
        ORDER BY s.packaged_on, s.lot_number
    SQL);
    $statement->execute(['loc' => $location_id]);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Finished lots ever removed to the customer, keyed by lot_id; units_available = net units still out there. */
function find_returnable_finished_lots(PDO $pdo, int $customer_id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        WITH moved AS (
            SELECT l.lot_id, sum(CASE WHEN r.direction = 'out' THEN l.units ELSE -l.units END) AS net_units
            FROM app.removal_lines l JOIN app.removals r ON r.id = l.removal_id
            WHERE r.customer_id = :c AND r.posted_at IS NOT NULL
            GROUP BY l.lot_id
            HAVING bool_or(r.direction = 'out')
        )
        SELECT m.lot_id, lot.lot_number, b.number AS batch_number, p.name AS product_name, pc.name AS package_name, pc.package_kind,
               fl.tax_class, GREATEST(m.net_units, 0) AS units_available, fl.unit_volume_l, lot.quality_status
        FROM moved m
        JOIN app.lots lot ON lot.id = m.lot_id
        JOIN app.finished_lots fl ON fl.lot_id = m.lot_id
        JOIN app.batches b ON b.id = fl.batch_id
        JOIN app.products p ON p.id = b.product_id
        JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
        ORDER BY lot.lot_number
    SQL);
    $statement->execute(['c' => $customer_id]);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** Kegs that can go on a line: filled with the lot (out), or at the customer holding the lot (return). id => serial. */
function find_kegs_for_lot(PDO $pdo, int $lot_id, ?int $customer_id): array
{
    if ($customer_id === null) {
        $statement = $pdo->prepare("SELECT id, serial FROM app.kegs WHERE state = 'filled' AND current_lot_id = :lot ORDER BY serial");
        $statement->execute(['lot' => $lot_id]);
    } else {
        $statement = $pdo->prepare("SELECT id, serial FROM app.kegs WHERE state = 'at_customer' AND current_holder_kind = 'customer' AND current_holder_id = :c AND current_lot_id = :lot ORDER BY serial");
        $statement->execute(['lot' => $lot_id, 'c' => $customer_id]);
    }
    return array_column($statement->fetchAll(), 'serial', 'id');
}

/** Location choices: 'from' (bonded packaged goods or taproom), 'taproom' (tax-paid taproom), 'return' (bonded packaged goods). id => label. */
function removals_location_options(PDO $pdo, string $purpose): array
{
    $where = match ($purpose) {
        'from' => "l.kind IN ('packaged_goods', 'taproom') AND l.tax_state = 'bonded'",
        'taproom' => "l.kind = 'taproom' AND l.tax_state = 'tax_paid'",
        'return' => "l.kind = 'packaged_goods' AND l.tax_state = 'bonded'",
        default => 'false',
    };
    $rows = $pdo->query("SELECT l.id, p.name AS premises_name, l.name FROM app.locations l JOIN app.premises p ON p.id = l.premises_id WHERE l.active AND {$where} ORDER BY p.name, l.name")->fetchAll();
    $multi = count(array_unique(array_column($rows, 'premises_name'))) > 1;
    $options = [];
    foreach ($rows as $row) {
        $options[(int) $row['id']] = $multi ? $row['premises_name'] . ' — ' . $row['name'] : $row['name'];
    }
    return $options;
}

function removals_location_premises(PDO $pdo, ?int $locationId): ?int
{
    if ($locationId === null) {
        return null;
    }
    $statement = $pdo->prepare('SELECT premises_id FROM app.locations WHERE id = :id');
    $statement->execute(['id' => $locationId]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (int) $value;
}

/** Lot choices and keg choices for the form lines: ['lots' => lot_id => row, 'kegs' => lot_id => [keg_id => serial]]. */
function removals_line_options(PDO $pdo, string $direction, ?int $fromLocationId, ?int $customerId): array
{
    $lots = [];
    if ($direction === 'in') {
        $lots = $customerId !== null ? find_returnable_finished_lots($pdo, $customerId) : [];
    } elseif ($fromLocationId !== null) {
        $lots = find_removable_finished_lots($pdo, $fromLocationId);
    }
    $kegs = [];
    foreach ($lots as $lotId => $lot) {
        if ($lot['package_kind'] === 'keg') {
            $kegs[$lotId] = find_kegs_for_lot($pdo, $lotId, $direction === 'in' ? $customerId : null);
        }
    }
    return ['lots' => $lots, 'kegs' => $kegs];
}

/**
 * Validate raw form lines against the lot and keg choices. Raw line: lot_id, units, keg_ids[].
 * Returns [lines (n => [lot_id, units, keg_ids]), lineErrors (n => [field => message])].
 */
function removals_validate_lines(array $rawLines, array $options): array
{
    $lines = [];
    $lineErrors = [];
    $unitsByLot = [];
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $n = preg_replace('/[^a-z0-9]/i', '', (string) $n) ?: 'n' . count($lines);
        $lotId = filter_var($raw['lot_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
        $unitsRaw = trim((string) ($raw['units'] ?? ''));
        $kegIds = array_values(array_unique(array_filter(array_map('intval', is_array($raw['keg_ids'] ?? null) ? $raw['keg_ids'] : []))));
        if ($lotId === null && $unitsRaw === '' && $kegIds === []) {
            continue;   // a blank line
        }
        $line = ['lot_id' => $lotId, 'units' => $unitsRaw, 'keg_ids' => $kegIds];
        $errors = [];
        $lot = $lotId !== null ? ($options['lots'][$lotId] ?? null) : null;
        if ($lot === null) {
            $errors['lot_id'] = 'Choose a finished lot from the list.';
        }
        $units = filter_var($unitsRaw, FILTER_VALIDATE_INT);
        if ($units === false || $units < 1) {
            $errors['units'] = 'Enter whole units, at least 1.';
        } else {
            $line['units'] = $units;
        }
        if ($lot !== null && $lot['package_kind'] === 'keg') {
            $allowed = $options['kegs'][$lotId] ?? [];
            if (array_diff($kegIds, array_keys($allowed)) !== []) {
                $errors['keg_ids'] = 'One of the kegs is no longer available for this lot.';
            } elseif ($units !== false && count($kegIds) !== $units) {
                $errors['keg_ids'] = 'Select one keg per unit (' . count($kegIds) . ' selected for ' . $units . ' units).';
            }
        } else {
            $line['keg_ids'] = [];
        }
        if ($lot !== null && $lot['quality_status'] !== 'released') {
            $errors['lot_id'] = 'Lot ' . $lot['lot_number'] . ' is ' . humanize($lot['quality_status']) . ' and cannot be removed.';
        }
        if ($errors === [] && $lot !== null) {
            $unitsByLot[$lotId] = ($unitsByLot[$lotId] ?? 0) + $units;
            if ($unitsByLot[$lotId] > (int) $lot['units_available']) {
                $errors['units'] = 'Only ' . (int) $lot['units_available'] . ' units of ' . $lot['lot_number'] . ' are available.';
            }
        }
        $lines[$n] = $line;
        if ($errors !== []) {
            $lineErrors[$n] = $errors;
        }
    }
    return [$lines, $lineErrors];
}

/** The latest tax class rules of a beverage type as of a date (params jsonb decoded), or null. */
function removals_tax_rules(PDO $pdo, string $beverageType, string $asOf): ?array
{
    static $cache = [];
    $key = $beverageType . '|' . $asOf;
    if (!array_key_exists($key, $cache)) {
        $statement = $pdo->prepare('SELECT params FROM app.tax_class_rules WHERE beverage_type = :b AND effective_from <= :d ORDER BY effective_from DESC LIMIT 1');
        $statement->execute(['b' => $beverageType, 'd' => $asOf]);
        $params = $statement->fetchColumn();
        $cache[$key] = $params === false ? null : json_decode((string) $params, true);
    }
    return $cache[$key];
}

/**
 * Rate and CBMA credit per wine gallon for a tax class. The credit array is params->cbma_credit_per_gal->hard_cider
 * for hard cider and ->wine for the wine classes, indexed by the premises tier (tier1 first, none = 0).
 * Returns ['rate' => ?float, 'credit' => float].
 */
function removals_tax_rate(PDO $pdo, string $beverageType, string $taxClass, string $cbmaTier, string $asOf): array
{
    $rules = removals_tax_rules($pdo, $beverageType, $asOf);
    $rate = isset($rules[$taxClass]['rate_per_gal']) ? (float) $rules[$taxClass]['rate_per_gal'] : null;
    $credit = 0.0;
    if (isset(CBMA_TIER_INDEX[$cbmaTier])) {
        $group = $taxClass === 'hard_cider' ? 'hard_cider' : 'wine';
        $credit = (float) ($rules['cbma_credit_per_gal'][$group][CBMA_TIER_INDEX[$cbmaTier]] ?? 0);
    }
    return ['rate' => $rate, 'credit' => $credit];
}

/**
 * Tax determination for lines [lot_id, units]: per tax class liters, wine gallons (4 dp), rate, CBMA credit and
 * tax = gallons x (rate - credit). The caller decides whether the destination determines tax.
 * Returns ['classes' => class => [...], 'total_liters', 'total_gallons', 'total_tax', 'tax_class' (single class, 'mixed' or null), 'cbma_tier'].
 */
function compute_removal_tax(PDO $pdo, int $premises_id, array $lines, ?string $asOf = null): array
{
    $asOf = $asOf ?? today();
    $statement = $pdo->prepare('SELECT cbma_tier FROM app.premises WHERE id = :id');
    $statement->execute(['id' => $premises_id]);
    $tier = (string) ($statement->fetchColumn() ?: 'none');
    $lotIds = array_values(array_unique(array_filter(array_map(static fn($l) => (int) ($l['lot_id'] ?? 0), $lines))));
    $lots = [];
    if ($lotIds !== []) {
        $in = implode(',', array_fill(0, count($lotIds), '?'));
        $q = $pdo->prepare("SELECT fl.lot_id, fl.unit_volume_l, fl.tax_class, p.beverage_type FROM app.finished_lots fl
                            JOIN app.batches b ON b.id = fl.batch_id JOIN app.products p ON p.id = b.product_id WHERE fl.lot_id IN ($in)");
        $q->execute($lotIds);
        foreach ($q->fetchAll() as $row) {
            $lots[(int) $row['lot_id']] = $row;
        }
    }
    $classes = [];
    foreach ($lines as $line) {
        $lot = $lots[(int) ($line['lot_id'] ?? 0)] ?? null;
        $units = (int) ($line['units'] ?? 0);
        if ($lot === null || $units < 1) {
            continue;
        }
        $class = (string) $lot['tax_class'];
        $classes[$class] ??= ['tax_class' => $class, 'beverage_type' => $lot['beverage_type'], 'units' => 0, 'liters' => 0.0];
        $classes[$class]['units'] += $units;
        $classes[$class]['liters'] += $units * (float) $lot['unit_volume_l'];
    }
    $totalLiters = 0.0;
    $totalTax = 0.0;
    $missingRate = false;
    foreach ($classes as $class => &$row) {
        $row['gallons'] = round($row['liters'] / LITERS_PER_GALLON, 4);
        $rate = removals_tax_rate($pdo, (string) $row['beverage_type'], $class, $tier, $asOf);
        $row['rate'] = $rate['rate'];
        $row['credit'] = $rate['credit'];
        $row['tax'] = $rate['rate'] === null ? null : round($row['gallons'] * max(0.0, $rate['rate'] - $rate['credit']), 2);
        $missingRate = $missingRate || $rate['rate'] === null;
        $totalLiters += $row['liters'];
        $totalTax += (float) ($row['tax'] ?? 0);
    }
    unset($row);
    ksort($classes);
    return [
        'classes' => $classes, 'total_liters' => $totalLiters, 'total_gallons' => round($totalLiters / LITERS_PER_GALLON, 4),
        'total_tax' => round($totalTax, 2), 'missing_rate' => $missingRate, 'cbma_tier' => $tier,
        'tax_class' => count($classes) === 1 ? (string) array_key_first($classes) : ($classes === [] ? null : 'mixed'),
    ];
}

/** True when this destination determines tax on posting. */
function removal_determines_tax(string $direction, string $destinationKind): bool
{
    return $direction === 'out' && in_array($destinationKind, REMOVAL_TAX_DETERMINED, true);
}

function insert_removal(PDO $pdo, int $premises_id, string $direction, string $destination_kind, ?int $customer_id, ?int $from_location_id, ?int $to_location_id, string $removed_at, ?string $reference, ?string $notes, int $created_by): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.removals (number, premises_id, direction, destination_kind, customer_id, from_location_id, to_location_id, removed_at, reference, notes, created_by)
        VALUES (app.next_number('removal'), :p, :dir, :dest, :c, :from, :to, :at, :ref, :notes, :by)
        RETURNING id, number, status, premises_id, direction, destination_kind, customer_id, from_location_id, to_location_id, removed_at, reference
    SQL);
    $statement->execute(['p' => $premises_id, 'dir' => $direction, 'dest' => $destination_kind, 'c' => $customer_id, 'from' => $from_location_id,
        'to' => $to_location_id, 'at' => $removed_at, 'ref' => $reference, 'notes' => $notes, 'by' => $created_by]);
    return $statement->fetch();
}

function update_removal(PDO $pdo, int $id, int $premises_id, string $direction, string $destination_kind, ?int $customer_id, ?int $from_location_id, ?int $to_location_id, string $removed_at, ?string $reference, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.removals SET premises_id = :p, direction = :dir, destination_kind = :dest, customer_id = :c, from_location_id = :from,
               to_location_id = :to, removed_at = :at, reference = :ref, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, premises_id, direction, destination_kind, customer_id, from_location_id, to_location_id, removed_at, reference
    SQL);
    $statement->execute(['id' => $id, 'p' => $premises_id, 'dir' => $direction, 'dest' => $destination_kind, 'c' => $customer_id, 'from' => $from_location_id,
        'to' => $to_location_id, 'at' => $removed_at, 'ref' => $reference, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft removal can be edited.');
    }
    return $row;
}

/**
 * Replace a draft's lines. Each line: lot_id, units, keg_ids[]. A keg lot is stored one row per keg (units 1, keg_id),
 * so the removal records exactly which kegs left. volume_l = units x unit_volume_l; tax_class from the finished lot.
 */
function replace_removal_lines(PDO $pdo, int $id, array $lines): void
{
    $pdo->prepare('DELETE FROM app.removal_lines WHERE removal_id = :id')->execute(['id' => $id]);
    $lot = $pdo->prepare('SELECT unit_volume_l, tax_class FROM app.finished_lots WHERE lot_id = :lot');
    $insert = $pdo->prepare('INSERT INTO app.removal_lines (removal_id, lot_id, units, volume_l, keg_id, tax_class) VALUES (:r, :lot, :units, :vol, :keg, :class)');
    foreach ($lines as $line) {
        $lot->execute(['lot' => (int) $line['lot_id']]);
        $finished = $lot->fetch();
        if ($finished === false) {
            throw new RuntimeException('Every line must be a finished lot.');
        }
        $rows = !empty($line['keg_ids']) ? array_map(static fn($k) => [1, (int) $k], $line['keg_ids']) : [[(int) $line['units'], null]];
        foreach ($rows as [$units, $kegId]) {
            $insert->execute(['r' => $id, 'lot' => (int) $line['lot_id'], 'units' => $units, 'vol' => round($units * (float) $finished['unit_volume_l'], 3),
                'keg' => $kegId, 'class' => $finished['tax_class']]);
        }
    }
}

/** Stored lines regrouped into form lines: keg rows of one lot become one line with keg_ids. Keyed n1, n2, ... */
function removals_form_lines(array $storedLines): array
{
    $lines = [];
    $kegLine = [];
    foreach ($storedLines as $row) {
        if ($row['keg_id'] !== null) {
            $lotId = (int) $row['lot_id'];
            if (!isset($kegLine[$lotId])) {
                $kegLine[$lotId] = 'n' . (count($lines) + 1);
                $lines[$kegLine[$lotId]] = ['lot_id' => $lotId, 'units' => 0, 'keg_ids' => []];
            }
            $lines[$kegLine[$lotId]]['units']++;
            $lines[$kegLine[$lotId]]['keg_ids'][] = (int) $row['keg_id'];
            continue;
        }
        $lines['n' . (count($lines) + 1)] = ['lot_id' => (int) $row['lot_id'], 'units' => (int) $row['units'], 'keg_ids' => []];
    }
    return $lines;
}

/** Update a keg's state and holder and, when $event is given, write its keg_movements row. */
function removals_transition_keg(PDO $pdo, int $kegId, ?string $event, string $state, string $holderKind, ?int $holderId, ?int $currentLotId,
    ?int $movementLotId, ?int $customerId, ?int $locationId, int $removalId, int $actorId, ?string $note = null): void
{
    $pdo->prepare('UPDATE app.kegs SET state = :s, current_holder_kind = :hk, current_holder_id = :hid, current_lot_id = :lot, last_moved_at = now() WHERE id = :id')
        ->execute(['s' => $state, 'hk' => $holderKind, 'hid' => $holderId, 'lot' => $currentLotId, 'id' => $kegId]);
    if ($event !== null) {
        $pdo->prepare('INSERT INTO app.keg_movements (keg_id, event, lot_id, customer_id, location_id, removal_id, actor_id, note) VALUES (:k, :e, :lot, :c, :loc, :r, :a, :n)')
            ->execute(['k' => $kegId, 'e' => $event, 'lot' => $movementLotId, 'c' => $customerId, 'loc' => $locationId, 'r' => $removalId, 'a' => $actorId, 'n' => $note]);
    }
}

/** Net units of a lot removed to a customer and not yet returned (posted documents). */
function removals_units_out_at_customer(PDO $pdo, int $customerId, int $lotId): int
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT COALESCE(sum(CASE WHEN r.direction = 'out' THEN l.units ELSE -l.units END), 0)
        FROM app.removal_lines l JOIN app.removals r ON r.id = l.removal_id
        WHERE r.customer_id = :c AND l.lot_id = :lot AND r.posted_at IS NOT NULL
    SQL);
    $statement->execute(['c' => $customerId, 'lot' => $lotId]);
    return (int) $statement->fetchColumn();
}

/** Post a draft removal or return. Caller owns the transaction and the activity log. */
function post_removal(PDO $pdo, int $id, int $actor_id): array
{
    $removal = find_removal($pdo, $id, true) ?? throw new RuntimeException('That removal does not exist.');
    if ($removal['status'] !== 'draft') {
        throw new RuntimeException('Removal ' . $removal['number'] . ' is already ' . $removal['status'] . '.');
    }
    return removals_post($pdo, $removal, find_removal_lines($pdo, $id), $actor_id, null);
}

/**
 * The posting path shared by post and reverse. $reversalOf is the removal being reversed (null for a normal post):
 * its rows carry ttb_category 'returned' and its kegs go back to the state they had before $reversalOf posted.
 * One ledger group: out rows at from_location ('removal'), the taproom's in row ('transfer_in'), return rows ('return').
 */
function removals_post(PDO $pdo, array $removal, array $lines, int $actorId, ?array $reversalOf): array
{
    $id = (int) $removal['id'];
    $number = (string) $removal['number'];
    $dest = (string) $removal['destination_kind'];
    $direction = (string) $removal['direction'];
    $customerId = $removal['customer_id'] === null ? null : (int) $removal['customer_id'];
    $fromId = $removal['from_location_id'] === null ? null : (int) $removal['from_location_id'];
    $toId = $removal['to_location_id'] === null ? null : (int) $removal['to_location_id'];
    if ($lines === []) {
        throw new RuntimeException($number . ' has no lines to post.');
    }

    // (1) Validate availability, lot status and keg states.
    $unitsByLot = [];
    foreach ($lines as $line) {
        if ($line['unit_volume_l'] === null) {
            throw new RuntimeException('Lot ' . $line['lot_number'] . ' is not a finished lot.');
        }
        $unitsByLot[(int) $line['lot_id']] = ($unitsByLot[(int) $line['lot_id']] ?? 0) + (int) $line['units'];
    }
    $lotNumbers = array_column($lines, 'lot_number', 'lot_id');
    foreach ($unitsByLot as $lotId => $units) {
        if ($direction === 'out' || $fromId !== null) {
            $onHand = lot_on_hand($pdo, $lotId, (int) $fromId);
            if ($onHand < $units) {
                throw new RuntimeException('Only ' . format_qty($onHand, 0) . ' units of ' . $lotNumbers[$lotId] . ' are at ' . ($removal['from_location_name'] ?? 'the source location') . '; ' . $units . ' requested.');
            }
        }
        if ($direction === 'in' && $reversalOf === null) {
            $out = removals_units_out_at_customer($pdo, (int) $customerId, $lotId);
            if ($out < $units) {
                throw new RuntimeException('Only ' . $out . ' units of ' . $lotNumbers[$lotId] . ' were removed to ' . $removal['customer_name'] . ' and not yet returned.');
            }
        }
    }
    foreach ($lines as $line) {
        if ($direction === 'out' && $line['quality_status'] !== 'released') {
            throw new RuntimeException('Lot ' . $line['lot_number'] . ' is ' . humanize($line['quality_status']) . ' and cannot be removed.');
        }
        if ($line['keg_id'] === null) {
            continue;
        }
        $expected = removals_expected_keg_state($removal, $reversalOf, $line);
        if ($expected !== null) {
            throw new RuntimeException('Keg ' . $line['keg_serial'] . ' ' . $expected);
        }
    }

    // (2)-(3) Ledger rows, one group.
    $group = new_group_id();
    $at = (new DateTimeImmutable((string) $removal['removed_at']))->format(DATE_ATOM);
    $ttb = $reversalOf !== null ? 'returned' : REMOVAL_TTB_CATEGORIES[$dest];
    $premisesId = (int) $removal['premises_id'];
    $note = $reversalOf !== null ? 'Reversal of ' . $reversalOf['number'] : null;
    foreach ($lines as $line) {
        $lid = (int) $line['id'];
        $common = [(int) $line['item_id'], (int) $line['lot_id']];
        $cost = (float) $line['unit_cost_base'];
        $units = (float) $line['units'];
        if ($direction === 'out') {
            [$cpKind, $cpId] = in_array($dest, REMOVAL_DISPOSAL_DESTINATIONS, true) ? ['disposal', null]
                : ($dest === 'taproom_transfer' ? ['location', $toId] : ['customer', $customerId]);
            insert_inventory_transaction($pdo, $group, 'removal', $common[0], $common[1], (int) $fromId, $premisesId, -$units, $cost, $cpKind, $cpId, null,
                $ttb, 'removal', $id, 'rm:' . $id . ':line:' . $lid, $at, $actorId, $note);
            if ($dest === 'taproom_transfer') {
                insert_inventory_transaction($pdo, $group, 'transfer_in', $common[0], $common[1], (int) $toId, $premisesId, $units, $cost, 'location', $fromId, null,
                    'none', 'removal', $id, 'rm:' . $id . ':line:' . $lid . ':in', $at, $actorId, $note);
            }
        } else {
            if ($fromId !== null) {   // reversing a taproom transfer: the units leave the tax-paid taproom
                insert_inventory_transaction($pdo, $group, 'return', $common[0], $common[1], $fromId, $premisesId, -$units, $cost, 'location', $toId, null,
                    'none', 'return', $id, 'rm:' . $id . ':line:' . $lid . ':out', $at, $actorId, $note);
            }
            [$cpKind, $cpId] = $customerId !== null ? ['customer', $customerId] : ($fromId !== null ? ['location', $fromId] : ['disposal', null]);
            insert_inventory_transaction($pdo, $group, 'return', $common[0], $common[1], (int) $toId, $premisesId, $units, $cost, $cpKind, $cpId, null,
                'returned', 'return', $id, 'rm:' . $id . ':line:' . $lid, $at, $actorId, $note);
        }
    }

    // (4) Kegs.
    $kegs = [];
    foreach ($lines as $line) {
        if ($line['keg_id'] === null) {
            continue;
        }
        $kegId = (int) $line['keg_id'];
        $lotId = (int) $line['lot_id'];
        if ($direction === 'out') {         // a reversal of a return re-posts a destination, so the same transitions apply
            if (in_array($dest, REMOVAL_KEG_EMPTIED, true)) {
                removals_transition_keg($pdo, $kegId, null, 'empty', 'location', $fromId, null, null, null, null, $id, $actorId, $note);
                $kegs[] = ['keg_id' => $kegId, 'serial' => $line['keg_serial'], 'event' => null];
            } elseif ($dest === 'taproom_transfer') {
                removals_transition_keg($pdo, $kegId, 'ship', 'filled', 'location', $toId, $lotId, $lotId, null, $toId, $id, $actorId, $note);
                $kegs[] = ['keg_id' => $kegId, 'serial' => $line['keg_serial'], 'event' => 'keg_shipped'];
            } else {
                removals_transition_keg($pdo, $kegId, 'ship', 'at_customer', 'customer', $customerId, $lotId, $lotId, $customerId, null, $id, $actorId, $note);
                $kegs[] = ['keg_id' => $kegId, 'serial' => $line['keg_serial'], 'event' => 'keg_shipped'];
            }
        } elseif ($reversalOf !== null) {        // undoing a removal: filled with the lot at the original source
            removals_transition_keg($pdo, $kegId, 'return', 'filled', 'location', $toId, $lotId, $lotId, $reversalOf['customer_id'] === null ? null : (int) $reversalOf['customer_id'], $toId, $id, $actorId, $note);
            $kegs[] = ['keg_id' => $kegId, 'serial' => $line['keg_serial'], 'event' => 'keg_returned'];
        } else {
            removals_transition_keg($pdo, $kegId, 'return', 'returned_dirty', 'location', $toId, null, $lotId, $customerId, $toId, $id, $actorId);
            $kegs[] = ['keg_id' => $kegId, 'serial' => $line['keg_serial'], 'event' => 'keg_returned'];
        }
    }

    // (5) Tax determination and status.
    $tax = compute_removal_tax($pdo, $premisesId, $lines, (new DateTimeImmutable((string) $removal['removed_at']))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d'));
    $determined = removal_determines_tax($direction, $dest);
    if ($determined && $tax['missing_rate']) {
        throw new RuntimeException('No tax rate is configured for one of the tax classes on ' . $number . '; check the tax class rules.');
    }
    $single = $tax['tax_class'] !== null && $tax['tax_class'] !== 'mixed' ? $tax['classes'][$tax['tax_class']] : null;
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.removals SET wine_gallons = :gal, tax_class = :class, tax_rate_per_gal = :rate, cbma_credit_per_gal = :credit, tax_amount = :tax,
               tax_determined = :det, status = 'posted', posted_by = :by, posted_at = now()
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, destination_kind, wine_gallons, tax_class, tax_rate_per_gal, cbma_credit_per_gal, tax_amount, tax_determined, posted_at
    SQL);
    $statement->execute(['id' => $id, 'gal' => $tax['total_gallons'], 'class' => $tax['tax_class'],
        'rate' => $determined && $single ? $single['rate'] : null, 'credit' => $determined && $single ? $single['credit'] : null,
        'tax' => $determined ? $tax['total_tax'] : null, 'det' => $determined ? 't' : 'f', 'by' => $actorId]);
    $posted = $statement->fetch();
    if ($posted === false) {
        throw new RuntimeException('Only a draft removal can be posted.');
    }
    return ['removal' => $posted, 'units' => array_sum($unitsByLot), 'tax' => $tax, 'kegs' => $kegs];
}

/** Null when the keg on this line is where posting expects it; otherwise the reason it is not. */
function removals_expected_keg_state(array $removal, ?array $reversalOf, array $line): ?string
{
    $state = $line['keg_state'];
    $holderKind = $line['keg_holder_kind'];
    $holderId = $line['keg_holder_id'] === null ? null : (int) $line['keg_holder_id'];
    $lotOk = (int) ($line['keg_lot_id'] ?? 0) === (int) $line['lot_id'];
    $customerId = $removal['customer_id'] === null ? null : (int) $removal['customer_id'];
    if ($reversalOf === null) {
        if ($removal['direction'] === 'out') {
            return $state === 'filled' && $lotOk ? null : 'is ' . humanize($state) . ', not filled with ' . $line['lot_number'] . '.';
        }
        return $state === 'at_customer' && $holderKind === 'customer' && $holderId === $customerId && $lotOk ? null : 'is not at ' . $removal['customer_name'] . ' with ' . $line['lot_number'] . '.';
    }
    if ($reversalOf['direction'] === 'in') {   // undoing a return: the keg must still be where the return left it
        $left = $reversalOf['reversal_of_id'] !== null ? ($state === 'filled' && $lotOk) : $state === 'returned_dirty';
        return $left && $holderKind === 'location' && $holderId === (int) $reversalOf['to_location_id'] ? null : 'has moved on since ' . $reversalOf['number'] . ' and cannot be put back.';
    }
    $origDest = (string) $reversalOf['destination_kind'];
    $ok = match (true) {
        in_array($origDest, REMOVAL_KEG_EMPTIED, true) => $state === 'empty',
        $origDest === 'taproom_transfer' => $state === 'filled' && $holderKind === 'location' && $holderId === (int) $reversalOf['to_location_id'],
        default => $state === 'at_customer' && $holderKind === 'customer' && $holderId === ($reversalOf['customer_id'] === null ? null : (int) $reversalOf['customer_id']),
    };
    return $ok ? null : 'has moved on since ' . $reversalOf['number'] . ' and cannot be put back.';
}

/**
 * Reverse a posted removal or return: a new document with the same lines and the direction flipped, posted through
 * removals_post(); the original becomes 'reversed' with reversed_by_id. Caller owns the transaction and the log.
 */
function reverse_removal(PDO $pdo, int $id, int $actor_id, string $reason): array
{
    $original = find_removal($pdo, $id, true) ?? throw new RuntimeException('That removal does not exist.');
    if ($original['status'] !== 'posted') {
        throw new RuntimeException($original['number'] . ' is ' . $original['status'] . '; only a posted removal can be reversed.');
    }
    $customerId = $original['customer_id'] === null ? null : (int) $original['customer_id'];
    if ($original['direction'] === 'out') {
        $direction = 'in';
        $dest = 'return_from_customer';
        $from = $original['destination_kind'] === 'taproom_transfer' ? (int) $original['to_location_id'] : null;
        $to = (int) $original['from_location_id'];
    } else {
        $direction = 'out';
        $source = $original['reversal_of_id'] !== null ? find_removal($pdo, (int) $original['reversal_of_id']) : null;
        if ($source !== null) {           // this return undid a removal: re-post that removal's destination
            $dest = (string) $source['destination_kind'];
            $customerId = $source['customer_id'] === null ? null : (int) $source['customer_id'];
            $to = $source['to_location_id'] === null ? null : (int) $source['to_location_id'];
        } else {                          // a customer return: the goods go back out to the customer's default destination
            $dest = (string) ($original['customer_default_destination'] ?? '');
            $to = null;
            if (!in_options($dest, REMOVAL_OUT_DESTINATIONS) || $dest === 'taproom_transfer') {
                throw new RuntimeException('Record a new removal instead: ' . $original['number'] . ' has no destination to send the goods back to.');
            }
        }
        $from = (int) $original['to_location_id'];
    }
    $reversal = insert_removal($pdo, (int) $original['premises_id'], $direction, $dest, $customerId, $from, $to, (new DateTimeImmutable())->format(DATE_ATOM),
        $original['reference'], 'Reversal of ' . $original['number'] . ': ' . $reason, $actor_id);
    $copy = $pdo->prepare('INSERT INTO app.removal_lines (removal_id, lot_id, units, volume_l, keg_id, tax_class, note) SELECT :new, lot_id, units, volume_l, keg_id, tax_class, note FROM app.removal_lines WHERE removal_id = :old ORDER BY id');
    $copy->execute(['new' => (int) $reversal['id'], 'old' => $id]);
    $newRemoval = find_removal($pdo, (int) $reversal['id'], true);
    $result = removals_post($pdo, $newRemoval, find_removal_lines($pdo, (int) $reversal['id']), $actor_id, $original);
    $statement = $pdo->prepare("UPDATE app.removals SET status = 'reversed', reversed_by_id = :new WHERE id = :id AND status = 'posted' RETURNING id, number, status, reversed_by_id");
    $statement->execute(['new' => (int) $reversal['id'], 'id' => $id]);
    return ['original' => $statement->fetch(), 'reversal' => $result['removal'], 'post' => $result];
}

function delete_removal(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare("DELETE FROM app.removals WHERE id = :id AND status = 'draft'");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() > 0;
}

/** The single 5120.17 premises when there is exactly one (fallback for the tax readback before a location is chosen). */
function removals_default_premises_id(PDO $pdo): ?int
{
    $rows = $pdo->query("SELECT id FROM app.premises WHERE active AND report_form = '5120.17' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    return count($rows) >= 1 ? (int) $rows[0] : null;
}

/** A Y-m-d value, or '' when empty or malformed (list filters). */
function removals_valid_date(string $raw): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    return $d && $d->format('Y-m-d') === $raw ? $raw : '';
}

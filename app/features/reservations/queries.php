<?php
declare(strict_types=1);

// Equipment reservations (db/023): one resource (a vessel or a piece of equipment) × one window, for a run or a block.
// Shared by the reservation screens, the production order's equipment plan, the schedule and the run pages.

const RESERVATION_KINDS = ['run' => 'A run', 'cleaning' => 'Cleaning', 'maintenance' => 'Maintenance', 'hold' => 'Hold'];
const RESERVATION_SUBJECT_KINDS = ['production_order' => 'Production order', 'batch' => 'Batch', 'press_run' => 'Press run', 'packaging_run' => 'Packaging run'];
const RESERVATION_ROLES = ['primary' => 'Primary', 'maturation' => 'Maturation', 'brite' => 'Brite', 'blend' => 'Blend', 'press' => 'Press',
    'mill' => 'Mill', 'transfer' => 'Transfer', 'filter' => 'Filter', 'carbonate' => 'Carbonate', 'package' => 'Package', 'other' => 'Other'];
/** The roles offered first for each resource kind; every role is accepted. */
const RESERVATION_ROLES_BY_RESOURCE = [
    'vessel' => ['primary', 'maturation', 'brite', 'blend', 'press', 'transfer', 'other'],
    'equipment' => ['mill', 'transfer', 'filter', 'carbonate', 'package', 'other'],
];
const RESERVATION_SUBJECT_URLS = ['production_order' => '/production-orders/', 'batch' => '/batches/', 'press_run' => '/press-runs/', 'packaging_run' => '/packaging-runs/'];
/** Statuses under which a run may still be booked (the picker's lists). */
const RESERVATION_SUBJECT_OPEN = [
    'production_order' => "status IN ('planned','released','in_progress')", 'batch' => "status = 'active'",
    'press_run' => "status = 'draft'", 'packaging_run' => "status = 'draft'",
];

// Presentation helpers (no PDO) --------------------------------------------------------------------------------------

function reservation_subject_url(string $kind, int $id): string
{
    return (RESERVATION_SUBJECT_URLS[$kind] ?? '/') . $id;
}

/** "Nov 2 – Nov 16, 2026" for an all-day booking; "Nov 18, 2026, 8:00 AM – 12:00 PM" for a timed one. */
function reservation_window_label(array $b): string
{
    $tz = new DateTimeZone((string) config('app.timezone'));
    $start = (new DateTimeImmutable($b['starts_at']))->setTimezone($tz);
    $end = (new DateTimeImmutable($b['ends_at']))->setTimezone($tz);
    if ($b['all_day']) {
        $last = $end->modify('-1 second');
        return $start->format('Y-m-d') === $last->format('Y-m-d') ? $start->format('M j, Y') : $start->format('M j') . ' – ' . $last->format('M j, Y');
    }
    return $start->format('Y-m-d') === $end->format('Y-m-d')
        ? $start->format('M j, Y, g:i A') . ' – ' . $end->format('g:i A')
        : $start->format('M j, g:i A') . ' – ' . $end->format('M j, Y, g:i A');
}

/** The number of calendar days a booking covers, inclusive. */
function reservation_days(array $b): int
{
    return (int) (new DateTimeImmutable($b['local_from']))->diff(new DateTimeImmutable($b['local_to']))->days + 1;
}

/** Build the booking's instants from the form's dates and times in the business's time zone. Returns [starts_at, ends_at] or an error. */
function reservation_window(string $from, string $to, bool $allDay, string $startTime, string $endTime): array|string
{
    $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
    $isTime = static fn(string $t): bool => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
    if (!$isDate($from)) { return 'Enter a start date.'; }
    if (!$isDate($to)) { return 'Enter an end date.'; }
    if ($to < $from) { return 'The end date must be on or after the start date.'; }
    $tz = new DateTimeZone((string) config('app.timezone'));
    if ($allDay) {
        $starts = new DateTimeImmutable($from . ' 00:00:00', $tz);
        $ends = (new DateTimeImmutable($to . ' 00:00:00', $tz))->modify('+1 day');
    } else {
        if (!$isTime($startTime) || !$isTime($endTime)) { return 'Enter a start and an end time, or tick All day.'; }
        $starts = new DateTimeImmutable($from . ' ' . $startTime . ':00', $tz);
        $ends = new DateTimeImmutable($to . ' ' . $endTime . ':00', $tz);
        if ($ends <= $starts) { return 'The end must come after the start.'; }
    }
    return [$starts->format(DATE_ATOM), $ends->format(DATE_ATOM)];
}

/** Split "vessel:12" into [kind, id]; null when it is not a resource key. */
function reservation_resource_key(string $key): ?array
{
    if (!preg_match('/^(vessel|equipment):(\d{1,12})$/', $key, $m)) {
        return null;
    }
    return [$m[1], (int) $m[2]];
}

// Policy -----------------------------------------------------------------------------------------------------------

/** settings.equipment.double_booking: true when the organization allows a booking over a clash (marked shared). */
function double_booking_allowed(PDO $pdo): bool
{
    return (string) $pdo->query("SELECT settings #>> '{equipment,double_booking}' FROM app.client_settings WHERE id = 1")->fetchColumn() === 'allow';
}

function set_double_booking(PDO $pdo, bool $allow): void
{
    $statement = $pdo->prepare("UPDATE app.client_settings SET settings = settings || jsonb_build_object('equipment', COALESCE(settings -> 'equipment', '{}'::jsonb) || jsonb_build_object('double_booking', :v::text)) WHERE id = 1");
    $statement->execute(['v' => $allow ? 'allow' : 'refuse']);
}

// Resources ----------------------------------------------------------------------------------------------------------

/**
 * Every active resource keyed "vessel:12" / "equipment:3": label, resource_kind, resource_id, kind, capacity_l, status,
 * premises_id. Vessels first (by kind), equipment after, as v_equipment_resources sorts them.
 */
function reservation_resource_catalog(PDO $pdo, bool $activeOnly = true): array
{
    $catalog = [];
    $rows = $pdo->query('SELECT resource_kind, resource_id, name, kind, capacity_l, status, premises_id, active FROM app.v_equipment_resources' . ($activeOnly ? ' WHERE active' : '') . ' ORDER BY sort_group, name')->fetchAll();
    foreach ($rows as $row) {
        $key = $row['resource_kind'] . ':' . (int) $row['resource_id'];
        $catalog[$key] = $row + ['key' => $key,
            'label' => $row['name'] . ($row['resource_kind'] === 'vessel' ? ' (' . fmt_qty($row['capacity_l'], 'L') . ')' : ' — ' . humanize($row['kind']))];
    }
    return $catalog;
}

/** The catalog as optgroups: ['Vessels' => [key => label], 'Equipment' => [key => label]]. */
function reservation_resource_groups(array $catalog): array
{
    $groups = ['Vessels' => [], 'Equipment' => []];
    foreach ($catalog as $key => $r) {
        $groups[$r['resource_kind'] === 'vessel' ? 'Vessels' : 'Equipment'][$key] = $r['label'];
    }
    return array_filter($groups);
}

/** One resource row from v_equipment_resources, or null. */
function find_resource(PDO $pdo, string $resourceKind, int $resourceId): ?array
{
    $statement = $pdo->prepare('SELECT resource_kind, resource_id, name, kind, capacity_l, status, premises_id, active FROM app.v_equipment_resources WHERE resource_kind = :k AND resource_id = :id');
    $statement->execute(['k' => $resourceKind, 'id' => $resourceId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

// Subjects (the runs) ------------------------------------------------------------------------------------------------

/** Open runs of one kind for the picker: id => "WO-00012 · Dry". */
function reservation_subject_options(PDO $pdo, string $kind): array
{
    $sql = match ($kind) {
        'production_order' => "SELECT o.id, o.number || ' · ' || p.name AS label FROM app.production_orders o JOIN app.products p ON p.id = o.product_id WHERE o.status IN ('planned','released','in_progress') ORDER BY o.number DESC LIMIT 200",
        'batch' => "SELECT b.id, b.number || ' · ' || p.name AS label FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.status = 'active' ORDER BY b.number DESC LIMIT 200",
        'press_run' => "SELECT id, number || ' · ' || run_on AS label FROM app.press_runs WHERE status = 'draft' ORDER BY number DESC LIMIT 200",
        'packaging_run' => "SELECT k.id, k.number || ' · ' || pc.name AS label FROM app.packaging_runs k JOIN app.packaging_configurations pc ON pc.id = k.packaging_configuration_id WHERE k.status = 'draft' ORDER BY k.number DESC LIMIT 200",
        default => null,
    };
    return $sql === null ? [] : $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** The run behind a reservation: ['number', 'label', 'status'] or null when it does not exist. */
function find_reservation_subject(PDO $pdo, string $kind, int $id): ?array
{
    $sql = match ($kind) {
        'production_order' => 'SELECT o.number, p.name AS label, o.status FROM app.production_orders o JOIN app.products p ON p.id = o.product_id WHERE o.id = :id',
        'batch' => 'SELECT b.number, p.name AS label, b.status FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.id = :id',
        'press_run' => "SELECT number, 'Press run' AS label, status FROM app.press_runs WHERE id = :id",
        'packaging_run' => 'SELECT k.number, pc.name AS label, k.status FROM app.packaging_runs k JOIN app.packaging_configurations pc ON pc.id = k.packaging_configuration_id WHERE k.id = :id',
        default => null,
    };
    if ($sql === null) {
        return null;
    }
    $statement = $pdo->prepare($sql);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

// Reservations -------------------------------------------------------------------------------------------------------

const RESERVATION_SELECT = <<<'SQL'
    SELECT r.id, r.resource_kind, r.resource_id, r.kind, r.subject_kind, r.subject_id, r.role, r.starts_at, r.ends_at, r.all_day, r.shared, r.status,
           r.notes, r.created_by, r.cancelled_by, r.cancelled_at, r.created_at, r.updated_at,
           res.name AS resource_name, res.kind AS resource_type, res.status AS resource_status, res.capacity_l, res.premises_id,
           (r.starts_at AT TIME ZONE app.client_timezone())::date AS local_from,
           ((r.ends_at - interval '1 second') AT TIME ZONE app.client_timezone())::date AS local_to,
           to_char(r.starts_at AT TIME ZONE app.client_timezone(), 'HH24:MI') AS start_time,
           to_char(r.ends_at AT TIME ZONE app.client_timezone(), 'HH24:MI') AS end_time,
           uc.display_name AS created_by_name, ux.display_name AS cancelled_by_name
      FROM app.equipment_reservations r
      JOIN app.v_equipment_resources res ON res.resource_kind = r.resource_kind AND res.resource_id = r.resource_id
      LEFT JOIN app.users uc ON uc.id = r.created_by
      LEFT JOIN app.users ux ON ux.id = r.cancelled_by
SQL;

/** A reservation in any status, with its resource and run (number, label, status). */
function find_reservation(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(RESERVATION_SELECT . ' WHERE r.id = :id' . ($lock ? ' FOR UPDATE OF r' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $subject = $row['subject_kind'] !== null ? find_reservation_subject($pdo, $row['subject_kind'], (int) $row['subject_id']) : null;
    $row['subject_number'] = $subject['number'] ?? null;
    $row['subject_label'] = $subject['label'] ?? null;
    $row['subject_status'] = $subject['status'] ?? null;
    return $row;
}

/** The clashes of a window on a resource (app.equipment_clashes), each with its run, as rows. */
function reservation_clashes(PDO $pdo, string $resourceKind, int $resourceId, string $startsAt, string $endsAt, ?int $excludeId = null): array
{
    $statement = $pdo->prepare('SELECT * FROM app.equipment_clashes(:k, :id, :s::timestamptz, :e::timestamptz, :x)');
    $statement->bindValue('k', $resourceKind);
    $statement->bindValue('id', $resourceId, PDO::PARAM_INT);
    $statement->bindValue('s', $startsAt);
    $statement->bindValue('e', $endsAt);
    $statement->bindValue('x', $excludeId, $excludeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

/** "FV-2 is booked for WO-00012 · Dry · Primary, Nov 10 – Nov 20" — one line per clash, for errors and the trail. */
function reservation_clash_labels(string $resourceName, array $clashes): array
{
    $labels = [];
    foreach ($clashes as $c) {
        $for = $c['subject_number'] !== null ? $c['subject_number'] . ' · ' . $c['subject_label'] : humanize($c['kind']);
        $labels[] = $resourceName . ' is booked for ' . $for . ' · ' . humanize($c['role']) . ', ' . reservation_window_label($c);
    }
    return $labels;
}

/**
 * $r: resource_kind, resource_id, kind, subject_kind?, subject_id?, role, starts_at, ends_at, all_day, shared, notes?.
 */
function insert_reservation(PDO $pdo, array $r, int $userId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.equipment_reservations (resource_kind, resource_id, kind, subject_kind, subject_id, role, starts_at, ends_at, all_day, shared, notes, created_by)
        VALUES (:rk, :rid, :kind, :sk, :sid, :role, :s::timestamptz, :e::timestamptz, :all_day, :shared, :notes, :by)
        RETURNING id, resource_kind, resource_id, kind, subject_kind, subject_id, role, starts_at, ends_at, all_day, shared, status, notes
    SQL);
    $statement->execute(['rk' => $r['resource_kind'], 'rid' => $r['resource_id'], 'kind' => $r['kind'], 'sk' => $r['subject_kind'] ?? null, 'sid' => $r['subject_id'] ?? null,
        'role' => $r['role'], 's' => $r['starts_at'], 'e' => $r['ends_at'], 'all_day' => $r['all_day'] ? 't' : 'f', 'shared' => !empty($r['shared']) ? 't' : 'f',
        'notes' => $r['notes'] ?? null, 'by' => $userId]);
    return $statement->fetch();
}

function update_reservation(PDO $pdo, int $id, array $r): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.equipment_reservations
           SET resource_kind = :rk, resource_id = :rid, kind = :kind, subject_kind = :sk, subject_id = :sid, role = :role,
               starts_at = :s::timestamptz, ends_at = :e::timestamptz, all_day = :all_day, shared = :shared, notes = :notes
         WHERE id = :id AND status = 'booked'
        RETURNING id, resource_kind, resource_id, kind, subject_kind, subject_id, role, starts_at, ends_at, all_day, shared, status, notes
    SQL);
    $statement->execute(['id' => $id, 'rk' => $r['resource_kind'], 'rid' => $r['resource_id'], 'kind' => $r['kind'], 'sk' => $r['subject_kind'] ?? null, 'sid' => $r['subject_id'] ?? null,
        'role' => $r['role'], 's' => $r['starts_at'], 'e' => $r['ends_at'], 'all_day' => $r['all_day'] ? 't' : 'f', 'shared' => !empty($r['shared']) ? 't' : 'f',
        'notes' => $r['notes'] ?? null]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a booked reservation can be changed.');
    }
    return $row;
}

function cancel_reservation(PDO $pdo, int $id, int $userId): array
{
    $statement = $pdo->prepare("UPDATE app.equipment_reservations SET status = 'cancelled', cancelled_by = :by, cancelled_at = now() WHERE id = :id AND status = 'booked' RETURNING id, status, cancelled_at");
    $statement->execute(['id' => $id, 'by' => $userId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('This reservation is already cancelled.');
    }
    return $row;
}

function delete_reservation(PDO $pdo, int $id): void
{
    $pdo->prepare('DELETE FROM app.equipment_reservations WHERE id = :id')->execute(['id' => $id]);
}

/** Booked reservations of one run, from the schedule view (resource, window, clash count), earliest first. */
function find_subject_reservations(PDO $pdo, string $subjectKind, int $subjectId): array
{
    $statement = $pdo->prepare('SELECT s.* FROM app.v_equipment_schedule s WHERE s.subject_kind = :k AND s.subject_id = :id ORDER BY s.starts_at, s.id');
    $statement->execute(['k' => $subjectKind, 'id' => $subjectId]);
    return $statement->fetchAll();
}

/** Cancel every booked reservation of a run (the run was cancelled or deleted). Returns the cancelled rows. */
function cancel_subject_reservations(PDO $pdo, string $subjectKind, int $subjectId, int $userId): array
{
    $statement = $pdo->prepare("UPDATE app.equipment_reservations SET status = 'cancelled', cancelled_by = :by, cancelled_at = now() WHERE subject_kind = :k AND subject_id = :id AND status = 'booked' RETURNING id, resource_kind, resource_id, role, starts_at, ends_at");
    $statement->execute(['k' => $subjectKind, 'id' => $subjectId, 'by' => $userId]);
    return $statement->fetchAll();
}

/**
 * The run closed: a booking still running ends now; one that has not started is cancelled; one already over is left.
 * Returns ['trimmed' => rows, 'cancelled' => rows].
 */
function trim_subject_reservations(PDO $pdo, string $subjectKind, int $subjectId, int $userId): array
{
    $trim = $pdo->prepare("UPDATE app.equipment_reservations SET ends_at = now(), all_day = false WHERE subject_kind = :k AND subject_id = :id AND status = 'booked' AND starts_at < now() AND ends_at > now() RETURNING id, resource_kind, resource_id, role, starts_at, ends_at");
    $trim->execute(['k' => $subjectKind, 'id' => $subjectId]);
    $cancel = $pdo->prepare("UPDATE app.equipment_reservations SET status = 'cancelled', cancelled_by = :by, cancelled_at = now() WHERE subject_kind = :k AND subject_id = :id AND status = 'booked' AND starts_at >= now() RETURNING id, resource_kind, resource_id, role, starts_at, ends_at");
    $cancel->execute(['k' => $subjectKind, 'id' => $subjectId, 'by' => $userId]);
    return ['trimmed' => $trim->fetchAll(), 'cancelled' => $cancel->fetchAll()];
}

/** The open occupant of a vessel when it is not the run's own batch: ['label' => 'B-26-004'] or null. Equipment has no occupant. */
function find_resource_occupant(PDO $pdo, string $resourceKind, int $resourceId, ?string $subjectKind, ?int $subjectId): ?array
{
    if ($resourceKind !== 'vessel') {
        return null;
    }
    $statement = $pdo->prepare(<<<'SQL'
        SELECT vb.occupant_kind, vb.occupant_id, vb.occupant_label, b.production_order_id
          FROM app.v_vessel_board vb LEFT JOIN app.batches b ON vb.occupant_kind = 'batch' AND b.id = vb.occupant_id
         WHERE vb.vessel_id = :id AND vb.occupant_kind IS NOT NULL
    SQL);
    $statement->execute(['id' => $resourceId]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $own = ($subjectKind === 'production_order' && (int) ($row['production_order_id'] ?? 0) === (int) $subjectId)
        || ($subjectKind === 'batch' && $row['occupant_kind'] === 'batch' && (int) $row['occupant_id'] === (int) $subjectId);
    return $own ? null : ['label' => (string) $row['occupant_label']];
}

<?php
declare(strict_types=1);

// Standing orders: recurring demand by customer (weekly, every N weeks, monthly). Future occurrences are
// projected (app.standing_order_occurrences); each occurrence can be turned into a firm order once.

require_once __DIR__ . '/queries.php';

const STANDING_FREQUENCIES = ['weekly' => 'Every week', 'every_n_weeks' => 'Every few weeks', 'monthly' => 'Every month'];
const STANDING_WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
const STANDING_SORTS = ['number' => 's.number', 'customer' => 'c.name', 'starts_on' => 's.starts_on'];

/** "Every 2 weeks on Friday", "Every month on the 15th". */
function standing_schedule_label(array $s): string
{
    return match ($s['frequency']) {
        'weekly' => 'Every week on ' . (STANDING_WEEKDAYS[(int) $s['weekday']] ?? '?'),
        'every_n_weeks' => 'Every ' . (int) $s['interval_weeks'] . ' weeks on ' . (STANDING_WEEKDAYS[(int) $s['weekday']] ?? '?'),
        'monthly' => 'Every month on the ' . standing_ordinal((int) $s['day_of_month']),
        default => humanize($s['frequency']),
    };
}

/** 1st, 2nd, 3rd, 4th ... 21st, 22nd (the intl extension is not installed). */
function standing_ordinal(int $n): string
{
    $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    return $n . $suffix;
}

function find_standing_orders(PDO $pdo, string $search = '', string $sort = 'customer', int $page = 1, ?bool $active = true, ?int $customerId = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(s.number ILIKE :s OR c.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($active !== null) {
        $where[] = $active ? '(s.active AND (s.ends_on IS NULL OR s.ends_on >= current_date))' : 'NOT (s.active AND (s.ends_on IS NULL OR s.ends_on >= current_date))';
    }
    if ($customerId !== null) {
        $where[] = 's.customer_id = :customer';
        $params['customer'] = $customerId;
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.standing_orders s JOIN app.customers c ON c.id = s.customer_id';
    return paged_query(
        $pdo,
        "SELECT s.id, s.number, s.customer_id, c.name AS customer_name, s.frequency, s.interval_weeks, s.weekday, s.day_of_month, s.starts_on, s.ends_on, s.active,
                (SELECT SUM(l.units) FROM app.standing_order_lines l WHERE l.standing_order_id = s.id) AS units_each,
                (SELECT SUM(l.units * COALESCE(l.unit_price, pc.default_unit_price)) FROM app.standing_order_lines l
                   JOIN app.packaging_configurations pc ON pc.id = l.packaging_configuration_id WHERE l.standing_order_id = s.id) AS value_each,
                (SELECT MIN(o.occurs_on) FROM app.standing_order_occurrences(current_date, current_date + 400) o
                  WHERE o.standing_order_id = s.id
                    AND NOT EXISTS (SELECT 1 FROM app.sales_orders so WHERE so.standing_order_id = s.id AND so.standing_occurrence_on = o.occurs_on AND so.status <> 'cancelled')) AS next_on"
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, STANDING_SORTS, 'customer') . ', s.id',
        'SELECT count(*)' . $from . $whereSql,
        $params,
        $page
    );
}

function find_standing_order(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.*, c.name AS customer_name, c.default_destination, pr.name AS premises_name, u.display_name AS created_by_name
        FROM app.standing_orders s JOIN app.customers c ON c.id = s.customer_id JOIN app.premises pr ON pr.id = s.premises_id
        LEFT JOIN app.users u ON u.id = s.created_by WHERE s.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_standing_order_lines(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.line_no, l.packaging_configuration_id, l.units AS units_ordered, l.unit_price, pc.name AS configuration_name, pc.default_unit_price,
               p.name AS product_name, l.units * COALESCE(l.unit_price, pc.default_unit_price) AS line_total
        FROM app.standing_order_lines l JOIN app.packaging_configurations pc ON pc.id = l.packaging_configuration_id JOIN app.products p ON p.id = pc.product_id
        WHERE l.standing_order_id = :id ORDER BY l.line_no
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** The next occurrences (from today, up to $limit within a year) with the firm order made from each, if any. */
function find_standing_occurrences(PDO $pdo, int $id, int $limit = 8): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.occurs_on, so.id AS order_id, so.number AS order_number, so.status AS order_status
        FROM app.standing_order_occurrences(current_date, current_date + 366) o
        LEFT JOIN app.sales_orders so ON so.standing_order_id = o.standing_order_id AND so.standing_occurrence_on = o.occurs_on AND so.status <> 'cancelled'
        WHERE o.standing_order_id = :id ORDER BY o.occurs_on LIMIT :n
    SQL);
    $statement->bindValue('id', $id, PDO::PARAM_INT);
    $statement->bindValue('n', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

/** Orders already made from this standing order, newest first. */
function find_standing_order_orders(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT so.id, so.number, so.status, so.requested_on, so.standing_occurrence_on FROM app.sales_orders so
        WHERE so.standing_order_id = :id ORDER BY so.standing_occurrence_on DESC LIMIT 50
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Insert or update a standing order and replace its lines (nothing points at standing order lines). */
function save_standing_order(PDO $pdo, ?int $id, array $s, array $lines, int $userId): array
{
    $params = ['customer' => $s['customer_id'], 'premises' => $s['premises_id'], 'frequency' => $s['frequency'],
               'interval' => $s['frequency'] === 'every_n_weeks' ? $s['interval_weeks'] : null,
               'weekday' => $s['frequency'] === 'monthly' ? null : $s['weekday'], 'day' => $s['frequency'] === 'monthly' ? $s['day_of_month'] : null,
               'starts' => $s['starts_on'], 'ends' => $s['ends_on'], 'notes' => $s['notes'] ?: null];
    if ($id === null) {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO app.standing_orders (number, customer_id, premises_id, frequency, interval_weeks, weekday, day_of_month, starts_on, ends_on, notes, created_by)
            VALUES (app.next_number('standing_order'), :customer, :premises, :frequency, :interval, :weekday, :day, :starts, :ends, :notes, :by)
            RETURNING id, number, customer_id, frequency, interval_weeks, weekday, day_of_month, starts_on, ends_on, active
        SQL);
        $statement->execute($params + ['by' => $userId]);
    } else {
        $statement = $pdo->prepare(<<<'SQL'
            UPDATE app.standing_orders SET customer_id = :customer, premises_id = :premises, frequency = :frequency, interval_weeks = :interval, weekday = :weekday,
                   day_of_month = :day, starts_on = :starts, ends_on = :ends, notes = :notes
            WHERE id = :id
            RETURNING id, number, customer_id, frequency, interval_weeks, weekday, day_of_month, starts_on, ends_on, active
        SQL);
        $statement->execute($params + ['id' => $id]);
    }
    $saved = $statement->fetch() ?: throw new RuntimeException('The standing order could not be saved.');
    $pdo->prepare('DELETE FROM app.standing_order_lines WHERE standing_order_id = :id')->execute(['id' => $saved['id']]);
    $insert = $pdo->prepare('INSERT INTO app.standing_order_lines (standing_order_id, line_no, packaging_configuration_id, units, unit_price) VALUES (:s, :n, :c, :u, :p)');
    foreach (array_values($lines) as $i => $line) {
        $insert->execute(['s' => $saved['id'], 'n' => $i + 1, 'c' => $line['packaging_configuration_id'], 'u' => $line['units_ordered'], 'p' => $line['unit_price']]);
    }
    return $saved;
}

function set_standing_order_active(PDO $pdo, int $id, bool $active): array
{
    $statement = $pdo->prepare('UPDATE app.standing_orders SET active = :a WHERE id = :id RETURNING id, number, active');
    $statement->execute(['id' => $id, 'a' => $active ? 't' : 'f']);
    return $statement->fetch() ?: throw new RuntimeException('That standing order does not exist.');
}

/**
 * Turn one occurrence into a confirmed customer order: due on the occurrence date, the standing lines at their price
 * (or the list price), destination from the customer. Refused for a date that is not an occurrence or already has an order.
 */
function create_order_from_standing(PDO $pdo, array $standing, string $occursOn, int $userId): array
{
    $check = $pdo->prepare('SELECT 1 FROM app.standing_order_occurrences(CAST(:d AS date), CAST(:d AS date)) WHERE standing_order_id = :id');
    $check->execute(['d' => $occursOn, 'id' => $standing['id']]);
    if ($check->fetchColumn() === false) {
        throw new RuntimeException(format_date($occursOn) . ' is not a date of ' . $standing['number'] . '.');
    }
    $lines = find_standing_order_lines($pdo, (int) $standing['id']);
    if ($lines === []) {
        throw new RuntimeException($standing['number'] . ' has no lines.');
    }
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.sales_orders (number, customer_id, premises_id, status, origin, destination_kind, ordered_on, requested_on,
                                      standing_order_id, standing_occurrence_on, notes, created_by, confirmed_by, confirmed_at)
        VALUES (app.next_number('sales_order'), :c, :p, 'confirmed', 'standing', :dest, LEAST(current_date, CAST(:d AS date)), :d, :s, :d, :notes, :by, :by, now())
        RETURNING id, number, status, requested_on
    SQL);
    $statement->execute(['c' => $standing['customer_id'], 'p' => $standing['premises_id'], 'dest' => order_default_destination($standing), 'd' => $occursOn,
        's' => $standing['id'], 'notes' => 'From standing order ' . $standing['number'] . '.', 'by' => $userId]);
    $order = $statement->fetch();
    $insert = $pdo->prepare('INSERT INTO app.sales_order_lines (sales_order_id, line_no, packaging_configuration_id, units_ordered, unit_price) VALUES (:o, :n, :c, :u, :p)');
    foreach ($lines as $i => $line) {
        $insert->execute(['o' => $order['id'], 'n' => $i + 1, 'c' => $line['packaging_configuration_id'], 'u' => $line['units_ordered'], 'p' => $line['unit_price'] ?? $line['default_unit_price']]);
    }
    return $order;
}

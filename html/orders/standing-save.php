<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/standing.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$id = request_integer('id');
$before = $id !== null ? (find_standing_order($pdo, $id) ?? not_found('That standing order does not exist.')) : null;
$customers = order_customer_options($pdo, $before ? (int) $before['customer_id'] : null);
$premises = premises_options($pdo);
$standing = [
    'id' => $id, 'number' => $before['number'] ?? null,
    'customer_id' => request_integer('customer_id'), 'premises_id' => request_integer('premises_id'),
    'frequency' => request_string('frequency', 20), 'interval_weeks' => request_integer('interval_weeks'),
    'weekday' => request_integer('weekday'), 'day_of_month' => request_integer('day_of_month'),
    'starts_on' => post_date('starts_on'), 'ends_on' => post_date('ends_on'), 'notes' => request_string('notes', 2000),
];
$errors = [];
if ($standing['customer_id'] === null || !isset($customers[$standing['customer_id']])) { $errors['customer_id'] = 'Choose a customer.'; }
if ($standing['premises_id'] === null || !isset($premises[$standing['premises_id']])) { $errors['premises_id'] = 'Choose a premises.'; }
if (!in_options($standing['frequency'], STANDING_FREQUENCIES)) { $errors['frequency'] = 'Choose how often.'; }
if ($standing['frequency'] === 'every_n_weeks' && ($standing['interval_weeks'] === null || $standing['interval_weeks'] < 2 || $standing['interval_weeks'] > 52)) {
    $errors['interval_weeks'] = 'Enter every 2 to 52 weeks.';
}
if (in_array($standing['frequency'], ['weekly', 'every_n_weeks'], true) && !isset(STANDING_WEEKDAYS[(int) $standing['weekday']])) { $errors['weekday'] = 'Choose the day of the week.'; }
if ($standing['frequency'] === 'monthly' && ($standing['day_of_month'] === null || $standing['day_of_month'] < 1 || $standing['day_of_month'] > 28)) {
    $errors['day_of_month'] = 'Choose a day from 1 to 28, so every month has it.';
}
if ($standing['starts_on'] === null || $standing['starts_on'] === false) { $errors['starts_on'] = 'Enter the first date it applies.'; }
if ($standing['ends_on'] === false) { $errors['ends_on'] = 'Use a valid date.'; }
if (!isset($errors['starts_on']) && is_string($standing['ends_on']) && $standing['ends_on'] < $standing['starts_on']) { $errors['ends_on'] = 'The end cannot be before the start.'; }
$catalog = order_format_catalog($pdo, $id !== null ? array_column(find_standing_order_lines($pdo, $id), 'packaging_configuration_id') : []);
[$lines, $lineErrors] = validate_order_lines(is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [], $catalog, true);
if ($lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = save_standing_order($pdo, $id, $standing, $lines, (int) $user['id']);
        log_activity($pdo, $id === null ? 'standing_order_created' : 'standing_order_updated', 'standing_order', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['lines' => array_values($lines)],
            ['customer' => $customers[$saved['customer_id']]['name'] ?? null], $id === null ? 'standing-order-add' : 'standing-order-edit');
        $pdo->commit();
        flash('success', 'Standing order ' . $saved['number'] . ' saved.');
        hx_trigger('ordersChanged');
        hx_location('/orders/standing/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The standing order could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $standing['number'] : 'New Standing Order', $id ? 'standing-order-edit' : 'standing-order-add', view('orders/partials/standing-form.php', [
    'standing' => $standing, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors,
    'catalog' => $catalog, 'customers' => $customers, 'premises' => $premises,
]), 'standing_order', $id);

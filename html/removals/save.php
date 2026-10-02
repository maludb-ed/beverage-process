<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$tz = new DateTimeZone((string) config('app.timezone'));

$id = request_integer('id');
$direction = request_string('direction', 3) === 'in' ? 'in' : 'out';
$removedAtRaw = request_string('removed_at', 20);
$removedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $removedAtRaw, $tz);
$removal = [
    'id' => $id, 'direction' => $direction, 'destination_kind' => request_string('destination_kind', 30), 'customer_id' => request_integer('customer_id'),
    'from_location_id' => request_integer('from_location_id'), 'to_location_id' => request_integer('to_location_id'),
    'removed_at' => $removedAtRaw, 'reference' => request_string('reference', 80), 'notes' => request_string('notes', 2000),
];
$errors = [];
$destOptions = $direction === 'in' ? REMOVAL_IN_DESTINATIONS : REMOVAL_OUT_DESTINATIONS;
if (!in_options($removal['destination_kind'], $destOptions)) {
    $errors['destination_kind'] = 'Choose where the goods go.';
    $removal['destination_kind'] = $direction === 'in' ? 'return_from_customer' : 'tax_paid_sale';
}
$dest = $removal['destination_kind'];
$customers = customers_options($pdo);
$customer = null;
if (in_array($dest, REMOVAL_CUSTOMER_REQUIRED, true)) {
    if ($removal['customer_id'] === null || !isset($customers[$removal['customer_id']])) {
        $errors['customer_id'] = $direction === 'in' ? 'Choose the customer returning the goods.' : 'Choose the customer for a ' . mb_strtolower(REMOVAL_DESTINATIONS[$dest]) . '.';
    } else {
        $customer = find_customer($pdo, $removal['customer_id']);
        if ($dest === 'in_bond_transfer' && trim((string) ($customer['permit_number'] ?? '')) === '') {
            $errors['customer_id'] = $customer['name'] . ' has no permit number; an in-bond transfer needs the consignee\'s permit.';
        }
    }
} else {
    $removal['customer_id'] = null;
}
$fromLocations = removals_location_options($pdo, 'from');
$toLocations = $direction === 'in' ? removals_location_options($pdo, 'return') : ($dest === 'taproom_transfer' ? removals_location_options($pdo, 'taproom') : []);
if ($direction === 'out') {
    if ($removal['from_location_id'] === null || !isset($fromLocations[$removal['from_location_id']])) {
        $errors['from_location_id'] = 'Choose the bonded location the goods leave from.';
    }
} else {
    $removal['from_location_id'] = null;
}
if ($direction === 'in' || $dest === 'taproom_transfer') {
    if ($removal['to_location_id'] === null || !isset($toLocations[$removal['to_location_id']])) {
        $errors['to_location_id'] = $direction === 'in' ? 'Choose the bonded packaged-goods location the goods return to.' : 'Choose the tax-paid taproom location.';
    }
} else {
    $removal['to_location_id'] = null;
}
if ($removedAt === false) { $errors['removed_at'] = 'Enter when the goods left.'; }
$premisesId = removals_location_premises($pdo, $removal['from_location_id'] ?? $removal['to_location_id']);
if ($premisesId !== null && $removal['to_location_id'] !== null && $removal['from_location_id'] !== null
    && removals_location_premises($pdo, $removal['to_location_id']) !== $premisesId) {
    $errors['to_location_id'] = 'The taproom must belong to the same premises.';
}

$options = removals_line_options($pdo, $direction, $removal['from_location_id'], $removal['customer_id']);
[$lines, $lineErrors] = removals_validate_lines(is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [], $options);
if ($lines === []) { $errors['lines'] = 'Add at least one finished lot.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

$before = null;
if ($id !== null) {
    $before = find_removal($pdo, $id) ?? not_found('That removal does not exist.');
    if ($before['status'] !== 'draft') { $errors['form'] = 'Only a draft removal can be edited.'; }
    $removal['number'] = $before['number'];
}

if ($errors === [] && $premisesId === null) { $errors['form'] = 'The location has no premises.'; }
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $at = $removedAt->format(DATE_ATOM);
        $args = [$premisesId, $direction, $dest, $removal['customer_id'], $removal['from_location_id'], $removal['to_location_id'], $at,
            $removal['reference'] ?: null, $removal['notes'] ?: null];
        $saved = $id === null ? insert_removal($pdo, ...[...$args, (int) $user['id']]) : update_removal($pdo, $id, ...$args);
        replace_removal_lines($pdo, (int) $saved['id'], array_values($lines));
        orders_link_removal_lines($pdo, (int) $saved['id']);
        $summary = array_map(static fn($l) => ['lot_id' => $l['lot_id'], 'units' => $l['units'], 'kegs' => count($l['keg_ids'])], array_values($lines));
        log_activity($pdo, $id === null ? 'removal_created' : 'removal_updated', 'removal', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['lines' => $summary], [], $id === null ? ($direction === 'in' ? 'return-add' : 'removal-add') : 'removal-edit');
        orders_refresh_statuses($pdo, orders_for_removal($pdo, (int) $saved['id']), 'removal-edit');
        $pdo->commit();
        flash('success', ($direction === 'in' ? 'Return ' : 'Removal ') . $saved['number'] . ' saved as a draft. Post it to move the stock' . (removal_determines_tax($direction, $dest) ? ' and determine tax.' : '.'));
        hx_trigger('removalsChanged');
        hx_location('/removals/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The removal could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
$screen = $id !== null ? 'removal-edit' : ($direction === 'in' ? 'return-add' : 'removal-add');
render_screen($id ? 'Edit ' . $removal['number'] : ($direction === 'in' ? 'Add Return' : 'Add Removal'), $screen, view('removals/partials/form.php', [
    'removal' => $removal, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors, 'customers' => customers_options($pdo, $removal['customer_id']),
    'fromLocations' => $fromLocations, 'toLocations' => $toLocations, 'options' => $options,
]), 'removal', $id);

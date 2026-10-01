<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$before = find_lot($pdo, $id) ?? not_found('That lot does not exist.');
$supplierLot = request_string('supplier_lot_number', 80);
$expires = post_date('expires_on');
$notes = request_string('notes', 2000);
$errors = [];
if ($expires === false) { $errors['expires_on'] = 'Use a valid date.'; }
if ($errors === []) {
    $pdo->beginTransaction();
    $after = update_lot($pdo, $id, $supplierLot ?: null, $expires, $notes ?: null);
    log_activity($pdo, 'lot_updated', 'lot', $id, $before['lot_number'], array_intersect_key($before, $after), $after, [], 'lot-edit');
    $pdo->commit();
    flash('success', 'Lot ' . $before['lot_number'] . ' saved.');
    hx_trigger('lotsChanged');
    hx_location('/lots/' . $id);
}
http_response_code(422);
render_screen('Edit ' . $before['lot_number'], 'lot-edit', view('lots/partials/form.php', [
    'lot' => array_merge($before, ['supplier_lot_number' => $supplierLot, 'expires_on' => request_string('expires_on', 10), 'notes' => $notes]), 'errors' => $errors,
]), 'lot', $id);

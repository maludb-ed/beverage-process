<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/finished-lots/queries.php';

// Pattern C: overrides the tax class and returns only the tax class card.
require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That finished lot does not exist.');
$lot = find_finished_lot($pdo, $id) ?? not_found('That finished lot does not exist.');
[$reasons, $defaultReason] = finished_lot_override_reasons($pdo);
$input = ['tax_class' => request_string('tax_class', 40), 'reason_code_id' => request_integer('reason_code_id'), 'note' => request_string('note', 200)];
$errors = [];
if (!in_options($input['tax_class'], FINISHED_LOT_TAX_CLASSES)) { $errors['tax_class'] = 'Choose a tax class.'; }
if ($input['reason_code_id'] === null || !isset($reasons[$input['reason_code_id']])) { $errors['reason_code_id'] = 'Choose a reason for the override.'; }
$notice = null;
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $before = ['tax_class' => $lot['tax_class'], 'tax_class_source' => $lot['tax_class_source']];
        $saved = override_finished_lot_tax_class($pdo, $id, $input['tax_class'], (int) $input['reason_code_id'], (int) $user['id']);
        log_activity($pdo, 'finished_lot_tax_class_overridden', 'finished_lot', $id, $lot['lot_number'], $before,
            ['tax_class' => $saved['tax_class'], 'tax_class_source' => $saved['tax_class_source']], ['reason' => $reasons[$input['reason_code_id']], 'note' => $input['note'] ?: null], 'finished-lot-view');
        $pdo->commit();
        hx_trigger('finishedLotsChanged');
        $lot = find_finished_lot($pdo, $id);
        $notice = 'Tax class set to ' . strtolower(FINISHED_LOT_TAX_CLASSES[$input['tax_class']]) . '.';
        $input = [];
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The tax class could not be changed.') : $exception->getMessage();
    }
}
if ($errors !== []) { http_response_code(422); }
echo view('finished-lots/partials/tax-class-card.php', [
    'lot' => $lot, 'derived' => derive_finished_lot_tax_class($pdo, $id), 'limits' => finished_lot_hard_cider_limits($pdo), 'reasons' => $reasons, 'defaultReason' => $defaultReason,
    'canOverride' => true, 'errors' => $errors, 'input' => $input, 'notice' => $notice,
]);

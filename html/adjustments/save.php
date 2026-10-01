<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/validation.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();

$id = request_integer('id');
$locations = inventory_locations($pdo);
$reasons = inventory_reason_options($pdo, 'adjustment');
$adjustedRaw = request_string('adjusted_at', 20);
$adjustedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $adjustedRaw, new DateTimeZone((string) config('app.timezone')));
$adjustment = [
    'id' => $id,
    'location_id' => request_integer('location_id'),
    'reason_code_id' => request_integer('reason_code_id'),
    'adjusted_at' => $adjustedRaw,
    'notes' => request_string('notes', 2000),
];
$errors = [];
$location = $locations[$adjustment['location_id']] ?? null;
if ($location === null) { $errors['location'] = 'Choose the location being adjusted.'; }
if ($adjustment['reason_code_id'] === null || !isset($reasons[$adjustment['reason_code_id']])) { $errors['reason'] = 'Choose a reason.'; }
if ($adjustedAt === false) { $errors['adjusted_at'] = 'Enter when the adjustment applies.'; }

$rawLines = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];
[$lines, $lineErrors] = $location !== null ? validate_adjustment_lines($pdo, $rawLines, (int) $adjustment['location_id'], (bool) $location['allow_negative']) : [[], []];
if ($location !== null && $lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

$before = null;
if ($id !== null) {
    $before = find_adjustment($pdo, $id) ?? not_found('That adjustment does not exist.');
    if (!in_array($before['status'], ['draft', 'pending_approval'], true)) { $errors['form'] = 'Only draft adjustments can be edited.'; }
    $adjustment['number'] = $before['number'];
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $at = $adjustedAt->format(DATE_ATOM);
        $saved = $id === null
            ? insert_adjustment($pdo, $adjustment['location_id'], $adjustment['reason_code_id'], $at, $adjustment['notes'] ?: null, (int) $user['id'], $lines)
            : update_adjustment($pdo, $id, $adjustment['location_id'], $adjustment['reason_code_id'], $at, $adjustment['notes'] ?: null, $lines);
        $summary = array_map(static fn($l) => ['item_id' => $l['item_id'], 'lot_id' => $l['lot_id'], 'qty_delta_base' => $l['qty_delta_base']], array_values($lines));
        log_activity($pdo, $id === null ? 'adjustment_created' : 'adjustment_updated', 'adjustment', (int) $saved['id'], $saved['number'],
            $before === null ? null : ['status' => $before['status'], 'reason_code_id' => $before['reason_code_id'], 'lines' => count($before['lines'])],
            $saved + ['lines' => $summary], [], $id === null ? 'adjustment-add' : 'adjustment-edit');
        $pdo->commit();
        flash('success', 'Adjustment ' . $saved['number'] . ($saved['status'] === 'pending_approval'
            ? ' saved and is waiting for owner approval before it can be posted.' : ' saved as a draft. Post it to change the stock.'));
        hx_trigger('adjustmentsChanged');
        hx_location('/adjustments/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The adjustment could not be saved.') : $exception->getMessage();
    }
}
$lines = adjustment_prepare_lines($pdo, $adjustment['location_id'], $lines === [] ? ['n1' => []] : $lines);
http_response_code(422);
render_screen($id ? 'Edit ' . $adjustment['number'] : 'Add Adjustment', $id ? 'adjustment-edit' : 'adjustment-add', view('adjustments/partials/form.php', [
    'adjustment' => $adjustment, 'lines' => $lines, 'errors' => $errors, 'lineErrors' => $lineErrors, 'locations' => $locations, 'reasons' => $reasons, 'itemOptions' => item_options($pdo),
]), 'adjustment', $id);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/validation.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();

$id = request_integer('id');
$locations = inventory_locations($pdo);
$transferredRaw = request_string('transferred_at', 20);
$transferredAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $transferredRaw, new DateTimeZone((string) config('app.timezone')));
$transfer = [
    'id' => $id,
    'from_location_id' => request_integer('from_location_id'),
    'to_location_id' => request_integer('to_location_id'),
    'transferred_at' => $transferredRaw,
    'notes' => request_string('notes', 2000),
];
$errors = [];
$from = $locations[$transfer['from_location_id']] ?? null;
$to = $locations[$transfer['to_location_id']] ?? null;
if ($from === null) { $errors['from_location'] = 'Choose where the stock is moving from.'; }
if ($to === null) { $errors['to_location'] = 'Choose where the stock is moving to.'; }
if ($from !== null && $to !== null) {
    if ($transfer['from_location_id'] === $transfer['to_location_id']) {
        $errors['to_location'] = 'Choose a different location than the source.';
    } elseif ($from['tax_state'] !== $to['tax_state']) {
        $errors['to_location'] = TRANSFER_TAX_STATE_MESSAGE . '.';
    }
}
if ($transferredAt === false) { $errors['transferred_at'] = 'Enter when the stock moved.'; }

$rawLines = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];
[$lines, $lineErrors] = $from !== null ? validate_transfer_lines($pdo, $rawLines, (int) $transfer['from_location_id']) : [[], []];
if ($from !== null && $lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

$before = null;
if ($id !== null) {
    $before = find_transfer($pdo, $id) ?? not_found('That transfer does not exist.');
    if ($before['status'] !== 'draft') { $errors['form'] = 'Only draft transfers can be edited.'; }
    $transfer['number'] = $before['number'];
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $at = $transferredAt->format(DATE_ATOM);
        $saved = $id === null
            ? insert_transfer($pdo, $transfer['from_location_id'], $transfer['to_location_id'], $at, $transfer['notes'] ?: null, (int) $user['id'], $lines)
            : update_transfer($pdo, $id, $transfer['from_location_id'], $transfer['to_location_id'], $at, $transfer['notes'] ?: null, $lines);
        $summary = array_map(static fn($l) => ['item_id' => $l['item_id'], 'lot_id' => $l['lot_id'], 'qty_base' => $l['qty_base']], array_values($lines));
        log_activity($pdo, $id === null ? 'transfer_created' : 'transfer_updated', 'transfer', (int) $saved['id'], $saved['number'],
            $before === null ? null : ['from_location_id' => $before['from_location_id'], 'to_location_id' => $before['to_location_id'], 'lines' => count($before['lines'])],
            $saved + ['lines' => $summary], [], $id === null ? 'transfer-add' : 'transfer-edit');
        $pdo->commit();
        flash('success', 'Transfer ' . $saved['number'] . ' saved as a draft. Post it to move the stock.');
        hx_trigger('transfersChanged');
        hx_location('/transfers/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The transfer could not be saved.') : $exception->getMessage();
    }
}
$fromId = $transfer['from_location_id'];
$lines = transfer_prepare_lines($pdo, $fromId, $lines === [] ? ['n1' => []] : $lines);
http_response_code(422);
render_screen($id ? 'Edit ' . $transfer['number'] : 'Add Transfer', $id ? 'transfer-edit' : 'transfer-add', view('transfers/partials/form.php', [
    'transfer' => $transfer, 'lines' => $lines, 'errors' => $errors, 'lineErrors' => $lineErrors, 'locations' => $locations,
    'itemOptions' => $fromId ? inventory_items_at_location($pdo, $fromId) : [],
]), 'transfer', $id);

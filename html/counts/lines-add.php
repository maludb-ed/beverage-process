<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// Add a line for a lot found that had no expected balance; returns the refreshed sheet.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');
$itemId = request_integer('item_id') ?? 0;
$lotId = request_integer('lot_id') ?? 0;
$errors = [];
if ($itemId === 0) { $errors['item_id'] = 'Choose an item.'; }
if ($lotId === 0) { $errors['lot_id'] = 'Choose a lot.'; }
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $count = find_count($pdo, $id, true) ?? not_found('That count does not exist.');
        $line = add_count_line($pdo, $id, $itemId, $lotId);
        log_activity($pdo, 'count_line_added', 'count', $id, $count['number'], null, ['line_id' => (int) $line['id'], 'item_id' => $itemId, 'lot' => $line['lot_number']], [], 'count-view');
        $pdo->commit();
        hx_trigger('countsChanged');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The line could not be added.') : $exception->getMessage();
    }
}
$count = find_count($pdo, $id) ?? not_found('That count does not exist.');
$addForm = $errors === [] ? '' : view('counts/partials/line-add-form.php', [
    'count' => $count, 'itemOptions' => item_options($pdo), 'lots' => $itemId ? find_count_lots($pdo, $itemId, (int) $count['location_id']) : [],
    'input' => ['item_id' => $itemId, 'lot_id' => $lotId], 'errors' => $errors,
]);
if ($errors !== []) {
    http_response_code(422);
}
header('Vary: HX-Request');
echo view('counts/partials/sheet.php', ['count' => $count, 'lines' => $count['lines'], 'canEdit' => in_array($count['status'], ['open', 'counting'], true), 'addForm' => $addForm]);

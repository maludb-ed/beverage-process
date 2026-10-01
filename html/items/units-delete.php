<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');

$id = request_integer('id') ?? not_found('That item does not exist.');
$pdo = db();
$item = find_item($pdo, $id) ?? not_found('That item does not exist.');
$unit = find_item_unit($pdo, request_integer('unit_id') ?? 0);
if ($unit === null || (int) $unit['item_id'] !== $id) {
    not_found('That unit does not exist.');
}
$errors = [];
try {
    $pdo->beginTransaction();
    delete_item_unit($pdo, (int) $unit['id']);
    log_activity($pdo, 'item_unit_removed', 'item', $id, $item['code'], $unit, null, [], 'item-view');
    $pdo->commit();
    hx_trigger('itemChanged');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    $errors['form'] = db_error_message($exception) ?? 'The unit could not be removed.';
    http_response_code(422);
}
header('Vary: HX-Request');
echo view('items/units-tab.php', ['item' => $item, 'units' => find_item_units($pdo, $id), 'unitInput' => [], 'unitErrors' => $errors, 'canEdit' => true]);

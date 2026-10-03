<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/item-classes/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That item class does not exist.');
$class = find_item_class($pdo, $id) ?? not_found('That item class does not exist.');
if ($class['is_builtin']) {
    flash('error', '"' . $class['name'] . '" is a built-in class and cannot be deleted. Deactivate it instead.');
    hx_location('/item-classes/' . $id . '/edit');
}
if ((int) $class['item_count'] > 0) {
    flash('error', '"' . $class['name'] . '" is used by ' . $class['item_count'] . ' item' . ((int) $class['item_count'] === 1 ? '' : 's') . ', so it cannot be deleted. Deactivate it instead.');
    hx_location('/item-classes/' . $id . '/edit');
}
try {
    $pdo->beginTransaction();
    $deleted = delete_item_class($pdo, $id);
    if ($deleted) {
        log_activity($pdo, 'item_class_deleted', 'item_class', $id, $class['code'], $class, null, [], 'item-class-edit');
    }
    $pdo->commit();
    flash($deleted ? 'success' : 'error', $deleted ? 'Item class "' . $class['name'] . '" deleted.' : 'The item class could not be deleted.');
    hx_trigger('itemClassesChanged');
    hx_location($deleted ? '/item-classes/' : '/item-classes/' . $id . '/edit');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The item class could not be deleted: ' . (db_error_message($exception) ?? 'it is still in use.'));
}
hx_location('/item-classes/' . $id . '/edit');

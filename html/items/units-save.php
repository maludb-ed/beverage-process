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
$input = [
    'unit_code' => request_string('unit_code', 20),
    'unit_name' => request_string('unit_name', 60),
    'to_base_factor' => request_string('to_base_factor', 20),
    'is_purchase_default' => post_bool('is_purchase_default'),
];
$errors = [];
if ($input['unit_code'] === '') { $errors['unit_code'] = 'Unit code is required.'; }
if ($input['unit_name'] === '') { $errors['unit_name'] = 'Unit name is required.'; }
$factor = post_decimal('to_base_factor');
if ($factor === null || $factor === false || $factor <= 0) { $errors['to_base_factor'] = 'Enter how many ' . $item['base_unit_code'] . ' one unit holds (greater than 0).'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $unit = insert_item_unit($pdo, $id, $input['unit_code'], $input['unit_name'], (float) $factor, $input['is_purchase_default']);
        log_activity($pdo, 'item_unit_added', 'item', $id, $item['code'], null, $unit, [], 'item-view');
        $pdo->commit();
        hx_trigger('itemChanged');
        $input = [];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if (is_unique_violation($exception)) {
            $errors['unit_code'] = 'This item already has a unit with that code.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The unit could not be saved.';
        }
    }
}
if ($errors !== []) {
    http_response_code(422);
}
header('Vary: HX-Request');
echo view('items/units-tab.php', ['item' => $item, 'units' => find_item_units($pdo, $id), 'unitInput' => $input, 'unitErrors' => $errors, 'canEdit' => true]);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');

$pdo = db();
$id = request_integer('id');
$class = request_string('item_class', 30);
$returnTo = request_string('return_to', 10) === 'materials' ? 'materials' : '';
$before = $id !== null ? (find_item($pdo, $id) ?? not_found('That item does not exist.')) : null;
$classes = $id !== null ? item_class_options($pdo, null, true, [$before['item_class']]) : item_class_options($pdo, $returnTo === 'materials' ? 'material' : null);
$receiptStatus = request_string('default_receipt_status', 20);
if ($receiptStatus === '' && in_options($class, $classes)) {
    $receiptStatus = items_default_receipt_status($class);
}
$input = [
    'id' => $id,
    'return_to' => $returnTo,
    'code' => request_string('code', 40),
    'name' => request_string('name', 160),
    'item_class' => $class,
    'base_unit_code' => request_string('base_unit_code', 10),
    'units_per_case' => request_string('units_per_case', 10),
    'notes' => request_string('notes', 2000),
    'active' => post_bool('active'),
    'lot_controlled' => post_bool('lot_controlled'),
    'catch_weight' => post_bool('catch_weight'),
    'shelf_life_days' => request_string('shelf_life_days', 10),
    'default_receipt_status' => $receiptStatus,
    'consumption_mode' => request_string('consumption_mode', 20),
    'costing_method' => request_string('costing_method', 20),
    'standard_cost_per_base' => request_string('standard_cost_per_base', 20),
    'ttb_material_category' => request_string('ttb_material_category', 20),
    'reorder_point' => request_string('reorder_point', 20),
    'min_qty' => request_string('min_qty', 20),
    'max_qty' => request_string('max_qty', 20),
];
$baseUnits = items_base_unit_options();
$errors = [];
if ($input['code'] === '') { $errors['code'] = 'Code is required.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($class, $classes)) { $errors['item_class'] = 'Choose an item class.'; }
if (!in_options($input['base_unit_code'], $baseUnits)) { $errors['base_unit_code'] = 'Choose a base unit.'; }
if (!in_options($receiptStatus, ITEM_RECEIPT_STATUSES)) { $errors['default_receipt_status'] = 'Choose a default receipt status.'; }
if (!in_options($input['consumption_mode'], ITEM_CONSUMPTION_MODES)) { $errors['consumption_mode'] = 'Choose a consumption mode.'; }
if (!in_options($input['costing_method'], ITEM_COSTING_METHODS)) { $errors['costing_method'] = 'Choose a costing method.'; }
if (!in_options($input['ttb_material_category'], ITEM_TTB_CATEGORIES)) { $errors['ttb_material_category'] = 'Choose a TTB material category.'; }

$unitsPerCase = null;
if ($input['units_per_case'] !== '') {
    if (!ctype_digit($input['units_per_case']) || (int) $input['units_per_case'] < 1) { $errors['units_per_case'] = 'Units per case must be a whole number of 1 or more.'; }
    elseif ($class !== 'finished_good') { $errors['units_per_case'] = 'Units per case applies to finished goods only.'; }
    else { $unitsPerCase = (int) $input['units_per_case']; }
}
$shelfLife = null;
if ($input['shelf_life_days'] !== '') {
    if (!ctype_digit($input['shelf_life_days'])) { $errors['shelf_life_days'] = 'Shelf life must be a whole number of days, 0 or more.'; }
    else { $shelfLife = (int) $input['shelf_life_days']; }
}
$decimals = [];
foreach (['standard_cost_per_base' => 'Standard cost', 'reorder_point' => 'Reorder point', 'min_qty' => 'Minimum quantity', 'max_qty' => 'Maximum quantity'] as $field => $label) {
    $value = post_decimal($field);
    if ($value === false || ($value !== null && $value < 0)) { $errors[$field] = $label . ' must be a number, 0 or more.'; $value = null; }
    $decimals[$field] = $value;
}

if ($errors === []) {
    $base = $input['base_unit_code'];
    $kind = items_unit_kind($class);
    $cost = $decimals['standard_cost_per_base'] === null ? null : $decimals['standard_cost_per_base'] / unit_factor(display_unit($base, $kind));
    try {
        $pdo->beginTransaction();
        $args = [$input['code'], $input['name'], $class, $base, $input['lot_controlled'], $input['catch_weight'], $shelfLife, $receiptStatus,
            $input['consumption_mode'], $input['costing_method'], $cost, from_display($decimals['reorder_point'], $base, $kind),
            from_display($decimals['min_qty'], $base, $kind), from_display($decimals['max_qty'], $base, $kind), $input['ttb_material_category'],
            $unitsPerCase, $input['notes'] ?: null, $input['active']];
        $item = $id === null ? insert_item($pdo, ...$args) : update_item($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'item_created' : 'item_updated', 'item', (int) $item['id'], $item['code'],
            $before, $item, [], $id === null ? 'item-add' : 'item-edit');
        $pdo->commit();
        flash('success', 'Item "' . $item['code'] . '" saved.');
        hx_trigger('itemChanged');
        hx_location($returnTo === 'materials' ? '/inventory/materials' : '/items/' . $item['id']);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if (is_unique_violation($exception)) {
            $errors['code'] = 'An item with this code already exists.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The item could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Item' : ($returnTo === 'materials' ? 'Add Material' : 'Add Item'), $id ? 'item-edit' : 'item-add', view('items/partials/form.php', ['item' => $input, 'classes' => $classes, 'errors' => $errors]), 'item', $id);

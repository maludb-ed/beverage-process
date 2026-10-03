<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id');
// kind=material (the Add Material button on Inventory, Materials) limits the class list to
// material classes and returns to that screen after saving.
$returnTo = request_string('kind', 10) === 'material' || request_string('return_to', 10) === 'materials' ? 'materials' : '';
if ($id !== null) {
    $item = items_form_values(find_item($pdo, $id) ?? not_found('That item does not exist.'));
    $classes = item_class_options($pdo, null, true, [$item['item_class']]);
    $screen = 'item-edit';
} else {
    $classes = item_class_options($pdo, $returnTo === 'materials' ? 'material' : null);
    $class = in_options(request_string('item_class'), $classes) ? request_string('item_class') : (isset($classes['fruit']) ? 'fruit' : (string) array_key_first($classes));
    $item = [
        'name' => request_string('name', 120), 'item_class' => $class, 'lot_controlled' => true, 'catch_weight' => false,
        'default_receipt_status' => items_default_receipt_status($class), 'consumption_mode' => 'explicit', 'costing_method' => 'actual_lot',
        'ttb_material_category' => 'none', 'active' => true,
    ];
    $screen = 'item-add';
}
$item['return_to'] = $returnTo;
log_screen_entered($screen, 'item', $id, $item['code'] ?? null);
render_screen($id ? 'Edit Item' : ($returnTo === 'materials' ? 'Add Material' : 'Add Item'), $screen, view('items/partials/form.php', ['item' => $item, 'classes' => $classes, 'errors' => []]), 'item', $id);

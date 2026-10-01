<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

$user = require_role('receiving');
$id = request_integer('id');
if ($id !== null) {
    $item = items_form_values(find_item(db(), $id) ?? not_found('That item does not exist.'));
    $screen = 'item-edit';
} else {
    $class = in_options(request_string('item_class'), ITEM_CLASSES) ? request_string('item_class') : 'fruit';
    $item = [
        'name' => request_string('name', 120), 'item_class' => $class, 'lot_controlled' => true, 'catch_weight' => false,
        'default_receipt_status' => items_default_receipt_status($class), 'consumption_mode' => 'explicit', 'costing_method' => 'actual_lot',
        'ttb_material_category' => 'none', 'active' => true,
    ];
    $screen = 'item-add';
}
log_screen_entered($screen, 'item', $id, $item['code'] ?? null);
render_screen($id ? 'Edit Item' : 'Add Item', $screen, view('items/partials/form.php', ['item' => $item, 'errors' => []]), 'item', $id);

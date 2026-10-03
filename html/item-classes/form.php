<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/item-classes/queries.php';

$user = require_role('receiving');
$id = request_integer('id');
if ($id !== null) {
    $class = find_item_class(db(), $id) ?? not_found('That item class does not exist.');
    $screen = 'item-class-edit';
} else {
    $kind = request_string('kind', 10);
    $class = ['kind' => in_options($kind, ITEM_CLASS_KINDS) ? $kind : 'material', 'purchasable' => true, 'recipe_ingredient' => false, 'display_order' => 100, 'active' => true, 'is_builtin' => false];
    $screen = 'item-class-add';
}
log_screen_entered($screen, 'item_class', $id, $class['code'] ?? null);
render_screen($id ? 'Edit Item Class' : 'Add Item Class', $screen, view('item-classes/partials/form.php', ['class' => $class, 'errors' => []]), 'item_class', $id);

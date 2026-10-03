<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/item-classes/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');

$pdo = db();
$id = request_integer('id');
$before = $id !== null ? (find_item_class($pdo, $id) ?? not_found('That item class does not exist.')) : null;
$builtin = $before !== null && $before['is_builtin'];
$input = [
    'id' => $id,
    'is_builtin' => $builtin,
    // A built-in class keeps its code and kind whatever the form sends.
    'code' => $builtin ? $before['code'] : strtolower(trim(request_string('code', 30))),
    'name' => request_string('name', 80),
    'kind' => $builtin ? $before['kind'] : request_string('kind', 10),
    'purchasable' => post_bool('purchasable'),
    'recipe_ingredient' => post_bool('recipe_ingredient'),
    'display_order' => request_string('display_order', 6),
    'active' => post_bool('active'),
    'notes' => request_string('notes', 1000),
];
$errors = [];
if ($input['code'] === '') { $errors['code'] = 'Code is required.'; }
elseif (!preg_match(ITEM_CLASS_CODE_PATTERN, $input['code'])) { $errors['code'] = 'Start with a letter; use lowercase letters, digits and underscores, 2 to 30 characters.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], ITEM_CLASS_KINDS)) { $errors['kind'] = 'Choose material or finished product.'; }
if ($input['display_order'] === '' || !ctype_digit($input['display_order'])) { $errors['display_order'] = 'Display order must be a whole number, 0 or more.'; }
if ($builtin && !$input['active'] && $before['code'] === 'finished_good') { $errors['active'] = 'Finished good is the Finished product screen and stays active.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['code'], $input['name'], $input['kind'], $input['purchasable'], $input['recipe_ingredient'], (int) $input['display_order'], $input['active'], $input['notes'] ?: null];
        $class = $id === null ? insert_item_class($pdo, ...$args) : update_item_class($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'item_class_created' : 'item_class_updated', 'item_class', (int) $class['id'], $class['code'],
            $before, $class, [], $id === null ? 'item-class-add' : 'item-class-edit');
        $pdo->commit();
        flash('success', 'Item class "' . $class['name'] . '" saved.');
        hx_trigger('itemClassesChanged');
        hx_location('/item-classes/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['code'] = 'That code already exists.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The item class could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Item Class' : 'Add Item Class', $id ? 'item-class-edit' : 'item-class-add', view('item-classes/partials/form.php', ['class' => $input, 'errors' => $errors]), 'item_class', $id);

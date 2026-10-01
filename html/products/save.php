<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$abv = post_decimal('target_abv');
$share = post_decimal('target_fruit_share_pct');
$input = [
    'id' => $id,
    'code' => request_string('code', 40),
    'name' => request_string('name', 120),
    'beverage_type' => request_string('beverage_type', 10),
    'style' => request_string('style', 120),
    'intended_tax_class' => request_string('intended_tax_class', 40),
    'target_abv' => $abv === false ? request_string('target_abv', 10) : $abv,
    'target_fruit_share_pct' => $share === false ? request_string('target_fruit_share_pct', 10) : $share,
    'contains_other_fruit' => post_bool('contains_other_fruit'),
    'contains_flavoring' => post_bool('contains_flavoring'),
    'status' => request_string('status', 10),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($input['code'] === '') { $errors['code'] = 'Code is required.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['beverage_type'], PRODUCT_BEVERAGES)) { $errors['beverage_type'] = 'Choose a beverage type.'; }
if (!in_options($input['intended_tax_class'], PRODUCT_TAX_CLASSES)) { $errors['intended_tax_class'] = 'Choose the intended tax class.'; }
if (!in_options($input['status'], PRODUCT_STATUSES)) { $errors['status'] = 'Choose a status.'; }
if ($abv === false || ($abv !== null && ($abv < 0 || $abv > 25))) { $errors['target_abv'] = 'Target ABV must be between 0 and 25.'; }
if ($share === false || ($share !== null && ($share < 0 || $share > 100))) { $errors['target_fruit_share'] = 'Fruit share must be between 0 and 100.'; }

$before = $id !== null ? (find_product($pdo, $id) ?? not_found('That product does not exist.')) : null;
if ($before !== null) {
    $before = array_diff_key($before, array_flip(['recipes', 'active_recipe', 'packaging', 'specs', 'approvals', 'batches']));
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['code'], $input['name'], $input['beverage_type'], $input['style'] ?: null, $input['intended_tax_class'], $abv, $share ?? null,
            $input['contains_other_fruit'], $input['contains_flavoring'], $input['status'], $input['notes'] ?: null];
        $product = $id === null ? insert_product($pdo, ...$args) : update_product($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'product_created' : 'product_updated', 'product', (int) $product['id'], $product['name'],
            $before === null ? null : array_intersect_key($before, $product), $product, [], $id === null ? 'product-add' : 'product-edit');
        $pdo->commit();
        flash('success', 'Product "' . $product['name'] . '" saved.');
        hx_trigger('productsChanged');
        hx_location('/products/' . $product['id']);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['code'] = 'That code is already used.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The product could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Product' : 'Add Product', $id ? 'product-edit' : 'product-add', view('products/partials/form.php', ['product' => $input, 'errors' => $errors]), 'product', $id);

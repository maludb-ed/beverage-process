<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_role('production');
$id = request_integer('id');
if ($id !== null) {
    $product = find_product(db(), $id) ?? not_found('That product does not exist.');
    $screen = 'product-edit';
} else {
    $product = ['name' => request_string('name', 120), 'style' => request_string('style', 120), 'beverage_type' => 'cider', 'intended_tax_class' => 'hard_cider', 'target_fruit_share_pct' => '100', 'status' => 'draft'];
    $screen = 'product-add';
}
log_screen_entered($screen, 'product', $id, $product['name'] ?? null);
render_screen($id ? 'Edit ' . $product['name'] : 'Add Product', $screen, view('products/partials/form.php', ['product' => $product, 'errors' => []]), 'product', $id);

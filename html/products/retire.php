<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That product does not exist.');
$product = find_product($pdo, $id) ?? not_found('That product does not exist.');
try {
    $pdo->beginTransaction();
    $after = retire_product($pdo, $id);
    log_activity($pdo, 'product_retired', 'product', $id, $product['name'], ['status' => $product['status']], $after, [], 'product-view');
    $pdo->commit();
    flash('success', 'Product "' . $product['name'] . '" retired.');
    hx_trigger('productsChanged');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', db_error_message($exception) ?? 'The product could not be retired.');
}
hx_location('/products/' . $id);

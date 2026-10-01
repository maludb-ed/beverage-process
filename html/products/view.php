<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That product does not exist.');
$product = find_product($pdo, $id) ?? not_found('That product does not exist.');
$tab = request_string('tab', 20);
$tab = in_array($tab, ['recipes', 'packaging', 'specs', 'approvals', 'batches'], true) ? $tab : 'recipes';
log_screen_entered('product-view', 'product', $id, $product['name']);
render_screen($product['name'], 'product-view', view('products/partials/view.php', ['product' => $product, 'user' => $user, 'activeTab' => $tab]), 'product', $id);

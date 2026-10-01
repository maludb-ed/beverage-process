<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_role('compliance');
$pdo = db();
$id = request_integer('id');
if ($id !== null) {
    $approval = find_approval($pdo, $id) ?? not_found('That approval does not exist.');
    $screen = 'approval-edit';
} else {
    $code = request_string('product', 40);
    $kind = request_string('kind', 10);
    $approval = ['product_id' => $code !== '' ? find_product_id_by_code($pdo, $code) : request_integer('product_id'), 'kind' => in_options($kind, APPROVAL_KINDS) ? $kind : 'formula', 'status' => 'required'];
    $screen = 'approval-add';
}
log_screen_entered($screen, 'product_approval', $id, $approval['product_name'] ?? null);
render_screen($id ? 'Edit approval' : 'Record approval', $screen, view('approvals/partials/form.php', [
    'approval' => $approval, 'errors' => [], 'products' => products_options($pdo, true),
    'packages' => !empty($approval['product_id']) ? approval_package_options($pdo, (int) $approval['product_id']) : [],
]), 'product_approval', $id);

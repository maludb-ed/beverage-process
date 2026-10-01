<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';

// Pattern A fragment: the package select for the chosen product.
$user = require_role('compliance');
$productId = request_integer('product_id');
$packages = $productId !== null ? approval_package_options(db(), $productId) : [];
echo view('approvals/partials/package-select.php', ['packages' => $packages, 'selected' => request_integer('selected'), 'errors' => []]);

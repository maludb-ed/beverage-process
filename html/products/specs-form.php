<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/specs/queries.php';

$user = require_role('quality');
$pdo = db();
$productId = request_integer('id') ?? not_found('That product does not exist.');
$product = find_product($pdo, $productId) ?? not_found('That product does not exist.');
$stages = product_stage_options($pdo, $product['beverage_type']);
$measurements = spec_measurement_options($pdo);
$stage = request_string('stage', 30);
$measurement = request_string('measurement', 30);
$spec = ['product_id' => $productId, 'product_name' => $product['name'], 'stage_code' => isset($stages[$stage]) ? $stage : '', 'measurement_type_code' => isset($measurements[$measurement]) ? $measurement : '', 'active' => true];
log_screen_entered('spec-add', 'product', $productId, $product['name']);
render_screen('Add Spec', 'spec-add', view('specs/partials/form.php', ['spec' => $spec, 'errors' => [], 'stages' => $stages, 'measurements' => $measurements]), 'product', $productId);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

// Pattern A fragment: one stage row. Stage choices come from the product's beverage type.
$user = require_role('production');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$productId = request_integer('product_id') ?? not_found('That product does not exist.');
$product = find_product($pdo, $productId) ?? not_found('That product does not exist.');
$raw = $_GET['stages'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$stage = [
    'seq' => (string) ($raw['seq'] ?? request_string('seq', 6)), 'stage_code' => (string) ($raw['stage_code'] ?? ''),
    'expected_loss_pct' => (string) ($raw['loss'] ?? '0'), 'expected_duration_days' => (string) ($raw['days'] ?? ''), 'instructions' => (string) ($raw['instructions'] ?? ''),
];
echo view('recipes/partials/stage-row.php', ['n' => $n, 'stage' => $stage, 'stageOptions' => product_stage_options($pdo, $product['beverage_type']), 'rowErrors' => []]);

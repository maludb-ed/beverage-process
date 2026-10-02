<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-configs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$catalog = packaging_bom_item_catalog($pdo);
if ($id !== null) {
    $config = find_packaging_configuration($pdo, $id) ?? not_found('That packaging configuration does not exist.');
    $lines = [];
    foreach ($config['bom'] as $i => $line) {
        $lines['n' . ($i + 1)] = ['item_id' => (int) $line['item_id'], 'qty' => round((float) to_display($line['qty_per_unit_base'], $line['base_unit_code']), 6)];
    }
    $config['fill_unit'] = display_unit('L');
    $config['fill_volume'] = round((float) to_display($config['fill_volume_l'], 'L'), 6);
    $screen = 'packaging-config-edit';
} else {
    $code = request_string('product', 40);
    $kind = request_string('package_kind', 10);
    $config = [
        'product_id' => $code !== '' ? find_product_id_by_code($pdo, $code) : null,
        'package_kind' => in_options($kind, PACKAGE_KINDS) ? $kind : 'can',
        'expected_loss_pct' => '2', 'active' => true, 'fill_unit' => display_unit('L'),
    ];
    $lines = ['n1' => []];
    $screen = 'packaging-config-add';
}
log_screen_entered($screen, 'packaging_configuration', $id, $config['name'] ?? null);
render_screen($id ? 'Edit ' . $config['name'] : 'Add Packaging Configuration', $screen, view('packaging-configs/partials/form.php', [
    'config' => $config, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'catalog' => $catalog,
    'canPrice' => user_can($user, 'sales'), 'products' => products_options($pdo), 'finishedItems' => packaging_finished_item_options($pdo), 'fillUnits' => packaging_fill_unit_options(),
]), 'packaging_configuration', $id);

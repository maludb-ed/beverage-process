<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// /batches/new is the pitch form; /batches/{id}/edit (same route file) is the edit form.
if (request_integer('id') !== null) {
    require __DIR__ . '/edit.php';
    return;
}
$user = require_role('production');
$pdo = db();

// Prefill (manifest): product, vessel, production_order — by id or by name/number.
$match = static function (array $options, string $raw): ?int {
    if ($raw === '') { return null; }
    if (ctype_digit($raw) && isset($options[(int) $raw])) { return (int) $raw; }
    $found = array_search(mb_strtolower($raw), array_map('mb_strtolower', $options), true);
    return $found === false ? null : (int) $found;
};
$catalogs = batches_pitch_catalogs($pdo, null);
$productId = $match($catalogs['products'], request_string('product', 120));
$catalogs = batches_pitch_catalogs($pdo, $productId);
$vesselNames = array_map(static fn($v) => $v['name'], $catalogs['vesselCatalog']);
$vesselId = $match($vesselNames, request_string('vessel', 120));
$orderId = $match($catalogs['orders'], request_string('production_order', 40));
$batch = ['product_id' => $productId, 'recipe_version_id' => $productId ? batches_active_recipe_id($pdo, $productId) : null, 'production_order_id' => $orderId, 'vessel_id' => $vesselId];
$juice = ['n1' => []];
$vessel = $vesselId !== null ? $catalogs['vesselCatalog'][$vesselId] : null;
if ($vessel !== null && $vessel['occupant_kind'] === 'lot' && isset($catalogs['juiceLots'][(int) $vessel['occupant_id']])) {
    $juice = ['n1' => ['lot_id' => (int) $vessel['occupant_id'], 'volume_gal' => batches_l_to_volume($vessel['occupancy_volume_l'])]];
}
log_screen_entered('batch-add', 'batch');
render_screen('Pitch a Batch', 'batch-add', view('batches/partials/form.php', ['batch' => $batch, 'juice' => $juice, 'errors' => [], 'juiceErrors' => []] + $catalogs), 'batch');

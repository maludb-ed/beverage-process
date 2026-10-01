<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');

$id = request_integer('id') ?? not_found('That supplier does not exist.');
$pdo = db();
$supplier = find_supplier($pdo, $id) ?? not_found('That supplier does not exist.');
$unitOptions = supplier_purchase_unit_options($pdo);
$itemOptions = item_options($pdo);
$input = [
    'item_id' => request_string('item_id', 20),
    'supplier_sku' => request_string('supplier_sku', 60),
    'purchase_unit_code' => request_string('purchase_unit_code', 20),
    'to_base_factor' => request_string('to_base_factor', 20),
    'last_price' => request_string('last_price', 20),
    'lead_time_days' => request_string('lead_time_days', 10),
];
$errors = [];
$itemId = request_integer('item_id');
if ($itemId === null || !isset($itemOptions[$itemId])) { $errors['item_id'] = 'Choose an item.'; }
if (!in_options($input['purchase_unit_code'], $unitOptions)) { $errors['purchase_unit_code'] = 'Choose a purchase unit.'; }
$factor = post_decimal('to_base_factor');
if ($factor === false || ($factor !== null && $factor <= 0)) { $errors['to_base_factor'] = 'Base units per purchase unit must be greater than 0.'; }
elseif ($factor === null && !isset($errors['item_id']) && !isset($errors['purchase_unit_code'])) {
    $factor = supplier_unit_factor($pdo, $itemId, $input['purchase_unit_code']);
    if ($factor === null) { $errors['to_base_factor'] = 'Enter the base units per purchase unit.'; }
}
$price = post_decimal('last_price');
if ($price === false || ($price !== null && $price < 0)) { $errors['last_price'] = 'Last price must be a number, 0 or more.'; $price = null; }
$lead = null;
if ($input['lead_time_days'] !== '') {
    if (!ctype_digit($input['lead_time_days'])) { $errors['lead_time_days'] = 'Lead time must be a whole number of days, 0 or more.'; }
    else { $lead = (int) $input['lead_time_days']; }
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $row = insert_supplier_item($pdo, $id, $itemId, $input['supplier_sku'] ?: null, $input['purchase_unit_code'], (float) $factor, $price, $lead);
        log_activity($pdo, 'supplier_item_added', 'supplier', $id, $supplier['name'], null, $row, [], 'supplier-view');
        $pdo->commit();
        hx_trigger('supplierChanged');
        $input = [];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if (is_unique_violation($exception)) {
            $errors['item_id'] = 'Already listed.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The item could not be added.';
        }
    }
}
if ($errors !== []) {
    http_response_code(422);
}
header('Vary: HX-Request');
echo view('suppliers/items-tab.php', [
    'supplier' => $supplier, 'items' => find_supplier_items($pdo, $id), 'itemInput' => $input, 'itemErrors' => $errors, 'canEdit' => true,
    'itemOptions' => $itemOptions, 'unitOptions' => $unitOptions,
]);

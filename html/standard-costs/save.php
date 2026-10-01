<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/standard-costs/queries.php';

require_post();
verify_csrf();
$user = require_role();
$pdo = db();

$catalog = standard_cost_item_catalog($pdo);
$itemId = request_integer('item_id');
$cost = post_decimal('cost');
$effective = post_date('effective_from');
$input = ['item_id' => $itemId, 'cost' => $cost === false ? request_string('cost', 20) : $cost, 'effective_from' => $effective === false ? request_string('effective_from', 10) : $effective];
$errors = [];
$item = $itemId !== null ? ($catalog[$itemId] ?? null) : null;
if ($item === null) { $errors['item'] = 'Choose an item.'; }
if ($cost === null || $cost === false || $cost < 0) { $errors['cost'] = 'Enter a cost of zero or more.'; }
if ($effective === null || $effective === false) { $errors['effective_from'] = 'Enter the date the cost takes effect.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $kind = standard_cost_unit_kind($item['item_class']);
        $unit = display_unit($item['base_unit_code'], $kind);
        $costPerBase = $cost / unit_factor($unit);
        $row = insert_standard_cost($pdo, $itemId, $costPerBase, $effective, (int) $user['id']);
        log_activity($pdo, 'standard_cost_set', 'item', $itemId, $item['code'], ['standard_cost_per_base' => $row['previous_standard']],
            ['item' => $item['code'], 'cost_per_base' => $row['cost_per_base'], 'entered' => $cost . ' per ' . $unit, 'effective_from' => $effective, 'applied_to_item' => $row['applied']], [], 'standard-cost-add');
        $pdo->commit();
        flash('success', 'Standard cost for ' . $item['code'] . ' set to $' . number_format($cost, 4) . ' per ' . $unit . ($row['applied'] ? '.' : ' (not yet current).'));
        hx_trigger('standardCostsChanged');
        hx_location('/standard-costs/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['effective_from'] = 'A cost for that date exists.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The standard cost could not be saved.';
        }
    }
}
http_response_code(422);
render_screen('Set Standard Cost', 'standard-cost-add', view('standard-costs/partials/form.php', [
    'input' => $input, 'errors' => $errors, 'catalog' => $catalog, 'history' => $itemId !== null ? find_standard_cost_history($pdo, $itemId) : [],
]), 'item', $itemId);

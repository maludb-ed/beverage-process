<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

// Pattern A: records one attribute and returns only the Attributes tab.
require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot($pdo, $id) ?? not_found('That lot does not exist.');
$input = ['key' => request_string('key', 40), 'other_key' => request_string('other_key', 40), 'value_num' => request_string('value_num', 30), 'value_text' => request_string('value_text', 200), 'unit_code' => request_string('unit_code', 20)];
$key = $input['key'] === 'other' ? strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $input['other_key'])) : $input['key'];
$errors = [];
if ($key === '' || ($input['key'] !== 'other' && !isset(LOT_ATTRIBUTE_KEYS[$key]))) { $errors[] = 'Choose an attribute or name a new one.'; }
$num = post_decimal('value_num');
if ($num === false) { $errors[] = 'The number is not valid.'; }
if ($num === null && $input['value_text'] === '') { $errors[] = 'Enter a number or a text value.'; }
if ($errors === []) {
    $pdo->beginTransaction();
    $previous = find_lot_attribute($pdo, $id, $key);
    $saved = set_lot_attribute($pdo, $id, $key, $num, $input['value_text'] ?: null, $input['unit_code'] ?: null, 'manual', (int) $user['id']);
    log_activity($pdo, 'lot_attribute_set', 'lot', $id, $lot['lot_number'], $previous ? ['key' => $key, 'value_num' => $previous['value_num'], 'value_text' => $previous['value_text']] : null, $saved, [], 'lot-view');
    $pdo->commit();
    hx_trigger('lotsChanged');
    $input = [];
} else {
    http_response_code(422);
}
echo view('lots/partials/attributes-tab.php', ['lot' => $lot, 'attributes' => find_lot_attributes($pdo, $id), 'errors' => $errors, 'canEdit' => true, 'input' => $input]);

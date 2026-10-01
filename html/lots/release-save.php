<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot($pdo, $id) ?? not_found('That lot does not exist.');
$reasons = override_reason_options($pdo);
$input = [
    'to_status' => request_string('to_status', 20), 'basis' => request_string('basis', 20), 'is_override' => post_bool('is_override'),
    'reason_code_id' => request_integer('reason_code_id'), 'note' => request_string('note', 2000),
];
if ($input['basis'] === 'override') { $input['is_override'] = true; }
$errors = [];
if (!in_options($input['to_status'], LOT_STATUSES) || $input['to_status'] === $lot['quality_status']) { $errors['to_status'] = 'Choose a status different from the current one.'; }
if (!in_options($input['basis'], RELEASE_BASES)) { $errors['basis'] = 'Choose the basis for the decision.'; }
if ($input['is_override'] && ($input['reason_code_id'] === null || !isset($reasons[$input['reason_code_id']]))) { $errors['reason_code_id'] = 'An override needs a reason.'; }
if (!$input['is_override']) { $input['reason_code_id'] = null; }

if ($errors === []) {
    $pdo->beginTransaction();
    $decision = insert_release_decision($pdo, 'lot', $id, $lot['quality_status'], $input['to_status'], $input['basis'], $input['is_override'], $input['reason_code_id'], $input['note'] ?: null, (int) $user['id']);
    update_lot_quality_status($pdo, $id, $input['to_status']);
    log_activity($pdo, 'lot_released', 'lot', $id, $lot['lot_number'], ['quality_status' => $lot['quality_status']], ['quality_status' => $input['to_status']],
        ['basis' => $input['basis'], 'is_override' => $input['is_override'], 'decision_id' => $decision['id']], 'lot-release');
    $pdo->commit();
    flash('success', 'Lot ' . $lot['lot_number'] . ' is now ' . strtolower(LOT_STATUSES[$input['to_status']]) . '.');
    hx_trigger('lotsChanged');
    hx_location('/lots/' . $id . '?tab=releases');
}
http_response_code(422);
render_screen('Release ' . $lot['lot_number'], 'lot-release', view('lots/partials/release-form.php', [
    'lot' => $lot, 'attributes' => find_lot_attributes($pdo, $id), 'certificateCount' => count(find_lot_certificates($pdo, $id)),
    'input' => $input, 'errors' => $errors, 'reasons' => $reasons,
]), 'lot', $id);

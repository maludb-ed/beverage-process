<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reason-codes/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');

$id = request_integer('id');
$threshold = post_decimal('requires_approval_above');
$input = [
    'id' => $id,
    'code' => strtoupper(request_string('code', 30)),
    'name' => request_string('name', 120),
    'applies_to' => request_string('applies_to', 20),
    'ttb_category' => request_string('ttb_category', 20),
    'classification' => request_string('classification', 20),
    'requires_approval_above' => $threshold === false ? request_string('requires_approval_above', 30) : $threshold,
    'active' => post_bool('active'),
];
$errors = [];
if ($input['code'] === '') { $errors['code'] = 'Code is required.'; }
elseif (!preg_match('/^[A-Z0-9_-]+$/', $input['code'])) { $errors['code'] = 'Use letters, digits, dashes and underscores only.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['applies_to'], REASON_APPLIES_TO)) { $errors['applies_to'] = 'Choose what this code applies to.'; }
if (!in_options($input['ttb_category'], REASON_TTB_CATEGORIES)) { $errors['ttb_category'] = 'Choose a TTB category.'; }
if (!in_options($input['classification'], REASON_CLASSIFICATIONS)) { $errors['classification'] = 'Choose a classification.'; }
if ($threshold === false || ($threshold !== null && $threshold < 0)) { $errors['requires_approval_above'] = 'Enter a number of zero or more, or leave it empty.'; }

$pdo = db();
$before = $id !== null ? (find_reason_code($pdo, $id) ?? not_found('That reason code does not exist.')) : null;

if ($errors === []) {
    // Volume thresholds are entered in the display volume unit and stored in liters.
    $stored = $threshold !== null && reason_code_threshold_is_volume($input['applies_to']) ? from_display($threshold, 'L') : $threshold;
    try {
        $pdo->beginTransaction();
        $args = [$input['code'], $input['name'], $input['applies_to'], $input['ttb_category'], $input['classification'], $stored, $input['active']];
        $reason = $id === null ? insert_reason_code($pdo, ...$args) : update_reason_code($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'reason_code_created' : 'reason_code_updated', 'reason_code', (int) $reason['id'], $reason['code'],
            $before, $reason, [], $id === null ? 'reason-code-add' : 'reason-code-edit');
        $pdo->commit();
        flash('success', 'Reason code "' . $reason['code'] . '" saved.');
        hx_trigger('reasonCodeChanged');
        hx_location('/reason-codes/');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['code'] = 'That code already exists.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The reason code could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Reason Code' : 'Add Reason Code', $id ? 'reason-code-edit' : 'reason-code-add', view('reason-codes/partials/form.php', ['reason' => $input, 'errors' => $errors, 'inputIsDisplay' => true]), 'reason_code', $id);

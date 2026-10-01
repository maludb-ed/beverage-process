<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/standard-costs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role();
$pdo = db();

$premises = premises_options($pdo);
$premisesId = request_integer('premises_id');
$rate = post_decimal('rate');
$effective = post_date('effective_from');
$input = ['premises_id' => $premisesId, 'rate' => $rate === false ? request_string('rate', 20) : $rate, 'effective_from' => $effective === false ? request_string('effective_from', 10) : $effective];
$errors = [];
if ($premisesId === null || !isset($premises[$premisesId])) { $errors['premises'] = 'Choose a premises.'; }
if ($rate === null || $rate === false || $rate < 0) { $errors['rate'] = 'Enter a rate of zero or more.'; }
if ($effective === null || $effective === false) { $errors['effective_from'] = 'Enter the date the rate takes effect.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $perL = $rate / unit_factor(display_unit('L'));
        $row = insert_overhead_rate($pdo, $premisesId, $perL, $effective, (int) $user['id']);
        log_activity($pdo, 'overhead_rate_set', 'overhead_rate', (int) $row['id'], $premises[$premisesId], null,
            ['premises' => $premises[$premisesId], 'rate_per_l' => $row['rate_per_l'], 'entered' => $rate . ' per ' . display_unit('L'), 'effective_from' => $effective], [], 'standard-cost-overhead');
        $pdo->commit();
        flash('success', 'Overhead rate for ' . $premises[$premisesId] . ' set to $' . number_format($rate, 4) . ' per ' . display_unit('L') . '.');
        hx_trigger('standardCostsChanged');
        hx_location('/standard-costs/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['effective_from'] = 'A rate for that date exists.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The overhead rate could not be saved.';
        }
    }
}
http_response_code(422);
render_screen('Set Overhead Rate', 'standard-cost-overhead', view('standard-costs/partials/overhead-form.php', ['input' => $input, 'errors' => $errors, 'premises' => $premises]), 'overhead_rate');

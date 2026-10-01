<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/vessels/queries.php';

require_post();
verify_csrf();
$user = require_role('production');

$pdo = db();
$id = request_integer('id');
$before = $id !== null ? (find_vessel($pdo, $id) ?? not_found('That vessel does not exist.')) : not_found('That vessel does not exist.');
$status = request_string('status', 20);

$error = null;
if (!in_options($status, VESSEL_SETTABLE_STATUSES)) {
    $error = 'Choose empty, cleaning or out of service.';
} elseif ($before['status'] === 'in_use') {
    http_response_code(409);
    $error = 'A vessel that is in use cannot be changed by hand; finish or cancel the work order that is using it.';
}
if ($error !== null) {
    if (http_response_code() === 200) { http_response_code(422); }
    echo view('shared/error.php', ['message' => $error]);
    exit;
}

try {
    $pdo->beginTransaction();
    $vessel = update_vessel_status($pdo, $id, $status);
    log_activity($pdo, 'vessel_status_set', 'vessel', (int) $vessel['id'], $vessel['name'], ['status' => $before['status']], ['status' => $vessel['status']], [], 'vessels-list');
    $pdo->commit();
} catch (PDOException $exception) {
    $pdo->rollBack();
    error_log($exception->getMessage());
    http_response_code(422);
    echo view('shared/error.php', ['message' => db_error_message($exception) ?? 'The status could not be changed.']);
    exit;
}
flash('success', 'Vessel "' . $vessel['name'] . '" is now ' . humanize($vessel['status']) . '.');
hx_trigger('vesselChanged');
hx_location('/vessels/');

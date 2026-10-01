<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reason-codes/queries.php';

$user = require_role('compliance');
$id = request_integer('id');
if ($id !== null) {
    $reason = find_reason_code(db(), $id) ?? not_found('That reason code does not exist.');
    $screen = 'reason-code-edit';
} else {
    $reason = null;
    $screen = 'reason-code-add';
}
log_screen_entered($screen, 'reason_code', $id, $reason['code'] ?? null);
render_screen($id ? 'Edit Reason Code' : 'Add Reason Code', $screen, view('reason-codes/partials/form.php', ['reason' => $reason ?? [], 'errors' => []]), 'reason_code', $id);

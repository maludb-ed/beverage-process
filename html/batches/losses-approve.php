<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A: approve a loss above its reason's threshold; returns only the losses tab.
// Approval reviews a recorded loss, so it is allowed on a batch that is no longer active.
require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$lossId = request_integer('sub_id') ?? not_found('That loss does not exist.');
$batch = find_batch($pdo, $id) ?? not_found('That batch does not exist.');
$errors = [];
try {
    $pdo->beginTransaction();
    $loss = find_batch_loss($pdo, $id, $lossId, true) ?? not_found('That loss does not exist.');
    if ($loss['requires_approval_above'] === null || (float) $loss['qty_base'] <= (float) $loss['requires_approval_above']) {
        throw new RuntimeException('This loss does not need approval.');
    }
    $approved = approve_batch_loss($pdo, $lossId, (int) $user['id']);
    log_activity($pdo, 'batch_loss_approved', 'batch', $id, $batch['number'], ['loss_event_id' => $lossId, 'approved_at' => null],
        ['loss_event_id' => $lossId, 'qty_l' => (float) $loss['qty_base'], 'reason' => $loss['reason_code'], 'approved_at' => $approved['approved_at']], [], 'batch-view');
    $pdo->commit();
    hx_trigger('batchesChanged');
} catch (PDOException | RuntimeException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('batch loss approve failed: ' . $exception->getMessage());
    $errors[] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The loss could not be approved.') : $exception->getMessage();
    http_response_code(422);
}
echo view('batches/partials/tab-losses.php', ['batch' => $batch, 'losses' => find_batch_losses($pdo, $id), 'canApprove' => true, 'errors' => $errors]);

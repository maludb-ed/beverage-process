<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That reservation does not exist.');

try {
    $pdo->beginTransaction();
    $before = find_reservation($pdo, $id, true) ?? not_found('That reservation does not exist.');
    $after = cancel_reservation($pdo, $id, (int) $user['id']);
    $label = $before['resource_name'] . ($before['subject_number'] !== null ? ' for ' . $before['subject_number'] : ' · ' . humanize($before['kind']));
    log_activity($pdo, 'equipment_reservation_cancelled', 'reservation', $id, $label, ['status' => $before['status']], $after,
        ['cause' => 'person', 'resource' => $before['resource_name'], 'window' => reservation_window_label($before)], 'reservation-view');
    $pdo->commit();
    emit_action_status(true, ['record_id' => $id]);
    flash('success', 'Reservation of ' . $before['resource_name'] . ' cancelled.');
    hx_trigger('reservationsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The reservation could not be cancelled.') : $exception->getMessage());
}
hx_location(isset($before) && $before['subject_number'] !== null ? reservation_subject_url($before['subject_kind'], (int) $before['subject_id']) : '/reservations/' . $id);

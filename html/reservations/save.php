<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$catalog = reservation_resource_catalog($pdo);
$allowShare = double_booking_allowed($pdo);
$before = $id !== null ? (find_reservation($pdo, $id) ?? not_found('That reservation does not exist.')) : null;

$input = [
    'id' => $id,
    'resource' => request_string('resource', 30),
    'kind' => request_string('kind', 20) ?: 'run',
    'subject_kind' => request_string('subject_kind', 30),
    'subject_id' => request_integer('subject_id'),
    'role' => request_string('role', 20),
    'planned_from' => request_string('planned_from', 10),
    'planned_to' => request_string('planned_to', 10),
    // The form always sends all_day (0 or 1); the kernel's actions server sends times or nothing: nothing means all day.
    'all_day' => array_key_exists('all_day', $_POST) ? post_bool('all_day') : (request_string('start_time', 5) === '' && request_string('end_time', 5) === ''),
    'start_time' => request_string('start_time', 5),
    'end_time' => request_string('end_time', 5),
    'notes' => request_string('notes', 2000),
    'share' => post_bool('share'),
];
// The kernel's actions server names things by id: vessel / equipment, order / batch / press_run / packaging_run, from / to, block.
if ($input['resource'] === '') {
    if (($vid = request_integer('vessel')) !== null) { $input['resource'] = 'vessel:' . $vid; }
    elseif (($eid = request_integer('equipment')) !== null) { $input['resource'] = 'equipment:' . $eid; }
}
if (request_string('block', 20) !== '' && in_options(request_string('block', 20), RESERVATION_KINDS)) { $input['kind'] = request_string('block', 20); }
if ($input['kind'] === 'run' && $input['subject_id'] === null) {
    foreach (['order' => 'production_order', 'batch' => 'batch', 'press_run' => 'press_run', 'packaging_run' => 'packaging_run'] as $param => $subjectKind) {
        if (($sid = request_integer($param)) !== null) { $input['subject_kind'] = $subjectKind; $input['subject_id'] = $sid; break; }
    }
}
if ($input['planned_from'] === '') { $input['planned_from'] = request_string('from', 10); }
if ($input['planned_to'] === '') { $input['planned_to'] = request_string('to', 10) ?: $input['planned_from']; }
$errors = [];
$clashes = [];
$resource = reservation_resource_key($input['resource']);
if ($resource === null || !isset($catalog[$input['resource']])) { $errors['resource'] = 'Choose a vessel or a piece of equipment.'; }
if (!in_options($input['kind'], RESERVATION_KINDS)) { $errors['kind'] = 'Choose what the booking is for.'; }
$subject = null;
if ($input['kind'] === 'run') {
    if (!in_options($input['subject_kind'], RESERVATION_SUBJECT_KINDS)) { $errors['subject_kind'] = 'Choose the kind of run.'; }
    elseif ($input['subject_id'] === null || ($subject = find_reservation_subject($pdo, $input['subject_kind'], $input['subject_id'])) === null) { $errors['subject_id'] = 'Choose the run.'; }
    elseif ($subject['status'] === 'cancelled') { $errors['subject_id'] = 'That run is cancelled.'; }
} else {
    $input['subject_kind'] = '';
    $input['subject_id'] = null;
}
if ($input['role'] === '') { $input['role'] = 'other'; }
if (!in_options($input['role'], RESERVATION_ROLES)) { $errors['role'] = 'Choose a role.'; }
$window = reservation_window($input['planned_from'], $input['planned_to'], $input['all_day'], $input['start_time'], $input['end_time']);
if (is_string($window)) { $errors['planned_to'] = $window; }
if ($before !== null && $before['status'] !== 'booked') { $errors['form'] = 'A cancelled reservation cannot be changed.'; }
if ($errors === [] && $catalog[$input['resource']]['status'] === 'out_of_service') { $errors['resource'] = $catalog[$input['resource']]['name'] . ' is out of service.'; }

$shared = false;
if ($errors === []) {
    [$startsAt, $endsAt] = $window;
    $clashes = reservation_clashes($pdo, $resource[0], $resource[1], $startsAt, $endsAt, $id);
    if ($clashes !== []) {
        if ($allowShare && $input['share']) {
            $shared = true;
        } else {
            $errors['clashes'] = $allowShare ? 'This window is already booked. Tick "Book anyway" to share it, or change the window.' : 'This window is already booked; the organization does not allow double booking.';
        }
    }
}

if ($errors === []) {
    $row = ['resource_kind' => $resource[0], 'resource_id' => $resource[1], 'kind' => $input['kind'], 'subject_kind' => $input['subject_kind'] ?: null, 'subject_id' => $input['subject_id'],
        'role' => $input['role'], 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'all_day' => $input['all_day'], 'shared' => $shared || ($before !== null && $before['shared'] && $clashes !== []), 'notes' => $input['notes'] ?: null];
    try {
        $pdo->beginTransaction();
        $saved = $id === null ? insert_reservation($pdo, $row, (int) $user['id']) : update_reservation($pdo, $id, $row);
        $label = $catalog[$input['resource']]['name'] . ($subject !== null ? ' for ' . $subject['number'] : ' · ' . humanize($input['kind']));
        $details = ['resource' => $catalog[$input['resource']]['name'], 'window' => reservation_window_label($saved), 'role' => $input['role']];
        if ($subject !== null) { $details['run'] = $subject['number']; }
        if ($clashes !== []) { $details['shared'] = true; $details['clashes'] = array_values(array_filter(array_map(static fn($c) => $c['subject_number'] ?? humanize($c['kind']), $clashes))); }
        log_activity($pdo, $id === null ? 'equipment_reserved' : 'equipment_reservation_updated', 'reservation', (int) $saved['id'], $label,
            $before === null ? null : array_intersect_key($before, $saved), $saved, $details, $id === null ? 'reservation-add' : 'reservation-edit');
        $pdo->commit();
        emit_action_status(true, ['record_id' => (int) $saved['id'], 'shared' => $clashes !== []]);
        flash('success', ($id === null ? 'Reserved ' : 'Updated the reservation of ') . $catalog[$input['resource']]['name'] . ', ' . reservation_window_label($saved) . '.' . ($clashes !== [] ? ' Shared with ' . implode(', ', $details['clashes']) . '.' : ''));
        hx_trigger('reservationsChanged');
        hx_location($subject !== null ? reservation_subject_url($input['subject_kind'], (int) $input['subject_id']) : '/reservations/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The reservation could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
if (isset($errors['clashes'])) {
    emit_action_status(false, ['errors' => $errors, 'clashes' => reservation_clash_labels($catalog[$input['resource']]['name'] ?? 'The resource', $clashes), 'share_allowed' => $allowShare]);
}
$input['subject_kind'] = $input['subject_kind'] ?: 'production_order';
render_screen($id ? 'Edit Reservation' : 'Reserve Equipment', $id ? 'reservation-edit' : 'reservation-add', view('reservations/partials/form.php', [
    'reservation' => $input + ['subject_number' => $before['subject_number'] ?? null], 'errors' => $errors, 'clashes' => $clashes, 'allowShare' => $allowShare,
    'groups' => reservation_resource_groups($catalog), 'subjectOptions' => reservation_subject_options($pdo, $input['subject_kind']),
    'resourceName' => $catalog[$input['resource']]['name'] ?? '',
]), 'reservation', $id);

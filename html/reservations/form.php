<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

// Reserve a resource for a run, or block it (cleaning, maintenance, hold). Prefill: resource (vessel:ID / equipment:ID),
// on (a date), subject_kind + subject_id (the run), kind.
$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$catalog = reservation_resource_catalog($pdo);
if ($id !== null) {
    $r = find_reservation($pdo, $id) ?? not_found('That reservation does not exist.');
    if ($r['status'] !== 'booked') {
        flash('error', 'A cancelled reservation cannot be changed.');
        hx_location('/reservations/' . $id);
    }
    $reservation = $r + ['resource' => $r['resource_kind'] . ':' . (int) $r['resource_id'], 'planned_from' => $r['local_from'], 'planned_to' => $r['local_to']];
    if ($r['all_day']) { $reservation['start_time'] = ''; $reservation['end_time'] = ''; }
    $screen = 'reservation-edit';
} else {
    $resource = request_string('resource', 30);
    $on = request_string('on', 10);
    $on = preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) ? $on : '';
    $subjectKind = request_string('subject_kind', 30);
    $subjectKind = in_options($subjectKind, RESERVATION_SUBJECT_KINDS) ? $subjectKind : '';
    $kind = request_string('kind', 20);
    $reservation = [
        'resource' => isset($catalog[$resource]) ? $resource : '',
        'kind' => in_options($kind, RESERVATION_KINDS) ? $kind : 'run',
        'subject_kind' => $subjectKind ?: 'production_order',
        'subject_id' => $subjectKind !== '' ? request_integer('subject_id') : null,
        'role' => '', 'planned_from' => $on, 'planned_to' => $on, 'all_day' => true, 'start_time' => '', 'end_time' => '', 'notes' => '',
    ];
    $screen = 'reservation-add';
}
log_screen_entered($screen, 'reservation', $id, $reservation['subject_number'] ?? null);
render_screen($id ? 'Edit Reservation' : 'Reserve Equipment', $screen, view('reservations/partials/form.php', [
    'reservation' => $reservation, 'errors' => [], 'clashes' => [], 'allowShare' => double_booking_allowed($pdo),
    'groups' => reservation_resource_groups($catalog), 'subjectOptions' => reservation_subject_options($pdo, $reservation['subject_kind'] ?? 'production_order'),
]), 'reservation', $id);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That reservation does not exist.');
$reservation = find_reservation($pdo, $id) ?? not_found('That reservation does not exist.');
$clashes = $reservation['status'] === 'booked' ? reservation_clashes($pdo, $reservation['resource_kind'], (int) $reservation['resource_id'], $reservation['starts_at'], $reservation['ends_at'], $id) : [];
log_screen_entered('reservation-view', 'reservation', $id, $reservation['resource_name']);
render_screen('Reservation', 'reservation-view', view('reservations/partials/view.php', [
    'reservation' => $reservation, 'clashes' => $clashes, 'canEdit' => user_can($user, 'production'),
    'occupant' => find_resource_occupant($pdo, $reservation['resource_kind'], (int) $reservation['resource_id'], $reservation['subject_kind'], $reservation['subject_id'] === null ? null : (int) $reservation['subject_id']),
]), 'reservation', $id);

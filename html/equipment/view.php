<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/equipment/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That equipment does not exist.');
$equipment = find_equipment($pdo, $id) ?? not_found('That equipment does not exist.');
log_screen_entered('equipment-view', 'equipment', $id, $equipment['name']);
render_screen($equipment['name'], 'equipment-view', view('equipment/partials/view.php', [
    'equipment' => $equipment, 'bookings' => find_resource_bookings($pdo, 'equipment', $id), 'canEdit' => user_can($user, 'production'),
]), 'equipment', $id);

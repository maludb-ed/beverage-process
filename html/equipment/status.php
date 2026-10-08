<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/equipment/queries.php';

require_post();
verify_csrf();
$user = require_role('production');

$pdo = db();
$id = request_integer('id') ?? not_found('That equipment does not exist.');
$before = find_equipment($pdo, $id) ?? not_found('That equipment does not exist.');
$status = request_string('status', 20);

if (!in_options($status, EQUIPMENT_STATUSES)) {
    http_response_code(422);
    emit_action_status(false, ['errors' => ['status' => 'Choose available, cleaning or out of service.']]);
    echo view('shared/error.php', ['message' => 'Choose available, cleaning or out of service.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $equipment = update_equipment_status($pdo, $id, $status);
    // Bookings the change affects are named in the trail; the schedule shades them, nothing is cancelled.
    $affected = [];
    if ($status === 'out_of_service') {
        $affected = array_values(array_filter(array_map(static fn($b) => $b['subject_number'] ?? humanize($b['kind']), find_resource_bookings($pdo, 'equipment', $id)['upcoming'])));
    }
    log_activity($pdo, 'equipment_status_set', 'equipment', (int) $equipment['id'], $equipment['name'], ['status' => $before['status']], ['status' => $equipment['status']],
        $affected === [] ? [] : ['bookings_ahead' => $affected], 'equipment-view');
    $pdo->commit();
} catch (PDOException $exception) {
    $pdo->rollBack();
    error_log($exception->getMessage());
    http_response_code(422);
    echo view('shared/error.php', ['message' => db_error_message($exception) ?? 'The status could not be changed.']);
    exit;
}
emit_action_status(true, ['record_id' => $id]);
flash('success', 'Equipment "' . $equipment['name'] . '" is now ' . strtolower(EQUIPMENT_STATUSES[$equipment['status']]) . '.' . ($affected !== [] ? ' Bookings ahead: ' . implode(', ', $affected) . '.' : ''));
hx_trigger('equipmentChanged');
hx_location('/equipment/' . $id);

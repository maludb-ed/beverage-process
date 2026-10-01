<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();

$locations = inventory_locations($pdo);
$count = ['location_id' => request_integer('location_id'), 'kind' => request_string('kind', 20), 'notes' => request_string('notes', 2000)];
$errors = [];
if (!isset($locations[$count['location_id']])) { $errors['location'] = 'Choose the location to count.'; }
if (!in_options($count['kind'], COUNT_KINDS)) { $errors['kind'] = 'Choose cycle or physical.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = insert_count($pdo, $count['location_id'], $count['kind'], $count['notes'] ?: null, (int) $user['id']);
        log_activity($pdo, 'count_started', 'count', (int) $saved['id'], $saved['number'], null, $saved, [], 'count-add');
        $pdo->commit();
        flash('success', 'Count ' . $saved['number'] . ' started with ' . $saved['line_count'] . ' line(s) to count.');
        hx_trigger('countsChanged');
        hx_location('/counts/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The count could not be started.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen('Start Count', 'count-add', view('counts/partials/form.php', ['count' => $count, 'errors' => $errors, 'locations' => $locations]));

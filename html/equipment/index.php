<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/equipment/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

// Equipment as cards (Pattern B): search, kind, status and premises filters refresh only the results region.
$user = require_login();
$pdo = db();
$premises = premises_options($pdo);
$q = request_string('q', 100);
$kind = request_string('kind', 20);
$kind = in_options($kind, EQUIPMENT_KINDS) ? $kind : '';
$status = request_string('status', 20);
$status = in_options($status, EQUIPMENT_STATUSES) ? $status : '';
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : null;
$data = [
    'rows' => find_equipment_list($pdo, $q, $kind, $status, $premisesId),
    'query' => ['q' => $q, 'kind' => $kind, 'status' => $status, 'premises_id' => $premisesId],
    'premises' => $premises,
    'canEdit' => user_can($user, 'production'),
];

if (is_results_request('equipment-list-results')) {
    header('Vary: HX-Request');
    echo view('equipment/partials/cards.php', $data);
    exit;
}
log_screen_entered('equipment-list');
render_screen('Equipment', 'equipment-list', view('equipment/page.php', $data));

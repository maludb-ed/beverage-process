<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/racks/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_login();
$pdo = db();
$premises = premises_options($pdo);
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : null;
$areas = rack_board_area_options($pdo, $premisesId);
$areaId = request_integer('area_id');
$areaId = $areaId !== null && isset($areas[$areaId]) ? $areaId : null;
$q = request_string('q', 100);
$data = [
    'board' => find_rack_board($pdo, $premisesId, $areaId, $q),
    'query' => ['q' => $q, 'premises_id' => $premisesId, 'area_id' => $areaId],
    'premises' => $premises,
    'areas' => $areas,
    'canEdit' => user_can($user),
];

if (is_results_request('rack-board-results')) {
    header('Vary: HX-Request');
    echo view('racks/partials/board.php', $data);
    exit;
}
log_screen_entered('rack-board');
render_screen('Rack board', 'rack-board', view('racks/page.php', $data));

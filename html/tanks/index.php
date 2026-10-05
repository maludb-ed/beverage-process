<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/tanks/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/tank-board/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_login();
$pdo = db();
$premises = premises_options($pdo);
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : null;
$kind = request_string('kind', 20);
$kind = in_options($kind, TANK_BOARD_KINDS) ? $kind : '';
$data = [
    'vessels' => find_tank_view($pdo, $premisesId, $kind ?: null),
    'query' => ['premises_id' => $premisesId, 'kind' => $kind],
    'premises' => $premises,
    'canArrange' => user_can($user, 'production'),
];

if (is_results_request('tank-view-results')) {
    header('Vary: HX-Request');
    echo view('tanks/partials/board.php', $data);
    exit;
}
log_screen_entered('tank-view');
render_screen('Tank view', 'tank-view', view('tanks/page.php', $data));

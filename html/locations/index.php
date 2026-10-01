<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/locations/queries.php';

$user = require_login();
$query = list_params('name');
$premisesId = request_integer('premises_id');
$result = find_locations(db(), $query['q'], $query['sort'], $query['page'], $premisesId);
$data = [
    'result' => $result,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'premises_id' => $premisesId],
    'premisesOptions' => premises_options(db()),
    'canEdit' => user_can($user),
];

if (is_results_request('locations-list-results')) {
    header('Vary: HX-Request');
    echo view('locations/partials/table.php', $data);
    exit;
}
log_screen_entered('locations-list');
render_screen('Locations', 'locations-list', view('locations/page.php', $data));

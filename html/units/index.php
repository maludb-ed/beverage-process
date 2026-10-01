<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/units/queries.php';

$user = require_login();
$data = ['units' => find_units(db())];

if (is_results_request('units-list-results')) {
    header('Vary: HX-Request');
    echo view('units/partials/table.php', $data);
    exit;
}
log_screen_entered('units-list');
render_screen('Units', 'units-list', view('units/page.php', $data));

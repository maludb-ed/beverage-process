<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/racks/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

$user = require_login();
$pdo = db();
$premises = premises_options($pdo);
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : null;
$itemClass = request_string('item_class', 30);
$classes = item_class_options($pdo, null, false);
$itemClass = in_options($itemClass, $classes) ? $itemClass : '';
$q = request_string('q', 100);
$data = [
    'groups' => find_fifo_picks($pdo, $premisesId, $q, $itemClass ?: null),
    'query' => ['q' => $q, 'premises_id' => $premisesId, 'item_class' => $itemClass],
    'premises' => $premises, 'classes' => $classes,
];

if (is_results_request('rack-fifo-results')) {
    header('Vary: HX-Request');
    echo view('racks/partials/fifo-list.php', $data);
    exit;
}
log_screen_entered('rack-fifo');
render_screen('FIFO pick order', 'rack-fifo', view('racks/fifo.php', $data));

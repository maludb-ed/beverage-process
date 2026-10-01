<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_login();
$query = list_params('-lot_number');
$qualityStatus = request_string('quality_status', 20);
$qualityStatus = in_options($qualityStatus, LOT_STATUSES) ? $qualityStatus : '';
$itemClass = request_string('item_class', 30);
$itemClass = in_options($itemClass, lot_item_class_options()) ? $itemClass : '';
$expiring = request_string('expiring', 1) === '1' ? '1' : '';
$result = find_lots(db(), $query['q'], $query['sort'], $query['page'], $qualityStatus ?: null, $itemClass ?: null, $expiring === '1');
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'quality_status' => $qualityStatus, 'item_class' => $itemClass, 'expiring' => $expiring]];

if (is_results_request('lots-list-results')) {
    header('Vary: HX-Request');
    echo view('lots/partials/table.php', $data);
    exit;
}
log_screen_entered('lots-list');
render_screen('Lots', 'lots-list', view('lots/page.php', $data));

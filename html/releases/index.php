<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/releases/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('number');
$target = $_SERVER['HTTP_HX_TARGET'] ?? '';
$onlyBatches = is_htmx_request() && $target === 'release-queue-batches-results';
$onlyLots = is_htmx_request() && $target === 'release-queue-lots-results';
// Each table pages on its own: a paging request carries `page` for the table it targets; a full render starts both at page 1.
$batchPage = $onlyLots || !($onlyBatches) ? 1 : $query['page'];
$lotPage = $onlyLots ? $query['page'] : 1;
$data = ['user' => $user, 'query' => ['q' => $query['q'], 'sort' => $query['sort']]];

if ($onlyBatches) {
    header('Vary: HX-Request');
    echo view('releases/partials/queue-batches.php', $data + ['result' => quality_queue_batches($pdo, $query['q'], $query['sort'], $batchPage)]);
    exit;
}
if ($onlyLots) {
    header('Vary: HX-Request');
    echo view('releases/partials/queue-lots.php', $data + ['result' => quality_queue_lots($pdo, $query['q'], $lotPage)]);
    exit;
}
$data += ['batches' => quality_queue_batches($pdo, $query['q'], $query['sort'], 1), 'lots' => quality_queue_lots($pdo, $query['q'], 1)];
if (is_results_request('release-queue-results')) {
    header('Vary: HX-Request');
    echo view('releases/partials/queue.php', $data);
    exit;
}
log_screen_entered('release-queue');
render_screen('Release queue', 'release-queue', view('releases/page.php', $data));

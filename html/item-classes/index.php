<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/item-classes/queries.php';

$user = require_login();
$query = list_params('display_order');
$kind = request_string('kind', 10);
$kind = in_options($kind, ITEM_CLASS_KINDS) ? $kind : '';
$result = find_item_classes(db(), $query['q'], $query['sort'], $query['page'], $kind ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'kind' => $kind], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('item-classes-list-results')) {
    header('Vary: HX-Request');
    echo view('item-classes/partials/table.php', $data);
    exit;
}
log_screen_entered('item-classes-list');
render_screen('Item classes', 'item-classes-list', view('item-classes/page.php', $data));

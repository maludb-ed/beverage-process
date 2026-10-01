<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

$user = require_login();
$id = request_integer('id') ?? not_found('That count does not exist.');
$count = find_count(db(), $id) ?? not_found('That count does not exist.');
log_screen_entered('count-view', 'count', $id, $count['number']);
render_screen($count['number'], 'count-view', view('counts/partials/view.php', ['count' => $count, 'user' => $user, 'reversed' => $count['status'] === 'cancelled' && ledger_document_reversed(db(), 'counts', $id)]), 'count', $id);

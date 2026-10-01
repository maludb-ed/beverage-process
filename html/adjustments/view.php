<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

$user = require_login();
$id = request_integer('id') ?? not_found('That adjustment does not exist.');
$adjustment = find_adjustment(db(), $id) ?? not_found('That adjustment does not exist.');
log_screen_entered('adjustment-view', 'adjustment', $id, $adjustment['number']);
render_screen($adjustment['number'], 'adjustment-view', view('adjustments/partials/view.php', ['adjustment' => $adjustment, 'user' => $user, 'reversed' => $adjustment['status'] === 'cancelled' && ledger_document_reversed(db(), 'adjustments', $id)]), 'adjustment', $id);

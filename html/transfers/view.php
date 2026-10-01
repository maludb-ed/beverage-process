<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That transfer does not exist.');
$transfer = find_transfer($pdo, $id) ?? not_found('That transfer does not exist.');
log_screen_entered('transfer-view', 'transfer', $id, $transfer['number']);
render_screen($transfer['number'], 'transfer-view', view('transfers/partials/view.php', ['transfer' => $transfer, 'user' => $user, 'reversed' => $transfer['status'] === 'cancelled' && ledger_document_reversed($pdo, 'transfers', $id)]), 'transfer', $id);

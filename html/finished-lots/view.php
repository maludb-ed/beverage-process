<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/finished-lots/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That finished lot does not exist.');
$lot = find_finished_lot($pdo, $id) ?? not_found('That finished lot does not exist.');
[$reasons, $defaultReason] = finished_lot_override_reasons($pdo);
log_screen_entered('finished-lot-view', 'finished_lot', $id, $lot['lot_number']);
render_screen($lot['lot_number'], 'finished-lot-view', view('finished-lots/partials/view.php', [
    'lot' => $lot, 'balances' => find_finished_lot_balances($pdo, $id), 'removals' => find_finished_lot_removals($pdo, $id), 'kegs' => find_finished_lot_kegs($pdo, $id),
    'user' => $user, 'derived' => derive_finished_lot_tax_class($pdo, $id), 'limits' => finished_lot_hard_cider_limits($pdo), 'reasons' => $reasons, 'defaultReason' => $defaultReason,
]), 'finished_lot', $id);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That receipt does not exist.');
$receipt = find_receipt($pdo, $id) ?? not_found('That receipt does not exist.');
log_screen_entered('receipt-view', 'goods_receipt', $id, $receipt['number']);
render_screen($receipt['number'], 'receipt-view', view('receipts/partials/view.php', [
    'receipt' => $receipt, 'lines' => find_receipt_lines($pdo, $id), 'user' => $user,
]), 'goods_receipt', $id);

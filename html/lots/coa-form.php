<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_role('quality');
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot(db(), $id) ?? not_found('That lot does not exist.');
log_screen_entered('lot-coa-add', 'lot', $id, $lot['lot_number']);
render_screen('Certificate for ' . $lot['lot_number'], 'lot-coa-add', view('lots/partials/coa-form.php', ['lot' => $lot, 'input' => [], 'errors' => []]), 'lot', $id);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_role('receiving');
$id = request_integer('id') ?? not_found('Lots are created by posting documents, not by hand.');
$lot = find_lot(db(), $id) ?? not_found('That lot does not exist.');
log_screen_entered('lot-edit', 'lot', $id, $lot['lot_number']);
render_screen('Edit ' . $lot['lot_number'], 'lot-edit', view('lots/partials/form.php', ['lot' => $lot, 'errors' => []]), 'lot', $id);

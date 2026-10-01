<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot($pdo, $id) ?? not_found('That lot does not exist.');
log_screen_entered('lot-release', 'lot', $id, $lot['lot_number']);
render_screen('Release ' . $lot['lot_number'], 'lot-release', view('lots/partials/release-form.php', [
    'lot' => $lot, 'attributes' => find_lot_attributes($pdo, $id), 'certificateCount' => count(find_lot_certificates($pdo, $id)),
    'input' => [], 'errors' => [], 'reasons' => override_reason_options($pdo),
]), 'lot', $id);

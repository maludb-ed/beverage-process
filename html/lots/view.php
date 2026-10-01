<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot($pdo, $id) ?? not_found('That lot does not exist.');
$tab = request_string('tab', 20);
$tab = in_array($tab, ['overview', 'attributes', 'certificates', 'releases', 'movements'], true) ? $tab : 'overview';
log_screen_entered('lot-view', 'lot', $id, $lot['lot_number']);
render_screen($lot['lot_number'], 'lot-view', view('lots/partials/view.php', lot_view_data($pdo, $lot) + ['movements' => find_lot_movements($pdo, $id), 'user' => $user, 'activeTab' => $tab]), 'lot', $id);

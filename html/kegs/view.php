<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That keg does not exist.');
$keg = find_keg($pdo, $id) ?? not_found('That keg does not exist.');
log_screen_entered('keg-view', 'keg', $id, $keg['serial']);
render_screen($keg['serial'], 'keg-view', view('kegs/partials/view.php', [
    'keg' => $keg, 'movements' => find_keg_movements($pdo, $id), 'lots' => keg_fill_lot_options($pdo), 'user' => $user, 'deletable' => keg_deletable($pdo, $id),
]), 'keg', $id);

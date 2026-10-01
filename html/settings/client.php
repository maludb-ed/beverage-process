<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';

$user = require_role();
$pdo = db();
log_screen_entered('settings-client', 'client_settings', 1, null);
render_screen('Client settings', 'settings-client', view('settings/client-page.php', ['settings' => find_client_settings($pdo), 'errors' => [], 'pdo' => $pdo]), 'client_settings', 1);

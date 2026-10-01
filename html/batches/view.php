<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$batch = find_batch($pdo, $id) ?? not_found('That batch does not exist.');
$tab = request_string('tab', 20);
$tab = in_array($tab, ['readings', 'consumptions', 'transfers', 'losses', 'lineage', 'cost'], true) ? $tab : 'readings';
log_screen_entered('batch-view', 'batch', $id, $batch['number']);
render_screen($batch['number'], 'batch-view', view('batches/partials/view.php', batch_view_data($pdo, $batch) + ['user' => $user, 'activeTab' => $tab]), 'batch', $id);

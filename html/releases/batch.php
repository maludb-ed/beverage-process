<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/releases/queries.php';

// Screen batch-release (canonical URL /batches/{id}/release; the coordinator routes it here with ?id=).
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$context = quality_batch_context($pdo, $id) ?: not_found('That batch does not exist.');
log_screen_entered('batch-release', 'batch', $id, $context['batch']['number']);
render_screen('Release ' . $context['batch']['number'], 'batch-release', view('releases/partials/batch-release-form.php', $context + [
    'input' => [], 'errors' => [], 'reasons' => override_reason_options($pdo), 'defaultReasonId' => releases_default_reason_id($pdo),
]), 'batch', $id);

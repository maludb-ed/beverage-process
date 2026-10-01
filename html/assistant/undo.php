<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();
require_post();
verify_csrf();
// Phase 4 resolves undo_id against the action manifest and the activity log.
echo view('assistant/reply.php', ['message' => 'undo', 'reply' => 'Undo arrives with build phase 4.', 'undoId' => null]);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/activity/queries.php';

// Ask me anything: the full conversation surface of the unified assistant. The thread
// is this user's assistant exchanges from the activity log (both surfaces share one
// conversation memory), oldest first; the form appends each new exchange.
$user = require_login();
log_screen_entered('ama');
$rows = array_reverse(assistant_transcript(db(), (int) $user['id'], 50));
render_screen('Ask me anything', 'ama', view('ama/page.php', ['rows' => $rows]));

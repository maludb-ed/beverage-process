<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();
log_screen_entered('ama');
render_screen('Ask me anything', 'ama', view('ama/page.php', []));

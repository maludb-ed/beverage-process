<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/activity/queries.php';
$user = require_login();
echo view('assistant/transcript.php', ['rows' => assistant_transcript(db(), (int) $user['id'])]);

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// Phase 2 stub for the command bar and AMA page: records the utterance with its
// screen context in the activity log and answers with a placeholder. Phase 4
// replaces the reply with a call to the unified assistant service.
$user = require_login();
require_post();
verify_csrf();
$message = request_string('message', 2000);
if ($message === '') {
    http_response_code(422);
    echo view('assistant/reply.php', ['message' => '', 'reply' => 'Say or type something first.', 'undoId' => null]);
    exit;
}
$screen   = request_string('screen', 80);
$entity   = request_string('entity', 80);
$recordId = request_integer('record_id');
$source   = request_string('source', 20) === 'ama' ? 'ama' : 'command_bar';
$reply = 'The assistant arrives with build phase 4. I noted: "' . $message . '"' . ($screen !== '' ? ' (on ' . $screen . ')' : '') . '.';
log_activity(db(), $source === 'ama' ? 'ama_question' : 'assistant_message', $entity !== '' ? $entity : null, $recordId, null, null, null,
    ['message' => $message, 'reply' => $reply, 'screen' => $screen], $screen !== '' ? $screen : null, $source);
echo view('assistant/reply.php', ['message' => $message, 'reply' => $reply, 'undoId' => null]);

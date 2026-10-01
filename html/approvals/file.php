<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';

// Streams the document attached to an approval (COLA, formula). The file lives outside the web root.
$user = require_login();
$pdo = db();
$approval = find_approval($pdo, request_integer('id') ?? 0) ?? not_found('That approval does not exist.');
$statement = $pdo->prepare("SELECT * FROM app.attachments WHERE id = :id AND entity_type = 'product_approval'");
$statement->execute(['id' => (int) $approval['attachment_id']]);
$attachment = $statement->fetch();
if ($attachment === false || !is_file($attachment['storage_path'])) {
    not_found('That document does not exist.');
}
log_screen_entered('approval-file', 'product_approval', (int) $approval['id'], $attachment['file_name']);
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($attachment['storage_path']));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $attachment['file_name']) . '"');
header('Cache-Control: private, no-store');
readfile($attachment['storage_path']);

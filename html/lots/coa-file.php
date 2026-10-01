<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

// Streams a certificate attached to this lot. The file lives outside the web root.
$user = require_login();
$id = request_integer('id') ?? not_found();
$attachment = find_attachment(db(), request_integer('attachment_id') ?? 0);
if ($attachment === null || $attachment['entity_type'] !== 'lot' || (int) $attachment['entity_id'] !== $id || !is_file($attachment['storage_path'])) {
    not_found('That document does not exist.');
}
log_screen_entered('lot-coa-file', 'lot', $id, $attachment['file_name']);
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($attachment['storage_path']));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $attachment['file_name']) . '"');
header('Cache-Control: private, no-store');
readfile($attachment['storage_path']);

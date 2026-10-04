<?php
declare(strict_types=1);
/** GET /api/v1/health — unauthenticated; the kernel's health check and the installer read it (registration.md). */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$manifest = json_decode((string) @file_get_contents(dirname(__DIR__, 3) . '/maludb-os.json'), true) ?: [];
$out = ['ok' => true, 'application' => os_app_key(), 'version' => (string) ($manifest['version'] ?? ''), 'database' => 'ok',
        'maludb' => (string) config('os.maludb_api_token', '') !== '' ? 'ok' : 'unconfigured', 'ingest_lag' => null, 'os_enabled' => os_enabled(), 'directory' => null];
try {
    $pdo = db();
    $out['ingest_lag'] = (int) $pdo->query('SELECT count(*) FROM app.activity_log WHERE ingested_at IS NULL')->fetchColumn();
    $sync = $pdo->query('SELECT last_run_at, last_error, next_cursor FROM app.directory_sync_state WHERE id = 1')->fetch() ?: [];
    $out['directory'] = ['last_run_at' => $sync['last_run_at'] ?? null, 'synced' => ($sync['next_cursor'] ?? null) !== null, 'error' => $sync['last_error'] ?? null];
} catch (Throwable $e) {
    error_log('health: ' . $e->getMessage());
    $out['ok'] = false;
    $out['database'] = 'error';
}
http_response_code($out['ok'] ? 200 : 503);
echo json_encode($out, JSON_UNESCAPED_SLASHES);

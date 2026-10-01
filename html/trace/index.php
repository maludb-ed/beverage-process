<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/trace/queries.php';

// Recall trace. ?lot_number= traces forward (a finished lot traces backward from its batch);
// ?batch_number= traces backward. ?format=csv exports the same rows.
$user = require_login();
$pdo = db();
$lotNumber = request_string('lot_number', 60);
$batchNumber = request_string('batch_number', 60);
$trace = ['lot_number' => $lotNumber, 'batch_number' => $batchNumber, 'direction' => null, 'start' => null, 'rows' => [], 'summary' => null, 'error' => null];
if ($lotNumber !== '' && $batchNumber !== '') {
    $trace['error'] = 'Enter a lot number or a batch number, not both.';
} elseif ($lotNumber !== '') {
    $lot = find_lot_by_number($pdo, $lotNumber);
    if ($lot === null) {
        $trace['error'] = 'No lot ' . $lotNumber . ' exists.';
    } elseif (($batch = trace_finished_lot_batch($pdo, (int) $lot['id'])) !== null) {
        $trace += ['entity' => ['lot', (int) $lot['id'], $lot['lot_number']]];
        $trace['direction'] = 'backward';
        $trace['start'] = $lot['lot_number'] . ' (finished lot of ' . $batch['number'] . ')';
        $trace['rows'] = find_trace_backward($pdo, (int) $batch['id']);
    } else {
        $trace += ['entity' => ['lot', (int) $lot['id'], $lot['lot_number']]];
        $trace['direction'] = 'forward';
        $trace['start'] = $lot['lot_number'] . ' (' . $lot['item_code'] . ')';
        $trace['rows'] = find_trace_forward($pdo, (int) $lot['id']);
    }
} elseif ($batchNumber !== '') {
    $batch = find_batch_by_number($pdo, $batchNumber);
    if ($batch === null) {
        $trace['error'] = 'No batch ' . $batchNumber . ' exists.';
    } else {
        $trace += ['entity' => ['batch', (int) $batch['id'], $batch['number']]];
        $trace['direction'] = 'backward';
        $trace['start'] = $batch['number'];
        $trace['rows'] = find_trace_backward($pdo, (int) $batch['id']);
    }
}
if ($trace['direction'] !== null) {
    $trace['summary'] = find_trace_summary($pdo, $trace['rows']);
}
$details = ['lot_number' => $lotNumber ?: null, 'batch_number' => $batchNumber ?: null, 'direction' => $trace['direction'], 'row_count' => count($trace['rows'])];

if (request_string('format', 10) === 'csv' && $trace['direction'] !== null) {
    log_activity($pdo, 'trace_exported', $trace['entity'][0], $trace['entity'][1], $trace['entity'][2], null, null, $details, 'trace');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trace-' . preg_replace('/[^A-Za-z0-9-]/', '', $trace['entity'][2]) . '-' . $trace['direction'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['level', 'kind', 'id', 'label', 'detail', 'customer']);
    foreach ($trace['rows'] as $row) {
        fputcsv($out, [$row['level'], $row['kind'], $row['id'], $row['label'], trace_detail_text($row), $row['customer_name'] ?? '']);
    }
    fclose($out);
    exit;
}

$entered = static function () use ($trace, $details): void {
    if ($trace['direction'] === null) {
        log_screen_entered('trace');
        return;
    }
    $GLOBALS['__current_screen'] = 'trace';
    try {
        log_activity(db(), 'screen_entered', $trace['entity'][0], $trace['entity'][1], $trace['entity'][2], null, null, $details, 'trace');
    } catch (Throwable $exception) {
        error_log('screen_entered log failed: ' . $exception->getMessage());
    }
};
$pushUrl = '/trace/' . query_string(['lot_number' => $lotNumber, 'batch_number' => $batchNumber]);
if (is_results_request('trace-results')) {
    $entered();
    header('Vary: HX-Request');
    header('HX-Push-Url: ' . $pushUrl);
    echo view('trace/partials/results.php', ['trace' => $trace]);
    exit;
}
$entered();
render_screen('Trace', 'trace', view('trace/page.php', ['trace' => $trace]));

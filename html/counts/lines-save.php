<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// Pattern C: record one counted quantity and return the refreshed sheet row.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');
$count = find_count_line_owner($pdo, $id);
$lineId = request_integer('line_id') ?? 0;
$line = find_count_line($pdo, $lineId);
if ($count === null || $line === null || (int) $line['count_id'] !== $id) {
    not_found('That count line does not exist.');
}
$qtyRaw = trim(str_replace(',', '', request_string('qty_counted', 30)));
$note = request_string('note', 200);
$error = '';
if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float) $qtyRaw < 0) {
    $error = 'Enter the counted quantity (zero or more).';
} elseif (!in_array($count['status'], ['open', 'counting'], true)) {
    $error = 'This count is no longer open for counting.';
}
if ($error === '') {
    try {
        $pdo->beginTransaction();
        $qtyBase = round((float) from_display((float) $qtyRaw, $line['base_unit_code'], inventory_unit_kind($line['item_class'])), 4);
        $result = record_count_line($pdo, $lineId, $qtyBase, $note ?: null, (int) $user['id']);
        log_activity($pdo, 'count_line_recorded', 'count', $id, $count['number'],
            ['line_id' => $lineId, 'lot' => $line['lot_number'], 'qty_counted_base' => $result['before']['qty_counted_base']],
            ['line_id' => $lineId, 'lot' => $line['lot_number'], 'qty_counted_base' => $result['after']['qty_counted_base'], 'variance_base' => $result['after']['variance_base']], [], 'count-view');
        $pdo->commit();
        $line = $result['after'];
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $error = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The count could not be saved.') : $exception->getMessage();
    }
}
if ($error !== '') {
    http_response_code(422);
}
header('Vary: HX-Request');
echo view('counts/partials/sheet-row.php', ['line' => $line, 'canEdit' => true, 'error' => $error]);

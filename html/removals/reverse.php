<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

// Reverse a posted removal or return by posting the opposite document; the original becomes 'reversed'.
require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That removal does not exist.');
$reason = request_string('reason', 500);
if ($reason === '') {
    $removal = find_removal($pdo, $id) ?? not_found('That removal does not exist.');
    http_response_code(422);
    render_screen($removal['number'], 'removal-view', view('removals/partials/view.php', [
        'removal' => $removal, 'lines' => find_removal_lines($pdo, $id), 'tax' => compute_removal_tax($pdo, (int) $removal['premises_id'], find_removal_lines($pdo, $id)), 'user' => $user, 'reverseError' => 'Give a reason for the reversal.',
    ]), 'removal', $id);
    exit;
}
try {
    $pdo->beginTransaction();
    $result = reverse_removal($pdo, $id, (int) $user['id'], $reason);
    $reversal = $result['reversal'];
    log_activity($pdo, 'removal_reversed', 'removal', $id, $result['original']['number'], ['status' => 'posted'], ['status' => 'reversed', 'reversed_by_id' => (int) $reversal['id']],
        ['reason' => $reason, 'reversal_number' => $reversal['number']], 'removal-view');
    log_activity($pdo, 'removal_posted', 'removal', (int) $reversal['id'], $reversal['number'], null, [
        'destination_kind' => $reversal['destination_kind'], 'units' => $result['post']['units'], 'wine_gallons' => (float) $reversal['wine_gallons'],
        'tax_amount' => $reversal['tax_amount'] === null ? null : (float) $reversal['tax_amount'],
    ], ['reversal_of' => $result['original']['number']], 'removal-view');
    foreach ($result['post']['kegs'] as $keg) {
        if ($keg['event'] !== null) {
            log_activity($pdo, $keg['event'], 'keg', $keg['keg_id'], $keg['serial'], null, null, ['removal' => $reversal['number'], 'reversal_of' => $result['original']['number']], 'removal-view');
        }
    }
    $pdo->commit();
    flash('success', $result['original']['number'] . ' reversed by ' . $reversal['number'] . '.');
    hx_trigger('removalsChanged, inventoryChanged, kegsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('removal reverse failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The removal could not be reversed.'));
}
hx_location('/removals/' . $id);

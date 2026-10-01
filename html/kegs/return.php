<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

// GET shows the form (prefill ?serials=KEG-0001); POST returns every serial typed, one per line.
$pdo = db();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!$isPost) {
    $user = require_role('production');
    log_screen_entered('keg-return');
    render_screen('Return kegs', 'keg-return', view('kegs/partials/return-form.php', [
        'input' => ['serials' => request_string('serials', 2000)], 'errors' => [], 'customers' => keg_customer_options($pdo), 'results' => [], 'hasLocation' => keg_default_location_id($pdo) !== null,
    ]));
    exit;
}
verify_csrf();
$user = require_role('production');
$customers = keg_customer_options($pdo);
$raw = request_string('serials', 5000);
$serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $raw) ?: [])));
$customerId = request_integer('customer_id');
$errors = [];
if ($serials === []) { $errors['serials'] = 'Enter at least one serial.'; }
if ($customerId !== null && !isset($customers[$customerId])) { $errors['customer_id'] = 'Choose a customer from the list.'; }
if (count($serials) > 200) { $errors['serials'] = 'Return at most 200 kegs at a time.'; }
if (keg_default_location_id($pdo) === null) { $errors['form'] = 'No packaged goods location exists to hold returned kegs. Add one under Locations.'; }
$results = [];
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $results = return_kegs_by_serial($pdo, $serials, $customerId, (int) $user['id']);
        $returned = array_values(array_filter($results, static fn($r) => $r['result'] === 'returned'));
        if ($returned !== []) {
            log_activity($pdo, 'keg_returned', 'keg', null, count($returned) . ' keg(s)', null, ['serials' => array_column($returned, 'serial')],
                ['customer_id' => $customerId, 'requested' => count($serials), 'skipped' => array_column(array_filter($results, static fn($r) => $r['result'] !== 'returned'), 'serial')], 'keg-return');
            hx_trigger('kegsChanged');
        }
        $pdo->commit();
        if (count($returned) === count($results)) {
            flash('success', count($returned) . ' keg(s) returned.');
            hx_location('/kegs/');
        }
        if ($returned !== []) { flash('success', count($returned) . ' keg(s) returned.'); }
        $failed = array_column(array_filter($results, static fn($r) => $r['result'] !== 'returned'), 'serial');
        render_screen('Return kegs', 'keg-return', view('kegs/partials/return-form.php', [
            'input' => ['serials' => implode("\n", $failed), 'customer_id' => $customerId], 'errors' => [], 'customers' => $customers, 'results' => $results, 'hasLocation' => true,
        ]));
        exit;
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('keg return failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The kegs could not be returned.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen('Return kegs', 'keg-return', view('kegs/partials/return-form.php', [
    'input' => ['serials' => $raw, 'customer_id' => $customerId], 'errors' => $errors, 'customers' => $customers, 'results' => [], 'hasLocation' => keg_default_location_id($pdo) !== null,
]));

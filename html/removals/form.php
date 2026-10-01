<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

// removal-add / return-add (?direction=in) / removal-edit. With ?refresh=1 the form re-renders from the
// submitted values (Pattern A on destination, customer and location changes) so hidden fields and lot
// choices follow the destination.
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id');
$tz = new DateTimeZone((string) config('app.timezone'));
$stored = null;
if ($id !== null) {
    $stored = find_removal($pdo, $id) ?? not_found('That removal does not exist.');
    if ($stored['status'] !== 'draft') {
        hx_location('/removals/' . $id);
    }
}
$customers = customers_options($pdo, $stored['customer_id'] ?? null);

if (request_string('refresh', 1) === '1') {
    $direction = request_string('direction', 3) === 'in' ? 'in' : 'out';
    $removal = [
        'direction' => $direction, 'destination_kind' => request_string('destination_kind', 30), 'customer_id' => request_integer('customer_id'),
        'from_location_id' => request_integer('from_location_id'), 'to_location_id' => request_integer('to_location_id'),
        'removed_at' => request_string('removed_at', 20), 'reference' => request_string('reference', 80), 'notes' => request_string('notes', 2000),
    ];
    if (request_string('changed', 20) === 'customer' && $direction === 'out' && $removal['customer_id'] !== null) {
        $customer = find_customer($pdo, $removal['customer_id']);
        $removal['destination_kind'] = $customer['default_destination'] ?? $removal['destination_kind'];
    }
    $raw = $_GET['lines'] ?? [];
    $lines = [];
    foreach (is_array($raw) ? $raw : [] as $n => $line) {
        if (is_array($line)) {
            $lines[preg_replace('/[^a-z0-9]/i', '', (string) $n) ?: 'n' . count($lines)] = [
                'lot_id' => (int) ($line['lot_id'] ?? 0) ?: null, 'units' => (string) ($line['units'] ?? ''),
                'keg_ids' => array_map('intval', is_array($line['keg_ids'] ?? null) ? $line['keg_ids'] : []),
            ];
        }
    }
} elseif ($stored !== null) {
    $removal = $stored;
    $removal['removed_at'] = (new DateTimeImmutable((string) $stored['removed_at']))->setTimezone($tz)->format('Y-m-d\TH:i');
    $lines = removals_form_lines(find_removal_lines($pdo, $id));
} else {
    // Prefill: ?direction=in, ?customer=<name>, ?destination_kind=
    $direction = request_string('direction', 3) === 'in' ? 'in' : 'out';
    $name = mb_strtolower(request_string('customer', 120));
    $match = $name !== '' ? array_search($name, array_map('mb_strtolower', $customers), true) : false;
    $customerId = $match !== false ? (int) $match : null;
    $dest = request_string('destination_kind', 30);
    if ($direction === 'in') {
        $dest = 'return_from_customer';
    } elseif (!in_options($dest, REMOVAL_OUT_DESTINATIONS)) {
        $dest = $customerId !== null ? (string) (find_customer($pdo, $customerId)['default_destination'] ?? 'tax_paid_sale') : 'tax_paid_sale';
    }
    $removal = ['direction' => $direction, 'destination_kind' => $dest, 'customer_id' => $customerId, 'from_location_id' => null, 'to_location_id' => null,
        'removed_at' => (new DateTimeImmutable('now', $tz))->format('Y-m-d\TH:i'), 'reference' => '', 'notes' => ''];
    $lines = [];
}
$removal['id'] = $id;
$removal['number'] = $stored['number'] ?? null;
$direction = $removal['direction'] === 'in' ? 'in' : 'out';
$removal['direction'] = $direction;
if ($direction === 'in') {
    $removal['destination_kind'] = 'return_from_customer';
} elseif (!in_options($removal['destination_kind'], REMOVAL_OUT_DESTINATIONS)) {
    $removal['destination_kind'] = 'tax_paid_sale';
}
$fromLocations = removals_location_options($pdo, 'from');
$toLocations = $direction === 'in' ? removals_location_options($pdo, 'return') : ($removal['destination_kind'] === 'taproom_transfer' ? removals_location_options($pdo, 'taproom') : []);
if ($direction === 'out' && $removal['from_location_id'] === null && count($fromLocations) === 1) { $removal['from_location_id'] = array_key_first($fromLocations); }
if ($toLocations !== [] && $removal['to_location_id'] === null && count($toLocations) === 1) { $removal['to_location_id'] = array_key_first($toLocations); }
if ($lines === []) { $lines = ['n1' => []]; }
$options = removals_line_options($pdo, $direction, $direction === 'out' ? $removal['from_location_id'] : null, $removal['customer_id']);

$screen = $id !== null ? 'removal-edit' : ($direction === 'in' ? 'return-add' : 'removal-add');
if (request_string('refresh', 1) !== '1') {
    log_screen_entered($screen, 'removal', $id, $removal['number']);
}
render_screen($id ? 'Edit ' . $removal['number'] : ($direction === 'in' ? 'Add Return' : 'Add Removal'), $screen, view('removals/partials/form.php', [
    'removal' => $removal, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'customers' => $customers,
    'fromLocations' => $fromLocations, 'toLocations' => $toLocations, 'options' => $options,
]), 'removal', $id);

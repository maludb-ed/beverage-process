<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

// Pattern A readback: tax determination recomputed from the form's current lines.
$user = require_login();
$pdo = db();
$direction = request_string('direction', 3) === 'in' ? 'in' : 'out';
$dest = request_string('destination_kind', 30);
$dest = in_options($dest, $direction === 'in' ? REMOVAL_IN_DESTINATIONS : REMOVAL_OUT_DESTINATIONS) ? $dest : ($direction === 'in' ? 'return_from_customer' : 'tax_paid_sale');
$premisesId = removals_location_premises($pdo, request_integer('from_location_id') ?? request_integer('to_location_id')) ?? removals_default_premises_id($pdo);
$lines = [];
foreach ((is_array($_GET['lines'] ?? null) ? $_GET['lines'] : []) as $raw) {
    if (is_array($raw)) {
        $lines[] = ['lot_id' => (int) ($raw['lot_id'] ?? 0), 'units' => (int) ($raw['units'] ?? 0)];
    }
}
$asOf = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', request_string('removed_at', 20), new DateTimeZone((string) config('app.timezone')));
$tax = $premisesId !== null ? compute_removal_tax($pdo, $premisesId, $lines, $asOf ? $asOf->format('Y-m-d') : null) : null;
echo view('removals/partials/tax-preview.php', ['tax' => $tax, 'determined' => removal_determines_tax($direction, $dest), 'destination' => $dest, 'prefix' => 'removal-form-tax']);

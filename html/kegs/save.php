<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$before = $id !== null ? (find_keg($pdo, $id) ?? not_found('That keg does not exist.')) : null;
$keg = [
    'id' => $id, 'serial' => request_string('serial', 60), 'size_gal' => request_string('size_gal', 12), 'ownership' => request_string('ownership', 20),
    'deposit_amount' => request_string('deposit_amount', 12), 'notes' => request_string('notes', 2000),
];
$errors = [];
if ($keg['serial'] === '') { $errors['serial'] = 'Serial is required.'; }
else {
    $existing = find_keg_by_serial($pdo, $keg['serial']);
    if ($existing !== null && (int) $existing['id'] !== $id) { $errors['serial'] = 'Another keg already has this serial.'; }
}
$gallons = post_decimal('size_gal');
if ($gallons === null || $gallons === false || $gallons <= 0) { $errors['size_gal'] = 'Enter the keg size, greater than zero.'; }
if (!in_options($keg['ownership'], KEG_OWNERSHIPS)) { $errors['ownership'] = 'Choose who owns the keg.'; }
$deposit = post_decimal('deposit_amount');
if ($deposit === null || $deposit === false || $deposit < 0) { $errors['deposit_amount'] = 'Enter the deposit, zero or more.'; }
$locationId = null;
if ($id === null) {
    $locationId = keg_default_location_id($pdo);
    if ($locationId === null) { $errors['form'] = 'A packaged goods location is needed to hold new kegs. Add one under Locations first.'; }
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $sizeL = (float) gal_to_liters($gallons);
        $saved = $id === null
            ? insert_keg($pdo, $keg['serial'], $sizeL, $keg['ownership'], $deposit, $keg['notes'] ?: null, $locationId)
            : update_keg($pdo, $id, $keg['serial'], $sizeL, $keg['ownership'], $deposit, $keg['notes'] ?: null);
        log_activity($pdo, $id === null ? 'keg_registered' : 'keg_updated', 'keg', (int) $saved['id'], $saved['serial'],
            $before === null ? null : array_intersect_key($before, $saved), $saved, [], $id === null ? 'keg-add' : 'keg-edit');
        $pdo->commit();
        flash('success', 'Keg ' . $saved['serial'] . ' saved.');
        hx_trigger('kegsChanged');
        hx_location('/kegs/' . $saved['id']);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = is_unique_violation($exception) ? 'Another keg already has this serial.' : (db_error_message($exception) ?? 'The keg could not be saved.');
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . ($before['serial'] ?? 'Keg') : 'Add Keg', $id ? 'keg-edit' : 'keg-add', view('kegs/partials/form.php', ['keg' => $keg, 'errors' => $errors]), 'keg', $id);

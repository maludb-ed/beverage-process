<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sensory/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$kind = request_string('target_kind', 10) === 'lot' ? 'lot' : 'batch';
$input = [
    'target_kind' => $kind, 'target_id' => request_integer('target_id'), 'panel_on' => request_string('panel_on', 10), 'panelist_name' => request_string('panelist_name', 120),
    'sample_code' => request_string('sample_code', 60), 'verdict' => request_string('verdict', 10), 'comment' => request_string('comment', 2000),
    'attributes' => [], 'faults' => [],
];
$errors = [];
$label = null;
if ($input['target_id'] === null) {
    $errors['target_id'] = 'Choose a ' . $kind . '.';
} elseif ($kind === 'batch') {
    $batch = find_reading_batch($pdo, $input['target_id']);
    if ($batch === null) { $errors['target_id'] = 'Choose a batch.'; } else { $label = $batch['number']; }
} else {
    $lot = find_reading_lot($pdo, $input['target_id']);
    if ($lot === null) { $errors['target_id'] = 'Choose a lot.'; } else { $label = $lot['lot_number']; }
}
if (post_date('panel_on') === null || post_date('panel_on') === false) { $errors['panel_on'] = 'Use a valid panel date.'; }
if (!in_options($input['verdict'], SENSORY_VERDICTS)) { $errors['verdict'] = 'Choose the panel verdict.'; }
$rawAttributes = is_array($_POST['attributes'] ?? null) ? $_POST['attributes'] : [];
foreach (SENSORY_ATTRIBUTES as $key => $name) {
    $raw = trim((string) ($rawAttributes[$key] ?? ''));
    if ($raw === '') { continue; }
    $n = filter_var($raw, FILTER_VALIDATE_INT);
    if ($n === false || $n < 1 || $n > 5) { $errors['attr_' . $key] = $name . ' must be 1 to 5.'; continue; }
    $input['attributes'][$key] = $n;
}
$rawFaults = is_array($_POST['faults'] ?? null) ? $_POST['faults'] : [];
$intensities = is_array($_POST['fault_intensity'] ?? null) ? $_POST['fault_intensity'] : [];
$faults = [];
foreach (SENSORY_FAULTS as $key => $name) {
    if (!isset($rawFaults[$key])) { continue; }
    $n = filter_var(trim((string) ($intensities[$key] ?? '')), FILTER_VALIDATE_INT);
    if ($n === false || $n < 1 || $n > 3) { $errors['fault_' . $key] = $name . ' intensity must be 1 to 3.'; $n = null; }
    $input['faults'][$key] = $n;
    if ($n !== null) { $faults[] = ['fault' => $key, 'intensity' => $n]; }
}
// The panelist is the current user while the name is unchanged; an edited name is a guest panelist.
$panelistId = $input['panelist_name'] === '' || $input['panelist_name'] === $user['display_name'] ? (int) $user['id'] : null;
$panelistName = $input['panelist_name'] === '' ? $user['display_name'] : $input['panelist_name'];

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $created = insert_sensory_record($pdo, $kind, $input['target_id'], $input['panel_on'], $panelistId, $panelistName, $input['sample_code'] ?: null, $input['verdict'], $input['attributes'], $faults, $input['comment'] ?: null);
        log_activity($pdo, 'sensory_recorded', 'sensory_record', (int) $created['id'], $label, null,
            ['target' => $kind . ':' . $label, 'verdict' => $input['verdict'], 'faults' => $faults], ['panelist' => $panelistName], 'sensory-add');
        $pdo->commit();
        flash('success', 'Sensory panel recorded for ' . $label . ': ' . strtolower(SENSORY_VERDICTS[$input['verdict']]) . '.');
        hx_trigger('sensoryChanged');
        hx_location('/sensory/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('sensory save failed: ' . $exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The panel record could not be saved.';
    }
}
http_response_code(422);
render_screen('Record sensory panel', 'sensory-add', view('sensory/partials/form.php', ['input' => $input, 'errors' => $errors, 'targets' => find_reading_targets($pdo, $kind)]));
